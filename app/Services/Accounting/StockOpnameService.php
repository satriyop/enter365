<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Product;
use App\Models\Accounting\ProductStock;
use App\Models\Accounting\StockOpname;
use App\Models\Accounting\StockOpnameItem;
use App\Models\Accounting\Warehouse;
use Illuminate\Support\Facades\DB;

class StockOpnameService
{
    public function __construct(
        private InventoryService $inventoryService
    ) {}

    /**
     * Create a new stock opname.
     */
    public function create(array $data): StockOpname
    {
        return DB::transaction(function () use ($data) {
            $opname = StockOpname::create([
                'opname_number' => StockOpname::generateOpnameNumber(),
                'warehouse_id' => $data['warehouse_id'],
                'opname_date' => $data['opname_date'] ?? now()->toDateString(),
                'status' => StockOpname::STATUS_DRAFT,
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? auth()->id(),
            ]);

            return $opname;
        });
    }

    /**
     * Update a stock opname.
     */
    public function update(StockOpname $opname, array $data): StockOpname
    {
        if (! $opname->canEdit()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat diubah pada status ini.');
        }

        $opname->update([
            'name' => $data['name'] ?? $opname->name,
            'notes' => $data['notes'] ?? $opname->notes,
            'opname_date' => $data['opname_date'] ?? $opname->opname_date,
        ]);

        return $opname->fresh();
    }

    /**
     * Delete a stock opname.
     */
    public function delete(StockOpname $opname): void
    {
        if (! $opname->canDelete()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat dihapus pada status ini.');
        }

        $opname->items()->delete();
        $opname->delete();
    }

