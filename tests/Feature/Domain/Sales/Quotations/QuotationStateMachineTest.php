<?php

declare(strict_types=1);

use App\Domain\Sales\Quotations\Events\QuotationApproved;
use App\Domain\Sales\Quotations\Events\QuotationCancelled;
use App\Domain\Sales\Quotations\Events\QuotationConverted;
use App\Domain\Sales\Quotations\Events\QuotationExpired;
use App\Domain\Sales\Quotations\Events\QuotationRejected;
use App\Domain\Sales\Quotations\Events\QuotationStatusChanged;
use App\Domain\Sales\Quotations\Events\QuotationSubmitted;
use App\Domain\Sales\Quotations\QuotationStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(ChartOfAccountsSeeder::class);
    $this->seed(FiscalPeriodSeeder::class);
    $this->user = authenticatedAdmin();
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Transitions
|--------------------------------------------------------------------------
|
| Tests for all valid and invalid transitions in the quotation workflow.
|
*/

describe('QuotationStateMachine transitions', function () {
    it('allows transition from draft to submitted when quotation has items', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });

    it('blocks transition from draft to submitted when quotation has no items', function () {
        $quotation = Quotation::factory()->draft()->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Submitted);
        })->toThrow(\InvalidArgumentException::class, 'Penawaran tidak dapat diajukan karena tidak memiliki item.');
    });

    it('allows transition from draft to cancelled', function () {
        $quotation = Quotation::factory()->draft()->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from draft to expired', function () {
        $quotation = Quotation::factory()->draft()->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Expired))->toBeTrue();
    });

    it('allows transition from submitted to approved when not expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('blocks transition from submitted to approved when expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create(['valid_until' => now()->subDay()]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Approved);
        })->toThrow(StateTransitionException::class);
    });

    it('allows transition from submitted to rejected', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('allows transition from submitted to cancelled', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from submitted to expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Expired))->toBeTrue();
    });

    it('allows transition from approved to converted', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->singleOption()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Converted))->toBeTrue();
    });

    it('allows transition from approved to cancelled', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from approved to expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Expired))->toBeTrue();
    });

    it('allows transition from rejected to draft for revision', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->rejected()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeTrue();
    });

    it('allows transition from expired to draft for revision', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->expired()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeTrue();
    });

    it('allows transition from cancelled to draft for revision', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create(['status' => DocumentStatus::Cancelled]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeTrue();
    });

    it('transitions and persists status when valid', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Submitted);
    });

    it('throws exception on invalid transition', function () {
        $quotation = Quotation::factory()->draft()->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        $stateMachine->transitionTo(DocumentStatus::Converted);
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Guards
|--------------------------------------------------------------------------
|
| Tests for guard conditions that block transitions.
|
*/

describe('QuotationStateMachine guards', function () {
    it('requires items to submit quotation', function () {
        $quotation = Quotation::factory()->draft()->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Submitted);
        })->toThrow(\InvalidArgumentException::class, 'Penawaran tidak dapat diajukan karena tidak memiliki item.');
    });

    it('blocks approve when quotation is expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create(['valid_until' => now()->subDay()]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Approved);
        })->toThrow(StateTransitionException::class);
    });

    it('allows approve when quotation is not expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('blocks reject when quotation is expired', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create(['valid_until' => now()->subDay()]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect(function () use ($quotation) {
            $stateMachine = QuotationStateMachine::fromQuotation($quotation);
            $stateMachine->transitionTo(DocumentStatus::Rejected);
        })->toThrow(StateTransitionException::class);
    });

    it('blocks convert when not approved', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canConvert())->toBeFalse();
    });

    it('blocks convert when already converted', function () {
        $invoice = \App\Models\Sales\Invoice::factory()->create();
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create(['converted_to_invoice_id' => $invoice->id]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->canConvert())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Events
|--------------------------------------------------------------------------
|
| Tests that proper events are dispatched during transitions.
|
*/

describe('QuotationStateMachine events', function () {
    it('dispatches QuotationSubmitted event when submitting', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(QuotationSubmitted::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationSubmitted::class);
        expect($event->quotationId)->toBe($quotation->id);
    });

    it('dispatches QuotationApproved event when approving', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(QuotationApproved::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationApproved::class);
        expect($event->quotationId)->toBe($quotation->id);
    });

    it('dispatches QuotationRejected event when rejecting', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $this->user->id,
            'rejection_reason' => 'Price too high',
        ]);

        expect($eventDispatcher->dispatchCount(QuotationRejected::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationRejected::class);
        expect($event->quotationId)->toBe($quotation->id);
        expect($event->reason)->toBe('Price too high');
    });

    it('dispatches QuotationConverted event when converting', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->singleOption()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Converted, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(QuotationConverted::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationConverted::class);
        expect($event->quotationId)->toBe($quotation->id);
    });

    it('dispatches QuotationExpired event when expiring', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Expired, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(QuotationExpired::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationExpired::class);
        expect($event->quotationId)->toBe($quotation->id);
    });

    it('dispatches QuotationCancelled event when cancelling', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'cancellation_reason' => 'Customer request',
        ]);

        expect($eventDispatcher->dispatchCount(QuotationCancelled::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(QuotationStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(QuotationCancelled::class);
        expect($event->quotationId)->toBe($quotation->id);
    });
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Business Helpers
|--------------------------------------------------------------------------
|
| Tests for business rule helper methods.
|
*/

describe('QuotationStateMachine business helpers', function () {
    it('reports canSubmit correctly', function () {
        $quotationWithItems = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();
        $quotationWithoutItems = Quotation::factory()->draft()->create();

        expect(QuotationStateMachine::fromQuotation($quotationWithItems)->canSubmit())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($quotationWithoutItems)->canSubmit())->toBeFalse();
    });

    it('reports canApprove correctly', function () {
        $validQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $expiredQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create(['valid_until' => now()->subDay()]);

        expect(QuotationStateMachine::fromQuotation($validQuotation)->canApprove())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($expiredQuotation)->canApprove())->toBeFalse();
    });

    it('reports canReject correctly', function () {
        $submittedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $draftQuotation = Quotation::factory()->draft()->create();

        expect(QuotationStateMachine::fromQuotation($submittedQuotation)->canReject())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($draftQuotation)->canReject())->toBeFalse();
    });

    it('reports canConvert correctly', function () {
        $approvedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->singleOption()
            ->create();

        $submittedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(QuotationStateMachine::fromQuotation($approvedQuotation)->canConvert())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($submittedQuotation)->canConvert())->toBeFalse();
    });

    it('reports canRevise correctly for terminal statuses', function () {
        $rejectedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->rejected()
            ->create();

        $expiredQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->expired()
            ->create();

        $cancelledQuotation = Quotation::factory()->draft()->create(['status' => DocumentStatus::Cancelled]);

        expect(QuotationStateMachine::fromQuotation($rejectedQuotation)->canRevise())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($expiredQuotation)->canRevise())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($cancelledQuotation)->canRevise())->toBeTrue();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draftQuotation = Quotation::factory()->draft()->create();
        $submittedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create();

        expect(QuotationStateMachine::fromQuotation($draftQuotation)->canCancel())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($submittedQuotation)->canCancel())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($approvedQuotation)->canCancel())->toBeTrue();
    });

    it('reports canEdit correctly', function () {
        $draftQuotation = Quotation::factory()->draft()->create();
        $submittedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(QuotationStateMachine::fromQuotation($draftQuotation)->canEdit())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($submittedQuotation)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftQuotation = Quotation::factory()->draft()->create();
        $submittedQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(QuotationStateMachine::fromQuotation($draftQuotation)->canDelete())->toBeTrue();
        expect(QuotationStateMachine::fromQuotation($submittedQuotation)->canDelete())->toBeFalse();
    });

    it('reports isExpired correctly', function () {
        $validQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $expiredQuotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->expired()
            ->create();

        expect(QuotationStateMachine::fromQuotation($validQuotation)->isExpired())->toBeFalse();
        expect(QuotationStateMachine::fromQuotation($expiredQuotation)->isExpired())->toBeTrue();
    });

    it('does not treat valid_until of today as expired', function () {
        // date cast stores midnight; Carbon::isPast() would wrongly treat today as past
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create([
                'quotation_date' => now()->toDateString(),
                'valid_until' => now()->toDateString(),
            ]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);

        expect($stateMachine->isExpired())->toBeFalse();

        $stateMachine->transitionTo(DocumentStatus::Rejected, ['user_id' => $this->user->id]);

        expect($quotation->fresh()->status)->toBe(DocumentStatus::Rejected);
    });

    it('provides conversion block reason when not approved', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);
        $reason = $stateMachine->getConversionBlockReason();

        expect($reason)->toBe('Penawaran harus disetujui sebelum dikonversi ke faktur.');
    });

    it('provides conversion block reason when already converted', function () {
        $invoice = \App\Models\Sales\Invoice::factory()->create();
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->approved()
            ->create(['converted_to_invoice_id' => $invoice->id]);

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);
        $reason = $stateMachine->getConversionBlockReason();

        expect($reason)->toBe('Penawaran sudah dikonversi ke faktur.');
    });
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Workflow Metadata
|--------------------------------------------------------------------------
|
| Tests for workflow metadata generation.
|
*/

