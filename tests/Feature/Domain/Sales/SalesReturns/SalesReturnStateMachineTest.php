<?php

declare(strict_types=1);

use App\Domain\Sales\SalesReturns\Events\SalesReturnApproved;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCancelled;
use App\Domain\Sales\SalesReturns\Events\SalesReturnCompleted;
use App\Domain\Sales\SalesReturns\Events\SalesReturnRejected;
use App\Domain\Sales\SalesReturns\Events\SalesReturnSubmitted;
use App\Domain\Sales\SalesReturns\SalesReturnStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
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
| SalesReturnStateMachine Transitions
|--------------------------------------------------------------------------
|
| Tests for all valid and invalid transitions in the sales return workflow.
|
*/

describe('SalesReturnStateMachine transitions', function () {
    it('allows transition from draft to submitted when sales return has items', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });

    it('blocks transition from draft to submitted when sales return has no items', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Submitted);
        })->toThrow(\InvalidArgumentException::class, 'Sales return tidak dapat diajukan karena tidak memiliki item.');
    });

    it('allows transition from draft to cancelled', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from submitted to approved', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('allows transition from submitted to rejected', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('allows transition from submitted to cancelled', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from approved to completed', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('allows transition from approved to cancelled', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('blocks transition from rejected to any status', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create(['status' => DocumentStatus::Rejected]);

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('blocks transition from completed to any status', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->completed()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        expect($salesReturn->fresh()->status)->toBe(DocumentStatus::Submitted);
    });

    it('throws exception on invalid transition', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        $stateMachine->transitionTo(DocumentStatus::Completed);
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| SalesReturnStateMachine Guards
|--------------------------------------------------------------------------
|
| Tests for guard conditions that block transitions.
|
*/

describe('SalesReturnStateMachine guards', function () {
    it('requires items to submit sales return', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect(function () use ($salesReturn) {
            $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);
            $stateMachine->transitionTo(DocumentStatus::Submitted);
        })->toThrow(\InvalidArgumentException::class, 'Sales return tidak dapat diajukan karena tidak memiliki item.');
    });

    it('allows submit transition when has items', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });

    it('blocks invalid state transitions', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| SalesReturnStateMachine Events
|--------------------------------------------------------------------------
|
| Tests that proper events are dispatched during transitions.
|
*/

describe('SalesReturnStateMachine events', function () {
    it('dispatches SalesReturnSubmitted event when submitting', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(SalesReturnSubmitted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SalesReturnSubmitted::class);
        expect($event->salesReturnId)->toBe($salesReturn->id);
    });

    it('dispatches SalesReturnApproved event when approving', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(SalesReturnApproved::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SalesReturnApproved::class);
        expect($event->salesReturnId)->toBe($salesReturn->id);
    });

    it('dispatches SalesReturnRejected event when rejecting', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $this->user->id,
            'rejection_reason' => 'Items not damaged as claimed',
        ]);

        expect($eventDispatcher->dispatchCount(SalesReturnRejected::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SalesReturnRejected::class);
        expect($event->salesReturnId)->toBe($salesReturn->id);
        expect($event->reason)->toBe('Items not damaged as claimed');
    });

    it('dispatches SalesReturnCompleted event when completing', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(SalesReturnCompleted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SalesReturnCompleted::class);
        expect($event->salesReturnId)->toBe($salesReturn->id);
    });

    it('dispatches SalesReturnCancelled event when cancelling from draft', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'reason' => 'Customer changed mind',
        ]);

        expect($eventDispatcher->dispatchCount(SalesReturnCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(SalesReturnCancelled::class);
        expect($event->salesReturnId)->toBe($salesReturn->id);
        expect($event->reason)->toBe('Customer changed mind');
    });

    it('dispatches SalesReturnCancelled event when cancelling from submitted', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'reason' => 'Processing error',
        ]);

        expect($eventDispatcher->dispatchCount(SalesReturnCancelled::class))->toBe(1);
    });

    it('dispatches SalesReturnCancelled event when cancelling from approved', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'reason' => 'Stock not available for replacement',
        ]);

        expect($eventDispatcher->dispatchCount(SalesReturnCancelled::class))->toBe(1);
    });
});

