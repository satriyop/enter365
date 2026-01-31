<?php

declare(strict_types=1);

use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnApproved;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCancelled;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnCompleted;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnRejected;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnStatusChanged;
use App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnSubmitted;
use App\Domain\Purchasing\PurchaseReturns\PurchaseReturnStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PurchaseReturnStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the purchase return state machine including transitions,
| guards, actions, workflow metadata, and business rule helpers.
|
*/

describe('PurchaseReturnStateMachine transitions', function () {
    it('allows transition from draft to submitted when PR has items', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });

    it('throws exception when transitioning from draft to submitted without items', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()->draft()->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Purchase return tidak dapat diajukan karena tidak memiliki item.');

    it('allows transition from draft to cancelled', function () {
        $pr = PurchaseReturn::factory()->draft()->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from submitted to approved', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('allows transition from submitted to rejected', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('allows transition from submitted to cancelled', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from approved to completed', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('allows transition from approved to cancelled', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('blocks transition from completed to any other status', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->completed()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('blocks transition from rejected to non-draft statuses', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $user = User::factory()->create();
        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => 'Invalid return',
        ]);

        $rejectedStateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr->fresh());

        expect($rejectedStateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
        expect($rejectedStateMachine->canTransitionTo(DocumentStatus::Completed))->toBeFalse();
    });

    it('blocks transition from cancelled to any other status', function () {
        $pr = PurchaseReturn::factory()->cancelled()->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($pr->fresh()->status)->toBe(DocumentStatus::Submitted);
    });

    it('throws exception on invalid transition', function () {
        $pr = PurchaseReturn::factory()->draft()->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        $stateMachine->transitionTo(DocumentStatus::Approved);
    })->throws(StateTransitionException::class);
});

describe('PurchaseReturnStateMachine guards', function () {
    it('throws exception when submitting PR without items', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()->draft()->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Purchase return tidak dapat diajukan karena tidak memiliki item.');

    it('allows submit transition with items', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });
});

describe('PurchaseReturnStateMachine actions', function () {
    it('dispatches PurchaseReturnSubmitted event when submitting', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnSubmitted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseReturnSubmitted::class);
        expect($event->purchaseReturnId)->toBe($pr->id);
    });

    it('dispatches PurchaseReturnApproved event when approving', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnApproved::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseReturnApproved::class);
        expect($event->purchaseReturnId)->toBe($pr->id);
    });

    it('dispatches PurchaseReturnRejected event when rejecting', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => 'Items not eligible for return',
        ]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnRejected::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseReturnRejected::class);
        expect($event->purchaseReturnId)->toBe($pr->id);
    });

    it('dispatches PurchaseReturnCompleted event when completing', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnCompleted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseReturnCompleted::class);
        expect($event->purchaseReturnId)->toBe($pr->id);
    });

    it('dispatches PurchaseReturnCancelled event when cancelling', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'No longer needed',
        ]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseReturnCancelled::class);
        expect($event->purchaseReturnId)->toBe($pr->id);
    });

    it('dispatches PurchaseReturnStatusChanged event for all transitions', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseReturnStatusChanged::class))->toBeGreaterThanOrEqual(1);
    });

    it('updates submitted_at and submitted_by when submitting', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        $pr->refresh();
        expect($pr->submitted_by)->toBe($user->id);
        expect($pr->submitted_at)->not->toBeNull();
    });

    it('updates approved_at and approved_by when approving', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        $pr->refresh();
        expect($pr->approved_by)->toBe($user->id);
        expect($pr->approved_at)->not->toBeNull();
    });

    it('updates rejected_at, rejected_by, and rejection_reason when rejecting', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $rejectionReason = 'Items not eligible for return';
        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => $rejectionReason,
        ]);

        $pr->refresh();
        expect($pr->rejected_by)->toBe($user->id);
        expect($pr->rejected_at)->not->toBeNull();
        expect($pr->rejection_reason)->toBe($rejectionReason);
    });

    it('updates completed_at and completed_by when completing', function () {
        $user = User::factory()->create();
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        $pr->refresh();
        expect($pr->completed_by)->toBe($user->id);
        expect($pr->completed_at)->not->toBeNull();
    });
});

describe('PurchaseReturnStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft PR', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for submitted PR', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Rejected->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for approved PR', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Completed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for completed PR', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->completed()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Completed->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $pr = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseReturnStateMachine::fromPurchaseReturn($pr);
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

describe('PurchaseReturnStateMachine business helpers', function () {
    it('reports canSubmit correctly', function () {
        $prWithItems = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->draft()
            ->create();
        $prWithoutItems = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($prWithItems)->canSubmit())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($prWithoutItems)->canSubmit())->toBeFalse();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canSubmit())->toBeFalse();
    });

    it('reports canApprove correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canApprove())->toBeFalse();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canApprove())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($approvedPR)->canApprove())->toBeFalse();
    });

    it('reports canReject correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canReject())->toBeFalse();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canReject())->toBeTrue();
    });

    it('reports canComplete correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $approvedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();
        $completedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->completed()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canComplete())->toBeFalse();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($approvedPR)->canComplete())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($completedPR)->canComplete())->toBeFalse();
    });

    it('reports canCancel correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->approved()
            ->create();
        $completedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->completed()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canCancel())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canCancel())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($approvedPR)->canCancel())->toBeFalse();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($completedPR)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canEdit())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftPR = PurchaseReturn::factory()->draft()->create();
        $submittedPR = PurchaseReturn::factory()
            ->has(PurchaseReturnItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseReturnStateMachine::fromPurchaseReturn($draftPR)->canDelete())->toBeTrue();
        expect(PurchaseReturnStateMachine::fromPurchaseReturn($submittedPR)->canDelete())->toBeFalse();
    });
});
