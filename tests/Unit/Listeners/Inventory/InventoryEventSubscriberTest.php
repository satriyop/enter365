<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Listeners\Inventory\InventoryEventSubscriber;
use Illuminate\Events\Dispatcher;

describe('InventoryEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new InventoryEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all inventory movement events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(InventoryReceived::class)
                ->and($subscriptions)->toHaveKey(InventoryIssued::class)
                ->and($subscriptions)->toHaveKey(InventoryTransferred::class)
                ->and($subscriptions)->toHaveKey(InventoryAdjusted::class);
        });
    });

    describe('handleReceived', function () {
        it('logs inventory received with correct context', function () {
            $event = new InventoryReceived(
                productId: 1,
                warehouseId: 2,
                quantity: 100.0,
                movementId: 10,
                userId: 5,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('inventory.received', [
                    'product_id' => 1,
                    'warehouse_id' => 2,
                    'quantity' => 100.0,
                    'movement_id' => 10,
                    'user_id' => 5,
                ]);

            $this->subscriber->handleReceived($event);
        });
    });

    describe('handleIssued', function () {
        it('logs inventory issued with correct context', function () {
            $event = new InventoryIssued(
                productId: 3,
                warehouseId: 1,
                quantity: 25.5,
                movementId: 11,
                userId: 7,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('inventory.issued', [
                    'product_id' => 3,
                    'warehouse_id' => 1,
                    'quantity' => 25.5,
                    'movement_id' => 11,
                    'user_id' => 7,
                ]);

            $this->subscriber->handleIssued($event);
        });
    });

    describe('handleTransferred', function () {
        it('logs inventory transfer with correct context', function () {
            $event = new InventoryTransferred(
                productId: 5,
                fromWarehouseId: 1,
                toWarehouseId: 2,
                quantity: 50.0,
                outMovementId: 20,
                inMovementId: 21,
                userId: 8,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('inventory.transferred', [
                    'product_id' => 5,
                    'from_warehouse_id' => 1,
                    'to_warehouse_id' => 2,
                    'quantity' => 50.0,
                    'out_movement_id' => 20,
                    'in_movement_id' => 21,
                    'user_id' => 8,
                ]);

            $this->subscriber->handleTransferred($event);
        });
    });

    describe('handleAdjusted', function () {
        it('logs inventory adjustment with correct context', function () {
            $event = new InventoryAdjusted(
                productId: 2,
                warehouseId: 3,
                adjustmentQuantity: -5.0,
                previousQuantity: 100.0,
                newQuantity: 95.0,
                movementId: 30,
                userId: 4,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('inventory.adjusted', [
                    'product_id' => 2,
                    'warehouse_id' => 3,
                    'adjustment_quantity' => -5.0,
                    'previous_quantity' => 100.0,
                    'new_quantity' => 95.0,
                    'movement_id' => 30,
                    'user_id' => 4,
                ]);

            $this->subscriber->handleAdjusted($event);
        });
    });
});
