<?php

declare(strict_types=1);

namespace App\Services\Inventory\Costing;

use App\Contracts\Inventory\CostingStrategy;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\ProductStock;

/**
 * First-In, First-Out (FIFO) costing method.
 *
 * Each stock-in creates a new cost layer. Stock-out consumes
 * the oldest layers first. Required for SAK EMKM compliance
 * in certain Indonesian industries.
 */
class FIFOCostingStrategy implements CostingStrategy
{
    public function recordStockIn(ProductStock $stock, int $quantity, int $unitCost, ?string $referenceType = null, ?int $referenceId = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        // Create a new cost layer for this batch
        InventoryCostLayer::create([
            'product_id' => $stock->product_id,
            'warehouse_id' => $stock->warehouse_id,
            'quantity' => $quantity,
            'original_quantity' => $quantity,
            'unit_cost' => $unitCost,
            'received_date' => now()->toDateString(),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);

        // Update ProductStock quantity and recalculate average cost for reporting
        $stock->addStock($quantity, $unitCost);
    }

    public function recordStockOut(ProductStock $stock, int $quantity): int
    {
        if ($quantity <= 0) {
            return 0;
        }

        // Consume oldest layers first (FIFO)
        $layers = InventoryCostLayer::where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->where('quantity', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $totalCost = 0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $consumed = $layer->consume($remaining);
            $totalCost += $consumed * $layer->unit_cost;
            $remaining -= $consumed;
        }

        if ($remaining > 0) {
            // This shouldn't happen if stock validation passed, but handle gracefully
            // Use the last known average cost for the remaining quantity
            $totalCost += $remaining * $stock->average_cost;
        }

        // Update ProductStock
        $stock->removeStock($quantity);

        // Return weighted average FIFO cost for this issuance
        return (int) round($totalCost / $quantity);
    }

    public function getCurrentUnitCost(ProductStock $stock): int
    {
        // Return cost of oldest available layer
        $oldestLayer = InventoryCostLayer::where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->where('quantity', '>', 0)
            ->orderBy('received_date')
            ->orderBy('id')
            ->first();

        return $oldestLayer ? $oldestLayer->unit_cost : $stock->average_cost;
    }
}