    /**
     * Generate items from warehouse stock.
     */
    public function generateItems(StockOpname $opname): StockOpname
    {
        if (! $opname->isDraft()) {
            throw new \InvalidArgumentException('Item hanya dapat di-generate pada status draft.');
        }

        return DB::transaction(function () use ($opname) {
            // Clear existing items
            $opname->items()->delete();

            // Get all products with stock in this warehouse
            $stocks = ProductStock::where('warehouse_id', $opname->warehouse_id)
                ->where('quantity', '>', 0)
                ->with('product')
                ->get();

            foreach ($stocks as $stock) {
                // Only include products that track inventory
                if (! $stock->product || ! $stock->product->track_inventory) {
                    continue;
                }

                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $stock->product_id,
                    'system_quantity' => $stock->quantity,
                    'system_cost' => $stock->average_cost,
                    'system_value' => $stock->quantity * $stock->average_cost,
                ]);
            }

            $opname->updateTotals();

            return $opname->fresh(['items']);
        });
    }

    /**
     * Add an item manually.
     */
    public function addItem(StockOpname $opname, array $data): StockOpnameItem
    {
        if (! $opname->canEdit()) {
            throw new \InvalidArgumentException('Item tidak dapat ditambahkan pada status ini.');
        }

        $product = Product::findOrFail($data['product_id']);

        if (! $product->track_inventory) {
            throw new \InvalidArgumentException('Produk ini tidak melacak inventori.');
        }

        // Check if product already exists
        $existing = $opname->items()->where('product_id', $product->id)->first();
        if ($existing) {
            throw new \InvalidArgumentException('Produk sudah ada dalam stock opname ini.');
        }

        // Get current stock
        $stock = ProductStock::where('warehouse_id', $opname->warehouse_id)
            ->where('product_id', $product->id)
            ->first();

        $systemQty = $stock ? $stock->quantity : 0;
        $systemCost = $stock ? $stock->average_cost : $product->purchase_price;

        $item = StockOpnameItem::create([
            'stock_opname_id' => $opname->id,
            'product_id' => $product->id,
            'system_quantity' => $systemQty,
            'system_cost' => $systemCost,
            'system_value' => $systemQty * $systemCost,
        ]);

        $opname->updateTotals();

        return $item;
    }

    /**
     * Update an item (record count).
     */
    public function updateItem(StockOpnameItem $item, array $data): StockOpnameItem
    {
        $opname = $item->stockOpname;

        if (! in_array($opname->status, [StockOpname::STATUS_DRAFT, StockOpname::STATUS_COUNTING])) {
            throw new \InvalidArgumentException('Item tidak dapat diubah pada status ini.');
        }

        if (isset($data['counted_quantity'])) {
            $item->recordCount(
                (int) $data['counted_quantity'],
                $data['notes'] ?? null
            );
        } else {
            $item->update([
                'notes' => $data['notes'] ?? $item->notes,
            ]);
        }

        return $item->fresh();
    }

    /**
     * Remove an item.
     */
    public function removeItem(StockOpnameItem $item): void
    {
        $opname = $item->stockOpname;

        if (! $opname->canEdit()) {
            throw new \InvalidArgumentException('Item tidak dapat dihapus pada status ini.');
        }

        $item->delete();
        $opname->updateTotals();
    }

    /**
     * Start counting workflow.
     */
    public function startCounting(StockOpname $opname, int $userId): StockOpname
    {
        if (! $opname->canStartCounting()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat memulai penghitungan. Pastikan ada item yang akan dihitung.');
        }

        // Refresh system quantities before starting
        return DB::transaction(function () use ($opname, $userId) {
            foreach ($opname->items as $item) {
                $stock = ProductStock::where('warehouse_id', $opname->warehouse_id)
                    ->where('product_id', $item->product_id)
                    ->first();

                if ($stock) {
                    $item->captureSystemQuantities($stock);
                }
            }

            $opname->update([
                'status' => StockOpname::STATUS_COUNTING,
                'counted_by' => $userId,
                'counting_started_at' => now(),
            ]);

            $opname->updateTotals();

            return $opname->fresh();
        });
    }

    /**
     * Submit for review.
     */
    public function submitForReview(StockOpname $opname, int $userId): StockOpname
    {
        if (! $opname->canSubmitForReview()) {
            $uncounted = $opname->items()->whereNull('counted_quantity')->count();
            if ($uncounted > 0) {
                throw new \InvalidArgumentException("Masih ada {$uncounted} item yang belum dihitung.");
            }
            throw new \InvalidArgumentException('Stock opname tidak dapat disubmit untuk review.');
        }

        $opname->update([
            'status' => StockOpname::STATUS_REVIEWED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        return $opname->fresh();
    }

    /**
     * Approve and apply adjustments.
     */
    public function approve(StockOpname $opname, int $userId): StockOpname
    {
        if (! $opname->canApprove()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat diapprove pada status ini.');
        }

        return DB::transaction(function () use ($opname, $userId) {
            $warehouse = Warehouse::findOrFail($opname->warehouse_id);

            // Apply adjustments for items with variance
            foreach ($opname->items()->where('variance_quantity', '!=', 0)->get() as $item) {
                $product = $item->product;

                // Create inventory adjustment
                $this->inventoryService->adjust(
                    $product,
                    $warehouse,
                    $item->counted_quantity,
                    null, // newUnitCost - keep existing
                    "Stock Opname: {$opname->opname_number}".($item->notes ? " - {$item->notes}" : ''),
                    StockOpname::class,
                    $opname->id
                );
            }

            $opname->update([
                'status' => StockOpname::STATUS_COMPLETED,
                'approved_by' => $userId,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);

            return $opname->fresh();
        });
    }

    /**
     * Reject and return to counting.
     */
    public function reject(StockOpname $opname, int $userId, ?string $reason = null): StockOpname
    {
        if (! $opname->canReject()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat direject pada status ini.');
        }

        $opname->update([
            'status' => StockOpname::STATUS_COUNTING,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'notes' => $reason ? ($opname->notes ? $opname->notes."\n\nDitolak: ".$reason : 'Ditolak: '.$reason) : $opname->notes,
        ]);

        return $opname->fresh();
    }

    /**
     * Cancel stock opname.
     */
    public function cancel(StockOpname $opname, int $userId): StockOpname
    {
        if (! $opname->canCancel()) {
            throw new \InvalidArgumentException('Stock opname tidak dapat dibatalkan pada status ini.');
        }

        $opname->update([
            'status' => StockOpname::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        return $opname->fresh();
    }

    /**
     * Get variance report.
     */
    public function getVarianceReport(StockOpname $opname): array
    {
        $items = $opname->items()->with('product')->get();

        $summary = [
            'total_items' => $items->count(),
            'counted_items' => $items->whereNotNull('counted_quantity')->count(),
            'items_with_variance' => $items->where('variance_quantity', '!=', 0)->count(),
            'items_with_surplus' => $items->where('variance_quantity', '>', 0)->count(),
            'items_with_shortage' => $items->where('variance_quantity', '<', 0)->count(),
            'total_system_value' => $items->sum('system_value'),
            'total_counted_value' => $items->sum(fn ($item) => ($item->counted_quantity ?? 0) * $item->system_cost),
            'total_variance_qty' => $items->sum('variance_quantity'),
            'total_variance_value' => $items->sum('variance_value'),
        ];

        $variances = $items->where('variance_quantity', '!=', 0)
            ->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_sku' => $item->product->sku,
                'product_name' => $item->product->name,
                'system_quantity' => $item->system_quantity,
                'counted_quantity' => $item->counted_quantity,
                'variance_quantity' => $item->variance_quantity,
                'variance_percentage' => $item->getVariancePercentage(),
                'system_cost' => $item->system_cost,
                'variance_value' => $item->variance_value,
                'notes' => $item->notes,
            ])
            ->values()
            ->toArray();

        return [
            'stock_opname' => [
                'id' => $opname->id,
                'opname_number' => $opname->opname_number,
                'warehouse' => $opname->warehouse->name,
                'opname_date' => $opname->opname_date->toDateString(),
                'status' => $opname->status,
            ],
            'summary' => $summary,
            'variances' => $variances,
        ];
    }
}
