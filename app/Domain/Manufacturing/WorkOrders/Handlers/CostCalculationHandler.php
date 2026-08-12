<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders\Handlers;

use App\Domain\Manufacturing\WorkOrders\WorkOrderDomainFactory;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;

/**
 * Handles final cost calculation when a work order is completed.
 *
 * This handler:
 * - Recalculates actual costs from all consumption records
 * - Updates work order item costs
 * - Calculates cost variance (estimated vs actual)
 *
 * Priority: 30 (runs last, after all materials are consumed and goods produced)
 */
class CostCalculationHandler implements CompletionHandlerInterface
{
    public function __construct(
        private WorkOrderDomainFactory $domainFactory,
    ) {}

    public function handle(WorkOrder $workOrder, ?int $userId = null): void
    {
        // Recalculate costs for each item from consumption records
        $this->recalculateItemCosts($workOrder);

        // Recalculate work order totals
        $this->domainFactory->applyActualCosts($workOrder);
        $workOrder->save();
    }

    public function priority(): int
    {
        return 30;
    }

    public function shouldHandle(WorkOrder $workOrder): bool
    {
        // Always calculate costs for production work orders
        return $workOrder->isProduction();
    }

    private function recalculateItemCosts(WorkOrder $workOrder): void
    {
        // Recalculate material item costs from consumption records
        $workOrder->materialItems()->each(function (WorkOrderItem $item) {
            $item->calculateActualCost();
            $item->save();
        });

        // For labor and overhead, use actual values if recorded, else estimated
        $workOrder->laborItems()->each(function (WorkOrderItem $item) {
            $this->finalizeNonMaterialItemCost($item);
        });

        $workOrder->overheadItems()->each(function (WorkOrderItem $item) {
            $this->finalizeNonMaterialItemCost($item);
        });
    }

    private function finalizeNonMaterialItemCost(WorkOrderItem $item): void
    {
        // If no actual cost recorded, use estimated cost
        if ($item->total_actual_cost <= 0) {
            $item->total_actual_cost = $item->total_estimated_cost;
            $item->actual_unit_cost = $item->unit_cost;
            $item->save();
        }
    }
}
