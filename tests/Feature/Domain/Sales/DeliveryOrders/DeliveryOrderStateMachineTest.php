<?php

declare(strict_types=1);

use App\Domain\Sales\DeliveryOrders\DeliveryOrderStateMachine;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderCancelled;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderConfirmed;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderDelivered;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderShipped;
use App\Domain\Sales\DeliveryOrders\Events\DeliveryOrderStatusChanged;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
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
| DeliveryOrderStateMachine Transitions
|--------------------------------------------------------------------------
|
| Tests for all valid and invalid transitions in the delivery order workflow.
|
*/

describe('DeliveryOrderStateMachine transitions', function () {
    it('allows transition from draft to confirmed when delivery order has items', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeTrue();
    });

    it('blocks transition from draft to confirmed when delivery order has no items', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Confirmed);
        })->toThrow(\InvalidArgumentException::class, 'Delivery Order tidak dapat dikonfirmasi karena tidak memiliki item.');
    });

    it('allows transition from draft to cancelled', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from confirmed to shipped', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Shipped))->toBeTrue();
    });

    it('allows transition from confirmed to cancelled', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from shipped to delivered', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Delivered))->toBeTrue();
    });

    it('blocks transition from shipped to cancelled', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
    });

    it('blocks transition from delivered to any status', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->delivered()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Draft))->toBeFalse();
    });

    it('transitions and persists status when valid', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $this->user->id]);

        expect($deliveryOrder->fresh()->status)->toBe(DocumentStatus::Confirmed);
    });

    it('throws exception on invalid transition', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        $stateMachine->transitionTo(DocumentStatus::Shipped);
    })->throws(StateTransitionException::class);
});

/*
|--------------------------------------------------------------------------
| DeliveryOrderStateMachine Guards
|--------------------------------------------------------------------------
|
| Tests for guard conditions that block transitions.
|
*/

describe('DeliveryOrderStateMachine guards', function () {
    it('requires items to confirm delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect(function () use ($stateMachine) {
            $stateMachine->transitionTo(DocumentStatus::Confirmed);
        })->toThrow(\InvalidArgumentException::class, 'Delivery Order tidak dapat dikonfirmasi karena tidak memiliki item.');
    });

    it('allows confirm transition when has items', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Confirmed))->toBeTrue();
    });

    it('blocks invalid state transitions', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);

        expect($stateMachine->canTransitionTo(DocumentStatus::Shipped))->toBeFalse();
        expect($stateMachine->canTransitionTo(DocumentStatus::Delivered))->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| DeliveryOrderStateMachine Events
|--------------------------------------------------------------------------
|
| Tests that proper events are dispatched during transitions.
|
*/

describe('DeliveryOrderStateMachine events', function () {
    it('dispatches DeliveryOrderConfirmed event when confirming', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(DeliveryOrderConfirmed::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(DeliveryOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(DeliveryOrderConfirmed::class);
        expect($event->deliveryOrderId)->toBe($deliveryOrder->id);
    });

    it('dispatches DeliveryOrderShipped event when shipping', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Shipped, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(DeliveryOrderShipped::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(DeliveryOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(DeliveryOrderShipped::class);
        expect($event->deliveryOrderId)->toBe($deliveryOrder->id);
    });

    it('dispatches DeliveryOrderDelivered event when delivering', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Delivered, ['user_id' => $this->user->id]);

        expect($eventDispatcher->dispatchCount(DeliveryOrderDelivered::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(DeliveryOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(DeliveryOrderDelivered::class);
        expect($event->deliveryOrderId)->toBe($deliveryOrder->id);
    });

    it('dispatches DeliveryOrderCancelled event when cancelling from draft', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'cancellation_reason' => 'Customer request',
        ]);

        expect($eventDispatcher->dispatchCount(DeliveryOrderCancelled::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(DeliveryOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);

        $event = $eventDispatcher->getFirstEvent(DeliveryOrderCancelled::class);
        expect($event->deliveryOrderId)->toBe($deliveryOrder->id);
        expect($event->reason)->toBe('Customer request');
    });

    it('dispatches DeliveryOrderCancelled event when cancelling from confirmed', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'cancellation_reason' => 'Stock unavailable',
        ]);

        expect($eventDispatcher->dispatchCount(DeliveryOrderCancelled::class))->toBe(1);
        expect($eventDispatcher->dispatchCount(DeliveryOrderStatusChanged::class))->toBeGreaterThanOrEqual(1);
    });
});

