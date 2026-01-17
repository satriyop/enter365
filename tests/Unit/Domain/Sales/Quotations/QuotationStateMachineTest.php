<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\QuotationStateMachine;
use App\Enums\DocumentStatus;
use Carbon\Carbon;

describe('QuotationStateMachine', function () {

    describe('state transitions', function () {

        it('allows draft to be submitted when has items', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );

            expect($sm->canSubmit())->toBeTrue();
        });

        it('prevents submission without items', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                false,
                Carbon::now()->addDays(30)
            );

            expect($sm->canSubmit())->toBeFalse();
        });

        it('allows submitted to be approved when not expired', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            expect($sm->canApprove())->toBeTrue();
        });

        it('prevents approval when expired', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->subDays(10)
            );

            expect($sm->canApprove())->toBeFalse();
        });

        it('allows submitted to be rejected', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            expect($sm->canReject())->toBeTrue();
        });

        it('prevents draft from being rejected', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );

            expect($sm->canReject())->toBeFalse();
        });

        it('allows approved to be converted when not already converted', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->addDays(30),
                null
            );

            expect($sm->canConvert())->toBeTrue();
        });

        it('prevents conversion when already converted', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->addDays(30),
                123 // existing invoice ID
            );

            expect($sm->canConvert())->toBeFalse();
        });

        it('allows revision from approved, rejected, or expired', function () {
            $approved = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->addDays(30)
            );
            $rejected = QuotationStateMachine::fromQuotation(
                DocumentStatus::Rejected,
                true,
                Carbon::now()->addDays(30)
            );
            $expired = QuotationStateMachine::fromQuotation(
                DocumentStatus::Expired,
                true,
                Carbon::now()->addDays(30)
            );

            expect($approved->canRevise())->toBeTrue();
            expect($rejected->canRevise())->toBeTrue();
            expect($expired->canRevise())->toBeTrue();
        });

        it('prevents revision from draft or submitted', function () {
            $draft = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );
            $submitted = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            expect($draft->canRevise())->toBeFalse();
            expect($submitted->canRevise())->toBeFalse();
        });

        it('allows editing only in draft status', function () {
            $draft = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );
            $submitted = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            expect($draft->canEdit())->toBeTrue();
            expect($submitted->canEdit())->toBeFalse();
        });

        it('allows deletion only in draft status', function () {
            $draft = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );
            $submitted = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            expect($draft->canDelete())->toBeTrue();
            expect($submitted->canDelete())->toBeFalse();
        });
    });

    describe('outcome tracking', function () {

        it('allows marking as won from submitted or approved', function () {
            $submitted = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );
            $approved = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->addDays(30)
            );

            expect($submitted->canMarkAsWon())->toBeTrue();
            expect($approved->canMarkAsWon())->toBeTrue();
        });

        it('prevents marking as won from draft or rejected', function () {
            $draft = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );
            $rejected = QuotationStateMachine::fromQuotation(
                DocumentStatus::Rejected,
                true,
                Carbon::now()->addDays(30)
            );

            expect($draft->canMarkAsWon())->toBeFalse();
            expect($rejected->canMarkAsWon())->toBeFalse();
        });
    });

    describe('status detection', function () {

        it('detects expired status correctly', function () {
            $notExpired = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->addDays(30)
            );
            $isExpired = QuotationStateMachine::fromQuotation(
                DocumentStatus::Approved,
                true,
                Carbon::now()->subDays(10)
            );

            expect($notExpired->isExpired())->toBeFalse();
            expect($isExpired->isExpired())->toBeTrue();
        });

        it('marks quotation as expired when status is expired', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Expired,
                true,
                Carbon::now()->addDays(30)
            );

            expect($sm->isExpired())->toBeTrue();
        });
    });

    describe('next valid statuses', function () {

        it('returns next statuses for draft', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Draft,
                true,
                Carbon::now()->addDays(30)
            );

            $nextStatuses = $sm->getNextValidStatuses();

            expect($nextStatuses)->toEqual([DocumentStatus::Submitted->value]);
        });

        it('returns next statuses for submitted', function () {
            $sm = QuotationStateMachine::fromQuotation(
                DocumentStatus::Submitted,
                true,
                Carbon::now()->addDays(30)
            );

            $nextStatuses = $sm->getNextValidStatuses();

            expect($nextStatuses)->toContain(DocumentStatus::Approved->value);
            expect($nextStatuses)->toContain(DocumentStatus::Rejected->value);
        });
    });
});