/*
|--------------------------------------------------------------------------
| SalesReturnStateMachine Business Helpers
|--------------------------------------------------------------------------
|
| Tests for business rule helper methods.
|
*/

describe('SalesReturnStateMachine business helpers', function () {
    it('reports canSubmit correctly', function () {
        $srWithItems = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();
        $srWithoutItems = SalesReturn::factory()->draft()->create();

        expect(SalesReturnStateMachine::fromSalesReturn($srWithItems)->canSubmit())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($srWithoutItems)->canSubmit())->toBeFalse();
    });

    it('reports canApprove correctly', function () {
        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $draftSR = SalesReturn::factory()->draft()->create();

        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canApprove())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($draftSR)->canApprove())->toBeFalse();
    });

    it('reports canReject correctly', function () {
        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $draftSR = SalesReturn::factory()->draft()->create();

        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canReject())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($draftSR)->canReject())->toBeFalse();
    });

    it('reports canComplete correctly', function () {
        $approvedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(SalesReturnStateMachine::fromSalesReturn($approvedSR)->canComplete())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canComplete())->toBeFalse();
    });

    it('reports canCancel correctly for cancellable statuses', function () {
        $draftSR = SalesReturn::factory()->draft()->create();
        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        expect(SalesReturnStateMachine::fromSalesReturn($draftSR)->canCancel())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canCancel())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($approvedSR)->canCancel())->toBeTrue();
    });

    it('reports canCancel correctly for non-cancellable statuses', function () {
        $completedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->completed()
            ->create();

        expect(SalesReturnStateMachine::fromSalesReturn($completedSR)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draftSR = SalesReturn::factory()->draft()->create();
        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(SalesReturnStateMachine::fromSalesReturn($draftSR)->canEdit())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftSR = SalesReturn::factory()->draft()->create();
        $submittedSR = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(SalesReturnStateMachine::fromSalesReturn($draftSR)->canDelete())->toBeTrue();
        expect(SalesReturnStateMachine::fromSalesReturn($submittedSR)->canDelete())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| SalesReturnStateMachine Workflow Metadata
|--------------------------------------------------------------------------
|
| Tests for workflow metadata generation.
|
*/

describe('SalesReturnStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft sales return', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for submitted sales return', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Rejected->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for completed sales return', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->completed()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Completed->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn);
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
| SalesReturnStateMachine Timestamp Updates
|--------------------------------------------------------------------------
|
| Tests for automatic timestamp updates during transitions.
|
*/

describe('SalesReturnStateMachine timestamp updates', function () {
    it('updates timestamps on submit transition', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $this->user->id]);

        $salesReturn->refresh();

        expect($salesReturn->submitted_at)->not->toBeNull();
        expect($salesReturn->submitted_by)->toBe($this->user->id);
    });

    it('updates timestamps on approve transition', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $this->user->id]);

        $salesReturn->refresh();

        expect($salesReturn->approved_at)->not->toBeNull();
        expect($salesReturn->approved_by)->toBe($this->user->id);
    });

    it('updates timestamps and reason on reject transition', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $this->user->id,
            'rejection_reason' => 'Not within warranty period',
        ]);

        $salesReturn->refresh();

        expect($salesReturn->rejected_at)->not->toBeNull();
        expect($salesReturn->rejected_by)->toBe($this->user->id);
        expect($salesReturn->rejection_reason)->toBe('Not within warranty period');
    });

    it('updates timestamps on complete transition', function () {
        $salesReturn = SalesReturn::factory()
            ->has(SalesReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = SalesReturnStateMachine::fromSalesReturn($salesReturn, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $this->user->id]);

        $salesReturn->refresh();

        expect($salesReturn->completed_at)->not->toBeNull();
        expect($salesReturn->completed_by)->toBe($this->user->id);
    });
});
