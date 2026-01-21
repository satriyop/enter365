<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Domain\Inventory\Movements\MovementRecorder;
use App\Models\Inventory\InventoryMovement;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;

/**
 * Handles material consumption when a work order is completed.
 *
 * This handler:
 * - Issues remaining materials from inventory that weren't consumed during production
 * - Creates MaterialConsumption records for tracking
 * - Updates WorkOrderItem consumed quantities
 *
 * Priority: 10 (runs first to consume materials before costs are calculated)
 */
class MaterialConsumptionHandler implements CompletionHandlerInterface
{
    public function __construct(
        private MovementRecorder $movementRecorder
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        $materialItems = $workOrder->materialItems()
            ->with('product')
            ->get();

        foreach ($materialItems as $item) {
            $this->consumeRemainingMaterial($workOrder, $item, $userId);
        }
    }

    public function priority(): int
    {
        return 10;
    }

    public function shouldHandle(WorkOrder $workOrder): bool
    {
        // Only handle if this is a production work order with materials
        return $workOrder->isProduction()
            && $workOrder->warehouse_id !== null
            && $workOrder->materialItems()->exists();
    }

    private function consumeRemainingMaterial(
        WorkOrder $workOrder,
        WorkOrderItem $item,
        ?int $userId
    ): void {
        $remainingQty = $item->getRemainingQuantity();

        if ($remainingQty <= 0) {
            return;
        }

        // Issue from inventory
        $movement = $this->movementRecorder->recordIssue(
            productId: $item->product_id,
            warehouseId: $workOrder->warehouse_id,
            quantity: (int) ceil($remainingQty),
            type: InventoryMovement::TYPE_PRODUCTION,
            notes: "Konsumsi otomatis saat penyelesaian WO #{$workOrder->wo_number}",
            referenceType: WorkOrder::class,
            referenceId: $workOrder->id,
            userId: $userId
        );

        // Create consumption record
        MaterialConsumption::create([
            'work_order_id' => $workOrder->id,
            'work_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity_consumed' => $remainingQty,
            'quantity_scrapped' => 0,
            'unit' => $item->unit,
            'unit_cost' => $movement->unit_cost,
            'total_cost' => (int) round($remainingQty * $movement->unit_cost),
            'consumed_date' => now(),
            'consumed_by' => $userId,
            'notes' => 'Konsumsi otomatis saat penyelesaian work order',
        ]);

        // Update item consumed quantity
        $item->increment('quantity_consumed', $remainingQty);
    }
}
