<?php

declare(strict_types=1);

use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderApproved;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderCancelled;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderPartial;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderReceived;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderRejected;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderStatusChanged;
use App\Domain\Purchasing\PurchaseOrders\Events\PurchaseOrderSubmitted;
use App\Domain\Purchasing\PurchaseOrders\PurchaseOrderStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| PurchaseOrderStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the purchase order state machine including transitions, guards,
| actions, workflow metadata, and business rule helpers.
|
*/

describe('PurchaseOrderStateMachine transitions', function () {
    it('allows transition from draft to submitted when PO has items', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });

    it('throws exception when transitioning from draft to submitted without items', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()->draft()->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Purchase Order tidak dapat diajukan karena tidak memiliki item.');

    it('allows transition from draft to cancelled', function () {
        $po = PurchaseOrder::factory()->draft()->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from submitted to approved', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeTrue();
    });

    it('allows transition from submitted to rejected', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Rejected))->toBeTrue();
    });

    it('allows transition from submitted to cancelled', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from approved to partial', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Partial))->toBeTrue();
    });

    it('allows transition from approved to received', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Received))->toBeTrue();
    });

    it('allows transition from approved to cancelled', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from partial to received', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->partial()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Received))->toBeTrue();
    });

    it('allows transition from partial to cancelled', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->partial()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from rejected to draft for revision', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->rejected()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeTrue();
    });

    it('blocks transition from received to any other status', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->received()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('blocks transition from cancelled to any other status', function () {
        $po = PurchaseOrder::factory()->cancelled()->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Approved))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($po->fresh()->status)->toBe(DocumentStatus::Submitted);
    });

    it('throws exception on invalid transition', function () {
        $po = PurchaseOrder::factory()->draft()->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        $stateMachine->transitionTo(DocumentStatus::Approved);
    })->throws(StateTransitionException::class);
});

describe('PurchaseOrderStateMachine guards', function () {
    it('throws exception when submitting PO without items', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()->draft()->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);
    })->throws(\InvalidArgumentException::class, 'Purchase Order tidak dapat diajukan karena tidak memiliki item.');

    it('allows submit transition with items', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);

        expect($stateMachine->canTransitionTo(DocumentStatus::Submitted))->toBeTrue();
    });
});

describe('PurchaseOrderStateMachine actions', function () {
    it('dispatches PurchaseOrderSubmitted event when submitting', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderSubmitted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderSubmitted::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderApproved event when approving', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Approved, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderApproved::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderApproved::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderRejected event when rejecting', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Rejected, [
            'user_id' => $user->id,
            'rejection_reason' => 'Price too high',
        ]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderRejected::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderRejected::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderCancelled event when cancelling', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'No longer needed',
        ]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderCancelled::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderPartial event when marking partial', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Partial, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderPartial::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderPartial::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderReceived event when marking received', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Received, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderReceived::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(PurchaseOrderReceived::class);
        expect($event->purchaseOrderId)->toBe($po->id);
    });

    it('dispatches PurchaseOrderStatusChanged event for all transitions', function () {
        $user = User::factory()->create();
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Submitted, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(PurchaseOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);
    });
});

describe('PurchaseOrderStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for submitted PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Submitted->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Rejected->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for approved PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Approved->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Partial->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Received->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for received PO', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->received()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Received->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $po = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = PurchaseOrderStateMachine::fromPurchaseOrder($po);
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

describe('PurchaseOrderStateMachine business helpers', function () {
    it('reports canSubmit correctly', function () {
        $poWithItems = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->draft()
            ->create();
        $poWithoutItems = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($poWithItems)->canSubmit())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($poWithoutItems)->canSubmit())->toBeFalse();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canSubmit())->toBeFalse();
    });

    it('reports canApprove correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canApprove())->toBeFalse();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canApprove())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($approvedPO)->canApprove())->toBeFalse();
    });

    it('reports canReject correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canReject())->toBeFalse();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canReject())->toBeTrue();
    });

    it('reports canCancel correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();
        $approvedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();
        $partialPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->partial()
            ->create();
        $receivedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->received()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canCancel())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canCancel())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($approvedPO)->canCancel())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($partialPO)->canCancel())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($receivedPO)->canCancel())->toBeFalse();
    });

    it('reports canReceive correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $approvedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();
        $partialPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->partial()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canReceive())->toBeFalse();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($approvedPO)->canReceive())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($partialPO)->canReceive())->toBeTrue();
    });

    it('reports canRevise correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $approvedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->approved()
            ->create();
        $rejectedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->rejected()
            ->create();
        $cancelledPO = PurchaseOrder::factory()->cancelled()->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canRevise())->toBeFalse();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($approvedPO)->canRevise())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($rejectedPO)->canRevise())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($cancelledPO)->canRevise())->toBeTrue();
    });

    it('reports canEdit correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canEdit())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftPO = PurchaseOrder::factory()->draft()->create();
        $submittedPO = PurchaseOrder::factory()
            ->has(PurchaseOrderItem::factory(), 'items')
            ->submitted()
            ->create();

        expect(PurchaseOrderStateMachine::fromPurchaseOrder($draftPO)->canDelete())->toBeTrue();
        expect(PurchaseOrderStateMachine::fromPurchaseOrder($submittedPO)->canDelete())->toBeFalse();
    });
});
