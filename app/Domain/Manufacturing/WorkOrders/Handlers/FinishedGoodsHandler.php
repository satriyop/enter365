<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Domain\Inventory\Movements\MovementRecorder;
use App\Models\Inventory\InventoryMovement;
use App\Models\Manufacturing\WorkOrder;

/**
 * Handles finished goods inventory when a work order is completed.
 *
 * This handler:
 * - Receives finished goods into inventory
 * - Calculates unit cost based on actual production costs
 * - Creates inventory movement records
 *
 * Priority: 20 (runs after materials are consumed, before cost calculation)
 */
class FinishedGoodsHandler implements CompletionHandlerInterface
{
    public function __construct(
        private MovementRecorder $movementRecorder
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        $quantityProduced = $this->getQuantityProduced($workOrder);

        if ($quantityProduced <= 0) {
            return;
        }

        $unitCost = $this->calculateUnitCost($workOrder, $quantityProduced);

        $this->movementRecorder->recordReceipt(
            productId: $workOrder->product_id,
            warehouseId: $workOrder->warehouse_id,
            quantity: $quantityProduced,
            unitCost: $unitCost,
            type: InventoryMovement::TYPE_PRODUCTION,
            notes: "Hasil produksi WO #{$workOrder->wo_number}",
            referenceType: WorkOrder::class,
            referenceId: $workOrder->id,
            userId: $userId
        );
    }

    public function priority(): int
    {
        return 20;
    }

    public function shouldHandle(WorkOrder $workOrder): bool
    {
        // Only handle production work orders with a product and warehouse
        return $workOrder->isProduction()
            && $workOrder->product_id !== null
            && $workOrder->warehouse_id !== null;
    }

    private function getQuantityProduced(WorkOrder $workOrder): int
    {
        // Use completed quantity, fall back to ordered if not specified
        $quantity = $workOrder->quantity_completed > 0
            ? $workOrder->quantity_completed
            : $workOrder->quantity_ordered;

        // Subtract scrapped quantity
        $netQuantity = (float) $quantity - (float) $workOrder->quantity_scrapped;

        return max(0, (int) floor($netQuantity));
    }

    private function calculateUnitCost(WorkOrder $workOrder, int $quantityProduced): int
    {
        // Refresh to get latest actual costs (after material consumption)
        $workOrder->refresh();
        $workOrder->calculateActualCosts();

        $totalCost = $workOrder->actual_total_cost ?? $workOrder->estimated_total_cost ?? 0;

        if ($quantityProduced <= 0) {
            return 0;
        }

        return (int) round($totalCost / $quantityProduced);
    }
}
