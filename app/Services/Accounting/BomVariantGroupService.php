<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use Illuminate\Support\Facades\DB;

class BomVariantGroupService
{
    /**
     * Create a new variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BomVariantGroup
    {
        return DB::transaction(function () use ($data) {
            $group = BomVariantGroup::create($data);

            // Add existing BOMs to the group if provided
            if (! empty($data['bom_ids'])) {
                $this->addBomsToGroup($group, $data['bom_ids'], $data['variant_names'] ?? []);
            }

            return $group->fresh(['boms', 'product']);
        });
    }

    /**
     * Update a variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BomVariantGroup $group, array $data): BomVariantGroup
    {
        return DB::transaction(function () use ($group, $data) {
            $group->fill($data);
            $group->save();

            return $group->fresh(['boms', 'product']);
        });
    }

    /**
     * Delete a variant group.
     */
    public function delete(BomVariantGroup $group): bool
    {
        return DB::transaction(function () use ($group) {
            // Unlink all BOMs from this group (don't delete the BOMs)
            Bom::query()
                ->where('variant_group_id', $group->id)
                ->update([
                    'variant_group_id' => null,
                    'variant_name' => null,
                    'variant_label' => null,
                    'is_primary_variant' => false,
                    'variant_sort_order' => 0,
                ]);

            return $group->delete();
        });
    }

    /**
     * Add a BOM to a variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function addBom(BomVariantGroup $group, Bom $bom, array $data = []): Bom
    {
        // Validate BOM belongs to same product
        if ($bom->product_id !== $group->product_id) {
            throw new \InvalidArgumentException('BOM must belong to the same product as the variant group.');
        }

        // Check if BOM is already in a group
        if ($bom->variant_group_id !== null && $bom->variant_group_id !== $group->id) {
            throw new \InvalidArgumentException('BOM is already in another variant group.');
        }

        $bom->variant_group_id = $group->id;
        $bom->variant_name = $data['variant_name'] ?? null;
        $bom->variant_label = $data['variant_label'] ?? null;
        $bom->is_primary_variant = $data['is_primary_variant'] ?? false;
        $bom->variant_sort_order = $data['sort_order'] ?? $this->getNextSortOrder($group);

        // If marking as primary, unset other primaries
        if ($bom->is_primary_variant) {
            $this->unsetOtherPrimaries($group, $bom->id);
        }

        $bom->save();

        return $bom->fresh();
    }

    /**
     * Remove a BOM from a variant group.
     */
    public function removeBom(BomVariantGroup $group, Bom $bom): Bom
    {
        if ($bom->variant_group_id !== $group->id) {
            throw new \InvalidArgumentException('BOM does not belong to this variant group.');
        }

        $bom->variant_group_id = null;
        $bom->variant_name = null;
        $bom->variant_label = null;
        $bom->is_primary_variant = false;
        $bom->variant_sort_order = 0;
        $bom->save();

        return $bom->fresh();
    }

    /**
     * Set the primary variant for a group.
     */
    public function setPrimaryVariant(BomVariantGroup $group, Bom $bom): Bom
    {
        if ($bom->variant_group_id !== $group->id) {
            throw new \InvalidArgumentException('BOM does not belong to this variant group.');
        }

        // Unset other primaries
        $this->unsetOtherPrimaries($group, $bom->id);

        $bom->is_primary_variant = true;
        $bom->save();

        return $bom->fresh();
    }

    /**
     * Reorder variants in a group.
     *
     * @param  array<int, int>  $bomIdOrder  [bom_id => sort_order]
     */
    public function reorderVariants(BomVariantGroup $group, array $bomIdOrder): BomVariantGroup
    {
        DB::transaction(function () use ($group, $bomIdOrder) {
            foreach ($bomIdOrder as $bomId => $sortOrder) {
                Bom::query()
                    ->where('id', $bomId)
                    ->where('variant_group_id', $group->id)
                    ->update(['variant_sort_order' => $sortOrder]);
            }
        });

        return $group->fresh(['boms']);
    }

    /**
     * Get comparison data for a variant group.
     *
     * @return array<string, mixed>
     */
    public function getComparisonData(BomVariantGroup $group): array
    {
        return $group->getComparisonData();
    }

