<?php

declare(strict_types=1);

namespace App\Services\Inventory\Costing;

use App\Contracts\Inventory\CostingStrategy;
use App\Exceptions\Domain\BusinessRuleException;
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
    public function recordStockIn(ProductStock $stock, int $quantity, int $unitCost, ?string $referenceType = null, ?int $referenceId = null, ?int $totalCost = null): void
    {
        if ($quantity <= 0) {
            return;
        }

        $exactTotal = $totalCost ?? ($quantity * $unitCost);
        $this->writeLayersForTotal($stock, $quantity, $exactTotal, $referenceType, $referenceId);
        $stock->addStock($quantity, $unitCost, $exactTotal);
    }

    /**
     * Persist FIFO layers whose qty × unit_cost sums to $totalCost.
     *
     * Integer unit costs cannot represent an uneven total in one layer
     * (3 × 333 = 999, not 1000). Put the remainder on the last unit.
     */
    private function writeLayersForTotal(
        ProductStock $stock,
        int $quantity,
        int $totalCost,
        ?string $referenceType,
        ?int $referenceId,
    ): void {
        $baseUnit = intdiv($totalCost, $quantity);
        $lastUnit = $totalCost - ($baseUnit * ($quantity - 1));

        if ($quantity === 1 || $lastUnit === $baseUnit) {
            $this->createLayer($stock, $quantity, $baseUnit, $referenceType, $referenceId);

            return;
        }

        $this->createLayer($stock, $quantity - 1, $baseUnit, $referenceType, $referenceId);
        $this->createLayer($stock, 1, $lastUnit, $referenceType, $referenceId);
    }

    private function createLayer(
        ProductStock $stock,
        int $quantity,
        int $unitCost,
        ?string $referenceType,
        ?int $referenceId,
    ): void {
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
            throw BusinessRuleException::operationNotAllowed(
                'pengeluaran stok FIFO',
                'Lapisan biaya FIFO tidak mencukupi kuantitas stok. Periksa penyesuaian stok sebelumnya.'
            );
        }

        // Update ProductStock
        $stock->removeStock($quantity);

        return $totalCost;
    }

    public function recordAdjustment(ProductStock $stock, int $delta, int $unitCost): int
    {
        if ($delta > 0) {
            $this->recordStockIn($stock, $delta, $unitCost);

            return $delta * $unitCost;
        }

        if ($delta < 0) {
            return -$this->recordStockOut($stock, abs($delta));
        }

        $layers = InventoryCostLayer::query()
            ->where('product_id', $stock->product_id)
            ->where('warehouse_id', $stock->warehouse_id)
            ->where('quantity', '>', 0)
            ->lockForUpdate()
            ->get();

        $oldValue = 0;
        foreach ($layers as $layer) {
            $oldValue += $layer->quantity * $layer->unit_cost;
            $layer->unit_cost = $unitCost;
            $layer->save();
        }

        $stock->average_cost = $unitCost;
        $stock->total_value = $stock->quantity * $unitCost;
        $stock->save();

        return $stock->total_value - $oldValue;
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
