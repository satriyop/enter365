<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\InventoryMovement;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record stock in (purchase/receiving).
     */
    public function stockIn(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        int $unitCost,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $warehouse, $quantity, $unitCost, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::getOrCreate($product, $warehouse);
            $quantityBefore = $stock->quantity;

            // Add stock with weighted average cost
            $stock->addStock($quantity, $unitCost);

            // Create movement record
            $movement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_IN),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => $quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'movement_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            return $movement;
        });
    }

    /**
     * Record stock out (sale/delivery).
     */
    public function stockOut(
        Product $product,
        Warehouse $warehouse,
        int $quantity,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $warehouse, $quantity, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::getOrCreate($product, $warehouse);
            $quantityBefore = $stock->quantity;

            // Get unit cost before removing
            $unitCost = $stock->average_cost;

            // Remove stock
            $stock->removeStock($quantity);

            // Create movement record
            $movement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_OUT),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => -$quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $stock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'movement_date' => now(),
                'notes' => $notes,
                'created_by' => auth()->id(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            return $movement;
        });
    }

    /**
     * Record stock adjustment.
     */
    public function adjust(
        Product $product,
        Warehouse $warehouse,
        int $newQuantity,
        ?int $newUnitCost = null,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): InventoryMovement {
        return DB::transaction(function () use ($product, $warehouse, $newQuantity, $newUnitCost, $notes, $referenceType, $referenceId) {
            $stock = ProductStock::getOrCreate($product, $warehouse);
            $quantityBefore = $stock->quantity;
            $quantityDiff = $newQuantity - $quantityBefore;

            // Update stock
            $stock->quantity = $newQuantity;
            if ($newUnitCost !== null) {
                $stock->average_cost = $newUnitCost;
            }
            $stock->total_value = $stock->quantity * $stock->average_cost;
            $stock->save();

            // Create movement record
            $movement = InventoryMovement::create([
                'movement_number' => InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_ADJUSTMENT),
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => $quantityDiff,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $newQuantity,
                'unit_cost' => $stock->average_cost,
                'total_cost' => abs($quantityDiff) * $stock->average_cost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'movement_date' => now(),
                'notes' => $notes ?? 'Penyesuaian stok',
                'created_by' => auth()->id(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            return $movement;
        });
    }

    /**
     * Transfer stock between warehouses.
     *
     * @return array{out: InventoryMovement, in: InventoryMovement}
     */
    public function transfer(
        Product $product,
        Warehouse $fromWarehouse,
        Warehouse $toWarehouse,
        int $quantity,
        ?string $notes = null
    ): array {
        return DB::transaction(function () use ($product, $fromWarehouse, $toWarehouse, $quantity, $notes) {
            $fromStock = ProductStock::getOrCreate($product, $fromWarehouse);

            if ($fromStock->quantity < $quantity) {
                throw new \InvalidArgumentException('Stok tidak mencukupi untuk transfer.');
            }

            $unitCost = $fromStock->average_cost;
            $fromQuantityBefore = $fromStock->quantity;

            // Remove from source warehouse
            $fromStock->removeStock($quantity);

            // Add to destination warehouse
            $toStock = ProductStock::getOrCreate($product, $toWarehouse);
            $toQuantityBefore = $toStock->quantity;
            $toStock->addStock($quantity, $unitCost);

            $transferNumber = InventoryMovement::generateMovementNumber(InventoryMovement::TYPE_TRANSFER_OUT);

            // Create outgoing movement
            $outMovement = InventoryMovement::create([
                'movement_number' => $transferNumber,
                'product_id' => $product->id,
                'warehouse_id' => $fromWarehouse->id,
                'type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'quantity' => -$quantity,
                'quantity_before' => $fromQuantityBefore,
                'quantity_after' => $fromStock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'transfer_warehouse_id' => $toWarehouse->id,
                'movement_date' => now(),
                'notes' => $notes ?? "Transfer ke {$toWarehouse->name}",
                'created_by' => auth()->id(),
            ]);

            // Create incoming movement
            $inMovement = InventoryMovement::create([
                'movement_number' => str_replace('TRF', 'TRI', $transferNumber),
                'product_id' => $product->id,
                'warehouse_id' => $toWarehouse->id,
                'type' => InventoryMovement::TYPE_TRANSFER_IN,
                'quantity' => $quantity,
                'quantity_before' => $toQuantityBefore,
                'quantity_after' => $toStock->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $quantity * $unitCost,
                'transfer_warehouse_id' => $fromWarehouse->id,
                'movement_date' => now(),
                'notes' => $notes ?? "Transfer dari {$fromWarehouse->name}",
                'created_by' => auth()->id(),
            ]);

            // Sync product's current_stock
            $product->syncCurrentStock();

            return ['out' => $outMovement, 'in' => $inMovement];
        });
    }

    /**
     * Process inventory for a posted invoice (stock out).
     */
    public function processInvoice(Invoice $invoice, Warehouse $warehouse): void
    {
        foreach ($invoice->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            $this->stockOut(
                $item->product,
                $warehouse,
                (int) $item->quantity,
                "Penjualan: {$invoice->invoice_number}",
                Invoice::class,
                $invoice->id
            );
        }
    }

    /**
     * Process inventory for a posted bill (stock in).
     */
    public function processBill(Bill $bill, Warehouse $warehouse): void
    {
        foreach ($bill->items as $item) {
            if (! $item->product || ! $item->product->track_inventory) {
                continue;
            }

            $this->stockIn(
                $item->product,
                $warehouse,
                (int) $item->quantity,
                $item->unit_price,
                "Pembelian: {$bill->bill_number}",
                Bill::class,
                $bill->id
            );
        }
    }

    /**
     * Get stock card (kartu stok) for a product.
     *
     * @return Collection<int, InventoryMovement>
     */
    public function getStockCard(
        Product $product,
        ?Warehouse $warehouse = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): Collection {
        $query = InventoryMovement::query()
            ->where('product_id', $product->id)
            ->with(['warehouse', 'createdByUser']);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        if ($startDate) {
            $query->where('movement_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('movement_date', '<=', $endDate);
        }

        return $query->orderBy('movement_date')->orderBy('id')->get();
    }

    /**
     * Get stock valuation report.
     *
     * @return Collection<int, object>
     */
    public function getStockValuation(?Warehouse $warehouse = null): Collection
    {
        $query = ProductStock::query()
            ->with(['product', 'warehouse'])
            ->where('quantity', '>', 0);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        return $query->get()->map(fn ($stock) => (object) [
            'product_id' => $stock->product_id,
            'product_sku' => $stock->product->sku,
            'product_name' => $stock->product->name,
            'warehouse_id' => $stock->warehouse_id,
            'warehouse_name' => $stock->warehouse->name,
            'quantity' => $stock->quantity,
            'average_cost' => $stock->average_cost,
            'total_value' => $stock->total_value,
        ]);
    }

    /**
     * Get inventory summary.
     */
    public function getInventorySummary(?Warehouse $warehouse = null): array
    {
        $query = ProductStock::query();

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $totalValue = $query->sum('total_value');
        $totalItems = $query->count();
        $totalQuantity = $query->sum('quantity');

        $lowStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->whereColumn('current_stock', '<=', 'min_stock')
            ->count();

        $outOfStockCount = Product::query()
            ->where('track_inventory', true)
            ->where('is_active', true)
            ->where('current_stock', '<=', 0)
            ->count();

        return [
            'total_value' => $totalValue,
            'total_items' => $totalItems,
            'total_quantity' => $totalQuantity,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
        ];
    }

    /**
     * Get movement summary for a period.
     */
    public function getMovementSummary(string $startDate, string $endDate, ?Warehouse $warehouse = null): array
    {
        $query = InventoryMovement::query()
            ->whereBetween('movement_date', [$startDate, $endDate]);

        if ($warehouse) {
            $query->where('warehouse_id', $warehouse->id);
        }

        $movements = $query->get();

        return [
            'period' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'stock_in' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_IN)->count(),
                'quantity' => $movements->where('type', InventoryMovement::TYPE_IN)->sum('quantity'),
                'value' => $movements->where('type', InventoryMovement::TYPE_IN)->sum('total_cost'),
            ],
            'stock_out' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_OUT)->count(),
                'quantity' => abs($movements->where('type', InventoryMovement::TYPE_OUT)->sum('quantity')),
                'value' => $movements->where('type', InventoryMovement::TYPE_OUT)->sum('total_cost'),
            ],
            'adjustments' => [
                'count' => $movements->where('type', InventoryMovement::TYPE_ADJUSTMENT)->count(),
            ],
            'transfers' => [
                'count' => $movements->whereIn('type', [
                    InventoryMovement::TYPE_TRANSFER_IN,
                    InventoryMovement::TYPE_TRANSFER_OUT,
                ])->count() / 2, // Divide by 2 because each transfer creates 2 records
            ],
        ];
    }
}
