<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

/**
 * Value object representing work order cost breakdown.
 *
 * Immutable DTO containing material, labor, overhead and total costs.
 * Used for both estimated and actual cost calculations.
 */
final readonly class WorkOrderCosts
{
    public int $totalCost;

    public function __construct(
        public int $materialCost,
        public int $laborCost,
        public int $overheadCost,
    ) {
        $this->totalCost = $materialCost + $laborCost + $overheadCost;
    }

    /**
     * Calculate cost variance from another WorkOrderCosts.
     *
     * Positive = under budget, Negative = over budget.
     */
    public function varianceFrom(WorkOrderCosts $other): int
    {
        return $other->totalCost - $this->totalCost;
    }

    /**
     * Calculate variance percentage.
     *
     * @return float Percentage variance (positive = under budget)
     */
    public function variancePercentageFrom(WorkOrderCosts $other): float
    {
        if ($other->totalCost === 0) {
            return 0.0;
        }

        return round((($other->totalCost - $this->totalCost) / $other->totalCost) * 100, 2);
    }
}