describe('QuotationStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for submitted quotation', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Rejected->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('includes all workflow elements in metadata', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = QuotationStateMachine::fromQuotation($quotation);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata)->toHaveKey('current_status');
        expect($metadata)->toHaveKey('next_statuses');
        expect($metadata)->toHaveKey('all_statuses');
        expect($metadata)->toHaveKey('transitions');
        expect($metadata['current_status'])->toHaveKey('value');
        expect($metadata['current_status'])->toHaveKey('label');
        expect($metadata['current_status'])->toHaveKey('color');
    });
});

/*
|--------------------------------------------------------------------------
| QuotationStateMachine Status History
|--------------------------------------------------------------------------
|
| Tests for status change history recording.
|
*/

describe('QuotationStateMachine status history', function () {
    it('records status history on transition', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, [
            'user_id' => $this->user->id,
            'reason' => 'Ready for customer review',
        ]);

        $quotation->refresh();

        expect($quotation->statusHistories)->toHaveCount(1);
        expect($quotation->statusHistories->first()->from_status)->toBe(DocumentStatus::Draft);
        expect($quotation->statusHistories->first()->to_status)->toBe(DocumentStatus::Submitted);
        expect($quotation->statusHistories->first()->user_id)->toBe($this->user->id);
    });

    it('updates timestamps on submit transition', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        $quotation->refresh();

        expect($quotation->submitted_at)->not->toBeNull();
        expect($quotation->submitted_by)->toBe($this->user->id);
    });

    it('updates timestamps on approve transition', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $this->user->id]);

        $quotation->refresh();

        expect($quotation->approved_at)->not->toBeNull();
        expect($quotation->approved_by)->toBe($this->user->id);
    });

    it('updates timestamps and reason on reject transition', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory(), 'items')
            ->submitted()
            ->validFor(30)
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = QuotationStateMachine::fromQuotation($quotation, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $this->user->id,
            'rejection_reason' => 'Budget constraints',
        ]);

        $quotation->refresh();

        expect($quotation->rejected_at)->not->toBeNull();
        expect($quotation->rejected_by)->toBe($this->user->id);
        expect($quotation->rejection_reason)->toBe('Budget constraints');
    });
});
