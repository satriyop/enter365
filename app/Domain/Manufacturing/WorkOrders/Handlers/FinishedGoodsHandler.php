<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Domain\Manufacturing\WorkOrders\WorkOrderDomainFactory;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\WorkOrder;

/**
 * Handles finished goods inventory when a work order is completed.
 *
 * This handler:
 * - Receives finished goods via the inventory stock-mutation seam
 * - Calculates unit cost based on actual production costs
 *
 * Priority: 20 (runs after materials are consumed, before cost calculation)
 */
class FinishedGoodsHandler implements CompletionHandlerInterface
{
    public function __construct(
        private InventoryServiceInterface $inventoryService,
        private WorkOrderDomainFactory $domainFactory,
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        $quantityProduced = $this->getQuantityProduced($workOrder);

        if ($quantityProduced <= 0) {
            return;
        }

        $product = $workOrder->product ?? Product::query()->find($workOrder->product_id);
        $warehouse = $workOrder->warehouse ?? Warehouse::query()->find($workOrder->warehouse_id);

        if ($product === null || $warehouse === null) {
            return;
        }

        $unitCost = $this->calculateUnitCost($workOrder, $quantityProduced);

        $this->inventoryService->stockIn(
            $product,
            $warehouse,
            $quantityProduced,
            $unitCost,
            "Hasil produksi WO #{$workOrder->wo_number}",
            WorkOrder::class,
            $workOrder->id,
            InventoryMovement::TYPE_PRODUCTION,
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
        $this->domainFactory->applyActualCosts($workOrder);

        $totalCost = $workOrder->actual_total_cost ?? $workOrder->estimated_total_cost ?? 0;

        if ($quantityProduced <= 0) {
            return 0;
        }

        return (int) round($totalCost / $quantityProduced);
    }
}
