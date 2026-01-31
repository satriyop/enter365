<?php

declare(strict_types=1);

namespace App\Listeners\Inventory;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use Illuminate\Events\Dispatcher;

/**
 * Handles all inventory movement-related events.
 */
class InventoryEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleReceived(InventoryReceived $event): void
    {
        $this->logger->logOperation('inventory.received', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'quantity' => $event->quantity,
            'movement_id' => $event->movementId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleIssued(InventoryIssued $event): void
    {
        $this->logger->logOperation('inventory.issued', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'quantity' => $event->quantity,
            'movement_id' => $event->movementId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleTransferred(InventoryTransferred $event): void
    {
        $this->logger->logOperation('inventory.transferred', [
            'product_id' => $event->productId,
            'from_warehouse_id' => $event->fromWarehouseId,
            'to_warehouse_id' => $event->toWarehouseId,
            'quantity' => $event->quantity,
            'out_movement_id' => $event->outMovementId,
            'in_movement_id' => $event->inMovementId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleAdjusted(InventoryAdjusted $event): void
    {
        $this->logger->logOperation('inventory.adjusted', [
            'product_id' => $event->productId,
            'warehouse_id' => $event->warehouseId,
            'adjustment_quantity' => $event->adjustmentQuantity,
            'previous_quantity' => $event->previousQuantity,
            'new_quantity' => $event->newQuantity,
            'movement_id' => $event->movementId,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            InventoryReceived::class => 'handleReceived',
            InventoryIssued::class => 'handleIssued',
            InventoryTransferred::class => 'handleTransferred',
            InventoryAdjusted::class => 'handleAdjusted',
        ];
    }
}
