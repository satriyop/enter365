<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Domain\Sales\Quotations\Events\QuotationCancelled;
use App\Domain\Sales\Quotations\Events\QuotationConverted;
use App\Domain\Sales\Quotations\Events\QuotationExpired;
use App\Domain\Sales\Quotations\Events\QuotationLost;
use App\Domain\Sales\Quotations\Events\QuotationRejected;
use App\Domain\Sales\Quotations\Events\QuotationStatusChanged;
use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Domain\Sales\Quotations\Events\QuotationWon;
use App\Enums\DocumentStatus;
use App\Listeners\Sales\QuotationEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('QuotationEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new QuotationEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all quotation events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(QuotationStatusChanged::class)
                ->and($subscriptions)->toHaveKey(QuotationSubmitted::class)
                ->and($subscriptions)->toHaveKey(QuotationApproved::class)
                ->and($subscriptions)->toHaveKey(QuotationRejected::class)
                ->and($subscriptions)->toHaveKey(QuotationCancelled::class)
                ->and($subscriptions)->toHaveKey(QuotationConverted::class)
                ->and($subscriptions)->toHaveKey(QuotationExpired::class)
                ->and($subscriptions)->toHaveKey(QuotationWon::class)
                ->and($subscriptions)->toHaveKey(QuotationLost::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new QuotationStatusChanged(
                quotationId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::Sent,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.status_changed', [
                    'quotation_id' => 1,
                    'from' => 'draft',
                    'to' => 'sent',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleSubmitted', function () {
        it('logs submitted with correct context', function () {
            $submittedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationSubmitted(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                submittedAt: $submittedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.submitted', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'customer_id' => 5,
                    'total_amount' => 2500000,
                    'submitted_at' => $submittedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleSubmitted($event);
        });
    });

    describe('handleApproved', function () {
        it('logs approved with correct context', function () {
            $approvedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationApproved(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                approvedAt: $approvedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.approved', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'approved_at' => $approvedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleApproved($event);
        });
    });

    describe('handleRejected', function () {
        it('logs rejected with correct context', function () {
            $rejectedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationRejected(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                rejectedAt: $rejectedAt,
                reason: 'Price too high',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.rejected', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'reason' => 'Price too high',
                    'rejected_at' => $rejectedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleRejected($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancelled with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationCancelled(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                previousStatus: DocumentStatus::Sent,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Customer cancelled order',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.cancelled', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'previous_status' => 'sent',
                    'reason' => 'Customer cancelled order',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });

    describe('handleConverted', function () {
        it('logs converted with correct context', function () {
            $convertedAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationConverted(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                invoiceId: 42,
                userId: 10,
                convertedAt: $convertedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.converted', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'invoice_id' => 42,
                    'converted_at' => $convertedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleConverted($event);
        });
    });

    describe('handleExpired', function () {
        it('logs expired with correct context', function () {
            $validUntil = Carbon::parse('2025-01-10');
            $expiredAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationExpired(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                validUntil: $validUntil,
                expiredAt: $expiredAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.expired', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'valid_until' => $validUntil->toDateString(),
                    'expired_at' => $expiredAt->toIso8601String(),
                ]);

            $this->subscriber->handleExpired($event);
        });
    });

    describe('handleWon', function () {
        it('logs won with correct context', function () {
            $wonAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationWon(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                wonAt: $wonAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.won', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'total_amount' => 2500000,
                    'won_at' => $wonAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleWon($event);
        });
    });

    describe('handleLost', function () {
        it('logs lost with correct context', function () {
            $lostAt = Carbon::parse('2025-01-15 10:00:00');

            $event = new QuotationLost(
                quotationId: 1,
                quotationNumber: 'QUO-2025-0001',
                customerId: 5,
                totalAmount: 2500000,
                currency: 'IDR',
                userId: 10,
                lostAt: $lostAt,
                reason: 'Competitor had better price',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('quotation.lost', [
                    'quotation_id' => 1,
                    'quotation_number' => 'QUO-2025-0001',
                    'reason' => 'Competitor had better price',
                    'lost_at' => $lostAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleLost($event);
        });
    });
});
