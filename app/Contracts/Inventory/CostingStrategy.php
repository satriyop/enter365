<?php

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\Inventory\ProductStock;

/**
 * Strategy for inventory costing methods (Weighted Average, FIFO, Standard Cost).
 *
 * Implementations control how cost is tracked when stock enters/exits the system.
 * ProductStock remains the source of truth for quantity; strategies manage cost calculation.
 */
interface CostingStrategy
{
    /**
     * Record stock addition and update cost tracking.
     *
     * Called during stock-in operations (purchase, receiving, transfer in).
     * Updates ProductStock quantity and any strategy-specific records.
     */
    public function recordStockIn(ProductStock $stock, int $quantity, int $unitCost, ?string $referenceType = null, ?int $referenceId = null): void;

    /**
     * Record stock removal and return the unit cost to use for COGS.
     *
     * Called during stock-out operations (sale, delivery, transfer out).
     * Returns the calculated unit cost based on the costing method.
     */
    public function recordStockOut(ProductStock $stock, int $quantity): int;

    /**
     * Get the current unit cost for a product-warehouse combination.
     */
    public function getCurrentUnitCost(ProductStock $stock): int;
}
