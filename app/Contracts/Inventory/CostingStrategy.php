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
     * Record stock removal and return the exact integer total cost of the issuance.
     *
     * Callers must persist that total on the movement / COGS journal.
     * Never reconstruct it as quantity × a rounded unit cost — integer
     * division leaks sen out of Inventory on every uneven FIFO issue.
     */
    public function recordStockOut(ProductStock $stock, int $quantity): int;

    /**
     * Apply a signed quantity delta and return the signed inventory value change.
     *
     * Positive delta adds a layer / weighted-average receipt at $unitCost.
     * Negative delta consumes stock the same way as a stock-out.
     * Callers (stock opname, manual adjust) must not write ProductStock
     * quantity or FIFO layers themselves.
     */
    public function recordAdjustment(ProductStock $stock, int $delta, int $unitCost): int;

    /**
     * Get the current unit cost for a product-warehouse combination.
     */
    public function getCurrentUnitCost(ProductStock $stock): int;
}
