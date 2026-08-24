<?php

declare(strict_types=1);

namespace App\Services\Inventory\Costing;

use App\Contracts\Inventory\CostingStrategy;
use App\Models\Inventory\ProductStock;

/**
 * Weighted Average costing method.
 *
 * Each stock-in recalculates the average cost across all units.
 * Stock-out uses the current average cost. This is the default method.
 */
class WeightedAverageCostingStrategy implements CostingStrategy
{
    public function recordStockIn(ProductStock $stock, int $quantity, int $unitCost, ?string $referenceType = null, ?int $referenceId = null): void
    {
        $stock->addStock($quantity, $unitCost);
    }

    public function recordStockOut(ProductStock $stock, int $quantity): int
    {
        $unitCost = $stock->average_cost;
        $stock->removeStock($quantity);

        return $unitCost;
    }

    public function recordAdjustment(ProductStock $stock, int $delta, int $unitCost): int
    {
        if ($delta > 0) {
            $this->recordStockIn($stock, $delta, $unitCost);

            return $delta * $unitCost;
        }

        if ($delta < 0) {
            $quantity = abs($delta);
            $outCost = $this->recordStockOut($stock, $quantity);

            return -($quantity * $outCost);
        }

        return 0;
    }

    public function getCurrentUnitCost(ProductStock $stock): int
    {
        return $stock->average_cost;
    }
}