    /**
     * Get detailed item-level comparison.
     *
     * @return array<string, mixed>
     */
    public function getDetailedComparison(BomVariantGroup $group): array
    {
        $boms = $group->boms()
            ->with(['items.product:id,name,sku,unit', 'product:id,name,sku'])
            ->get();

        if ($boms->isEmpty()) {
            return [
                'product' => $group->product,
                'variants' => [],
                'item_comparison' => [],
            ];
        }

        // Collect all unique items across all BOMs
        $allItems = [];
        foreach ($boms as $bom) {
            foreach ($bom->items as $item) {
                $key = $item->product_id ?? $item->description;
                if (! isset($allItems[$key])) {
                    $allItems[$key] = [
                        'product_id' => $item->product_id,
                        'description' => $item->description ?? $item->product?->name,
                        'unit' => $item->unit,
                        'type' => $item->type,
                        'variants' => [],
                    ];
                }
                $allItems[$key]['variants'][$bom->variant_name ?? $bom->id] = [
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->total_cost,
                    'waste_percentage' => $item->waste_percentage,
                ];
            }
        }

        return [
            'product' => $group->product,
            'variants' => $boms->map(fn ($bom) => [
                'id' => $bom->id,
                'variant_name' => $bom->variant_name,
                'variant_label' => $bom->variant_label,
                'is_primary' => $bom->is_primary_variant,
                'total_cost' => $bom->total_cost,
                'unit_cost' => $bom->unit_cost,
            ]),
            'item_comparison' => collect($allItems)->groupBy('type')->toArray(),
            'summary' => $group->generateComparisonSummary($boms),
        ];
    }

    /**
     * Duplicate a BOM into a variant group with different settings.
     *
     * @param  array<string, mixed>  $variantData
     */
    public function createVariantFromBom(
        BomVariantGroup $group,
        Bom $sourceBom,
        array $variantData
    ): Bom {
        if ($sourceBom->product_id !== $group->product_id) {
            throw new \InvalidArgumentException('Source BOM must belong to the same product.');
        }

        return DB::transaction(function () use ($group, $sourceBom, $variantData) {
            // Duplicate the BOM
            $newBom = $sourceBom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
            $newBom->bom_number = Bom::generateBomNumber();
            $newBom->status = Bom::STATUS_DRAFT;
            $newBom->variant_group_id = $group->id;
            $newBom->variant_name = $variantData['variant_name'] ?? 'Variant';
            $newBom->variant_label = $variantData['variant_label'] ?? null;
            $newBom->is_primary_variant = $variantData['is_primary_variant'] ?? false;
            $newBom->variant_sort_order = $this->getNextSortOrder($group);

            if (isset($variantData['name'])) {
                $newBom->name = $variantData['name'];
            }

            $newBom->save();

            // Duplicate items
            foreach ($sourceBom->items as $item) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }

            // If primary, unset others
            if ($newBom->is_primary_variant) {
                $this->unsetOtherPrimaries($group, $newBom->id);
            }

            return $newBom->fresh(['items', 'product']);
        });
    }

    /**
     * Add multiple BOMs to a group.
     *
     * @param  array<int>  $bomIds
     * @param  array<int, string>  $variantNames  [bom_id => variant_name]
     */
    private function addBomsToGroup(BomVariantGroup $group, array $bomIds, array $variantNames = []): void
    {
        $sortOrder = 0;
        foreach ($bomIds as $bomId) {
            $bom = Bom::find($bomId);
            if ($bom && $bom->product_id === $group->product_id) {
                $this->addBom($group, $bom, [
                    'variant_name' => $variantNames[$bomId] ?? null,
                    'sort_order' => $sortOrder++,
                ]);
            }
        }
    }

    /**
     * Get next sort order for a group.
     */
    private function getNextSortOrder(BomVariantGroup $group): int
    {
        return (int) Bom::query()
            ->where('variant_group_id', $group->id)
            ->max('variant_sort_order') + 1;
    }

    /**
     * Unset primary flag on all other BOMs in the group.
     */
    private function unsetOtherPrimaries(BomVariantGroup $group, int $exceptBomId): void
    {
        Bom::query()
            ->where('variant_group_id', $group->id)
            ->where('id', '!=', $exceptBomId)
            ->update(['is_primary_variant' => false]);
    }
}
