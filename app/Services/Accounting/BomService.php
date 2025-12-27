<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\Product;
use Illuminate\Support\Facades\DB;

class BomService
{
    /**
     * Create a new BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bom
    {
        return DB::transaction(function () use ($data) {
            $bom = new Bom($data);
            $bom->bom_number = Bom::generateBomNumber();
            $bom->save();

            // Create items
            if (! empty($data['items'])) {
                $sortOrder = 0;
                foreach ($data['items'] as $itemData) {
                    $itemData['sort_order'] = $sortOrder++;
                    $this->createItem($bom, $itemData);
                }

                // Recalculate totals
                $bom->calculateTotals();
                $bom->save();
            }

            return $bom->fresh(['items', 'product']);
        });
    }

    /**
     * Update a BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Bom $bom, array $data): Bom
    {
        if (! $bom->canBeEdited()) {
            throw new \InvalidArgumentException('BOM can only be edited in draft status.');
        }

        return DB::transaction(function () use ($bom, $data) {
            $bom->fill($data);
            $bom->save();

            // Update items if provided
            if (isset($data['items'])) {
                // Remove existing items and recreate
                $bom->items()->delete();

                $sortOrder = 0;
                foreach ($data['items'] as $itemData) {
                    $itemData['sort_order'] = $sortOrder++;
                    $this->createItem($bom, $itemData);
                }

                // Recalculate totals
                $bom->calculateTotals();
                $bom->save();
            }

            return $bom->fresh(['items', 'product']);
        });
    }

    /**
     * Delete a BOM.
     */
    public function delete(Bom $bom): bool
    {
        if (! $bom->canBeEdited()) {
            throw new \InvalidArgumentException('Only draft BOMs can be deleted.');
        }

        return DB::transaction(function () use ($bom) {
            $bom->items()->delete();

            return $bom->delete();
        });
    }

    /**
     * Activate a BOM.
     */
    public function activate(Bom $bom, ?int $userId = null): Bom
    {
        if (! $bom->canBeActivated()) {
            throw new \InvalidArgumentException('BOM cannot be activated. Ensure it has items and is in draft status.');
        }

        // Deactivate any existing active BOM for this product
        Bom::query()
            ->where('product_id', $bom->product_id)
            ->where('status', Bom::STATUS_ACTIVE)
            ->where('id', '!=', $bom->id)
            ->update(['status' => Bom::STATUS_INACTIVE]);

        $bom->status = Bom::STATUS_ACTIVE;
        $bom->approved_by = $userId;
        $bom->approved_at = now();
        $bom->save();

        return $bom->fresh();
    }

    /**
     * Deactivate a BOM.
     */
    public function deactivate(Bom $bom): Bom
    {
        if (! $bom->canBeDeactivated()) {
            throw new \InvalidArgumentException('Only active BOMs can be deactivated.');
        }

        $bom->status = Bom::STATUS_INACTIVE;
        $bom->save();

        return $bom->fresh();
    }

    /**
     * Duplicate a BOM.
     */
    public function duplicate(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            $newBom = $bom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
            $newBom->bom_number = Bom::generateBomNumber();
            $newBom->status = Bom::STATUS_DRAFT;
            $newBom->version = $this->getNextVersion($bom->version);
            $newBom->parent_bom_id = $bom->id;
            $newBom->save();

            // Duplicate items
            foreach ($bom->items as $item) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }

            return $newBom->fresh(['items', 'product']);
        });
    }

    /**
     * Get active BOM for a product.
     */
    public function getActiveForProduct(Product $product): ?Bom
    {
        return Bom::query()
            ->where('product_id', $product->id)
            ->where('status', Bom::STATUS_ACTIVE)
            ->with(['items'])
            ->first();
    }

    /**
     * Calculate cost for producing a quantity.
     *
     * @return array<string, mixed>
     */
    public function calculateProductionCost(Bom $bom, float $quantity): array
    {
        $multiplier = $quantity / (float) $bom->output_quantity;

        $materials = [];
        $totalMaterialCost = 0;

        foreach ($bom->materialItems as $item) {
            $effectiveQuantity = $item->getEffectiveQuantity() * $multiplier;
            $itemCost = (int) round($effectiveQuantity * $item->unit_cost);

            $materials[] = [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $effectiveQuantity,
                'unit' => $item->unit,
                'unit_cost' => $item->unit_cost,
                'total_cost' => $itemCost,
            ];

            $totalMaterialCost += $itemCost;
        }

        $laborCost = (int) round($bom->total_labor_cost * $multiplier);
        $overheadCost = (int) round($bom->total_overhead_cost * $multiplier);
        $totalCost = $totalMaterialCost + $laborCost + $overheadCost;

        return [
            'bom_id' => $bom->id,
            'bom_number' => $bom->bom_number,
            'product_id' => $bom->product_id,
            'quantity_to_produce' => $quantity,
            'materials' => $materials,
            'cost_breakdown' => [
                'material' => $totalMaterialCost,
                'labor' => $laborCost,
                'overhead' => $overheadCost,
            ],
            'total_cost' => $totalCost,
            'unit_cost' => $quantity > 0 ? (int) round($totalCost / $quantity) : 0,
        ];
    }

    /**
     * Get BOM statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'total_count' => Bom::count(),
            'draft_count' => Bom::where('status', Bom::STATUS_DRAFT)->count(),
            'active_count' => Bom::where('status', Bom::STATUS_ACTIVE)->count(),
            'inactive_count' => Bom::where('status', Bom::STATUS_INACTIVE)->count(),
            'products_with_bom' => Bom::where('status', Bom::STATUS_ACTIVE)
                ->distinct('product_id')
                ->count('product_id'),
            'avg_material_cost' => (int) Bom::where('status', Bom::STATUS_ACTIVE)
                ->avg('total_material_cost'),
            'avg_items_per_bom' => round(BomItem::count() / max(Bom::count(), 1), 1),
        ];
    }

    /**
     * Create a BOM item.
     *
     * @param  array<string, mixed>  $data
     */
    private function createItem(Bom $bom, array $data): BomItem
    {
        $item = new BomItem($data);
        $item->bom_id = $bom->id;
        $item->calculateTotalCost();
        $item->save();

        return $item;
    }

    /**
     * Get next version number.
     */
    private function getNextVersion(string $currentVersion): string
    {
        $parts = explode('.', $currentVersion);
        $major = (int) ($parts[0] ?? 1);
        $minor = (int) ($parts[1] ?? 0);

        return $major.'.'.($minor + 1);
    }
}