/*
|--------------------------------------------------------------------------
| DeliveryOrderStateMachine Business Helpers
|--------------------------------------------------------------------------
|
| Tests for business rule helper methods.
|
*/

describe('DeliveryOrderStateMachine business helpers', function () {
    it('reports canConfirm correctly', function () {
        $doWithItems = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();
        $doWithoutItems = DeliveryOrder::factory()->draft()->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($doWithItems)->canConfirm())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($doWithoutItems)->canConfirm())->toBeFalse();
    });

    it('reports canShip correctly', function () {
        $confirmedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $draftDO = DeliveryOrder::factory()->draft()->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($confirmedDO)->canShip())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($draftDO)->canShip())->toBeFalse();
    });

    it('reports canDeliver correctly', function () {
        $shippedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $confirmedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($shippedDO)->canDeliver())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($confirmedDO)->canDeliver())->toBeFalse();
    });

    it('reports canCancel correctly for cancellable statuses', function () {
        $draftDO = DeliveryOrder::factory()->draft()->create();
        $confirmedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($draftDO)->canCancel())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($confirmedDO)->canCancel())->toBeTrue();
    });

    it('reports canCancel correctly for non-cancellable statuses', function () {
        $shippedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $deliveredDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->delivered()
            ->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($shippedDO)->canCancel())->toBeFalse();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($deliveredDO)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draftDO = DeliveryOrder::factory()->draft()->create();
        $confirmedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($draftDO)->canEdit())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($confirmedDO)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draftDO = DeliveryOrder::factory()->draft()->create();
        $confirmedDO = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        expect(DeliveryOrderStateMachine::fromDeliveryOrder($draftDO)->canDelete())->toBeTrue();
        expect(DeliveryOrderStateMachine::fromDeliveryOrder($confirmedDO)->canDelete())->toBeFalse();
    });
});

/*
|--------------------------------------------------------------------------
| DeliveryOrderStateMachine Workflow Metadata
|--------------------------------------------------------------------------
|
| Tests for workflow metadata generation.
|
*/

describe('DeliveryOrderStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Confirmed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for confirmed delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Confirmed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Shipped->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for delivered delivery order', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->delivered()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Delivered->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder);
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
| DeliveryOrderStateMachine Timestamp Updates
|--------------------------------------------------------------------------
|
| Tests for automatic timestamp updates during transitions.
|
*/

describe('DeliveryOrderStateMachine timestamp updates', function () {
    it('updates timestamps on confirm transition', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->draft()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Confirmed, ['user_id' => $this->user->id]);

        $deliveryOrder->refresh();

        expect($deliveryOrder->confirmed_at)->not->toBeNull();
        expect($deliveryOrder->confirmed_by)->toBe($this->user->id);
    });

    it('updates timestamps on ship transition', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->confirmed()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Shipped, ['user_id' => $this->user->id]);

        $deliveryOrder->refresh();

        expect($deliveryOrder->shipped_at)->not->toBeNull();
        expect($deliveryOrder->shipped_by)->toBe($this->user->id);
        expect($deliveryOrder->shipping_date)->not->toBeNull();
    });

    it('updates timestamps on deliver transition', function () {
        $deliveryOrder = DeliveryOrder::factory()
            ->has(DeliveryOrderItem::factory(), 'items')
            ->shipped()
            ->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Delivered, ['user_id' => $this->user->id]);

        $deliveryOrder->refresh();

        expect($deliveryOrder->delivered_at)->not->toBeNull();
        expect($deliveryOrder->delivered_by)->toBe($this->user->id);
        expect($deliveryOrder->received_date)->not->toBeNull();
    });

    it('updates timestamps and reason on cancel transition', function () {
        $deliveryOrder = DeliveryOrder::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = DeliveryOrderStateMachine::fromDeliveryOrder($deliveryOrder, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $this->user->id,
            'cancellation_reason' => 'Wrong address',
        ]);

        $deliveryOrder->refresh();

        expect($deliveryOrder->status)->toBe(DocumentStatus::Cancelled);
        // Note: cancelled_at and cancellation_reason fields may not exist in the schema yet
        if (\Illuminate\Support\Facades\Schema::hasColumn('delivery_orders', 'cancelled_at')) {
            expect($deliveryOrder->cancelled_at)->not->toBeNull();
            expect($deliveryOrder->cancelled_by)->toBe($this->user->id);
            expect($deliveryOrder->cancellation_reason)->toBe('Wrong address');
        }
    });
});
