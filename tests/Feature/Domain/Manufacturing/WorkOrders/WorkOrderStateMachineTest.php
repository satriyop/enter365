<?php

declare(strict_types=1);

use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCancelled;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderCompleted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderConfirmed;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStarted;
use App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged;
use App\Domain\Manufacturing\WorkOrders\WorkOrderStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

/*
|--------------------------------------------------------------------------
| WorkOrderStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the work order state machine including transitions, guards,
| actions, event dispatching, and business rule helpers.
|
*/

describe('WorkOrderStateMachine transitions', function () {
    it('allows transition from draft to confirmed when work order has items', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeTrue();
    });

    it('blocks transition from draft to confirmed when work order has no items', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeFalse();
    });

    it('allows transition from confirmed to in_progress', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('allows transition from in_progress to completed when all sub-work orders are done', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        // Create completed and cancelled sub-work orders
        WorkOrder::factory()->completed()->create(['parent_work_order_id' => $workOrder->id]);
        WorkOrder::factory()->cancelled()->create(['parent_work_order_id' => $workOrder->id]);

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('blocks transition from in_progress to completed when sub-work orders are pending', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        // Create a sub-work order that is still in progress
        WorkOrder::factory()->inProgress()->create(['parent_work_order_id' => $workOrder->id]);

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeFalse();
    });

    it('allows cancellation from draft', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows cancellation from confirmed', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows cancellation from in_progress', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $user->id]);

        expect($workOrder->fresh()->status)->toBe(DocumentStatus::Confirmed);
    });

    it('throws exception on invalid transition', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        $stateMachine->transitionTo(DocumentStatus::Completed);
    })->throws(StateTransitionException::class);
});

describe('WorkOrderStateMachine guards', function () {
    it('requires items to confirm work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Confirmed);

        expect($reasons)->toContain('Work order tidak memiliki item.');
    });

    it('requires all sub-work orders completed to mark as completed', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        // Create a draft sub-work order
        WorkOrder::factory()->draft()->create(['parent_work_order_id' => $workOrder->id]);

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $reasons = $stateMachine->getGuardFailureReasons(DocumentStatus::Completed);

        expect($reasons)->toContain('Masih ada sub work order yang belum selesai.');
    });

    it('allows completion when no sub-work orders exist', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });
});

describe('WorkOrderStateMachine actions', function () {
    it('updates confirmed_by and confirmed_at when confirming', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $user->id]);

        $workOrder->refresh();
        expect($workOrder->confirmed_by)->toBe($user->id);
        expect($workOrder->confirmed_at)->not->toBeNull();
    });

    it('updates started_by and started_at when starting', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        $workOrder->refresh();
        expect($workOrder->started_by)->toBe($user->id);
        expect($workOrder->started_at)->not->toBeNull();
        expect($workOrder->actual_start_date)->not->toBeNull();
    });

    it('updates completed_by and completed_at when completing', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create(['quantity_ordered' => 10]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        $workOrder->refresh();
        expect($workOrder->completed_by)->toBe($user->id);
        expect($workOrder->completed_at)->not->toBeNull();
        expect($workOrder->actual_end_date)->not->toBeNull();
        expect((int) $workOrder->quantity_completed)->toBe(10);
    });

    it('updates cancelled_by and cancelled_at when cancelling', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'Dibatalkan karena permintaan pelanggan',
        ]);

        $workOrder->refresh();
        expect($workOrder->cancelled_by)->toBe($user->id);
        expect($workOrder->cancelled_at)->not->toBeNull();
        expect($workOrder->cancellation_reason)->toBe('Dibatalkan karena permintaan pelanggan');
    });
});

describe('WorkOrderStateMachine event dispatching', function () {
    it('dispatches WorkOrderConfirmed event when confirming', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(WorkOrderConfirmed::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(WorkOrderConfirmed::class);
        expect($event->workOrderId)->toBe($workOrder->id);
        expect($event->workOrderNumber)->toBe($workOrder->wo_number);
    });

    it('dispatches WorkOrderStarted event when starting', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(WorkOrderStarted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(WorkOrderStarted::class);
        expect($event->workOrderId)->toBe($workOrder->id);
    });

    it('dispatches WorkOrderCompleted event when completing', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(WorkOrderCompleted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(WorkOrderCompleted::class);
        expect($event->workOrderId)->toBe($workOrder->id);
    });

    it('dispatches WorkOrderCancelled event when cancelling', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'Test reason',
        ]);

        expect($eventDispatcher->dispatchCount(WorkOrderCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(WorkOrderCancelled::class);
        expect($event->workOrderId)->toBe($workOrder->id);
        expect($event->reason)->toBe('Test reason');
    });

    it('dispatches WorkOrderStatusChanged event on every transition', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(WorkOrderStatusChanged::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(WorkOrderStatusChanged::class);
        expect($event->workOrderId)->toBe($workOrder->id);
        expect($event->fromStatus)->toBe(DocumentStatus::Draft);
        expect($event->toStatus)->toBe(DocumentStatus::Confirmed);
    });
});

describe('WorkOrderStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft work order', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Confirmed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for confirmed work order', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Confirmed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for in progress work order', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Completed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for completed work order', function () {
        $workOrder = WorkOrder::factory()->completed()->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Completed->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder);
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

describe('WorkOrderStateMachine business helpers', function () {
    it('reports canConfirm correctly', function () {
        $withItems = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();
        $withoutItems = WorkOrder::factory()->draft()->create();

        expect(WorkOrderStateMachine::fromWorkOrder($withItems)->canConfirm())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($withoutItems)->canConfirm())->toBeFalse();
    });

    it('reports canStart correctly', function () {
        $confirmed = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();
        $draft = WorkOrder::factory()->draft()->create();

        expect(WorkOrderStateMachine::fromWorkOrder($confirmed)->canStart())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($draft)->canStart())->toBeFalse();
    });

    it('reports canComplete correctly', function () {
        $inProgress = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();
        $confirmed = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(WorkOrderStateMachine::fromWorkOrder($inProgress)->canComplete())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($confirmed)->canComplete())->toBeFalse();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draft = WorkOrder::factory()->draft()->create();
        $confirmed = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();
        $inProgress = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->inProgress()
            ->create();
        $completed = WorkOrder::factory()->completed()->create();

        expect(WorkOrderStateMachine::fromWorkOrder($draft)->canCancel())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($confirmed)->canCancel())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($inProgress)->canCancel())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($completed)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draft = WorkOrder::factory()->draft()->create();
        $confirmed = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(WorkOrderStateMachine::fromWorkOrder($draft)->canEdit())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($confirmed)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draft = WorkOrder::factory()->draft()->create();
        $confirmed = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(WorkOrderStateMachine::fromWorkOrder($draft)->canDelete())->toBeTrue();
        expect(WorkOrderStateMachine::fromWorkOrder($confirmed)->canDelete())->toBeFalse();
    });
});

describe('WorkOrderStateMachine status history', function () {
    it('records status history on transition', function () {
        $user = User::factory()->create();
        $workOrder = WorkOrder::factory()
            ->has(WorkOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = WorkOrderStateMachine::fromWorkOrder($workOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $user->id, 'reason' => 'Ready for production']);

        $workOrder->refresh();

        expect($workOrder->statusHistories)->toHaveCount(1);
        expect($workOrder->statusHistories->first()->from_status)->toBe(DocumentStatus::Draft);
        expect($workOrder->statusHistories->first()->to_status)->toBe(DocumentStatus::Confirmed);
        expect($workOrder->statusHistories->first()->user_id)->toBe($user->id);
    });
});
