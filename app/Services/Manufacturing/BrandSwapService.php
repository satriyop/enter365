<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\BomVariantGroup;
use App\Models\Manufacturing\ComponentBrandMapping;
use App\Models\Manufacturing\SpecValidationRuleSet;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for brand swap operations on BOMs.
 *
 * Handles previewing, executing, and managing brand swaps for BOM items.
 */
class BrandSwapService
{
    public function __construct(
        private ProductEquivalenceService $equivalenceService,
        private BomVariantGroupService $variantGroupService,
        private SpecValidationService $validationService
    ) {}

    /**
     * Preview swap without creating a new BOM.
     *
     * @return array{current_total: float, estimated_total: float, savings: float, savings_percentage: float, coverage: array, items: array, validation: array}
     */
    public function previewSwapBrand(Bom $bom, string $targetBrand, ?SpecValidationRuleSet $ruleSet = null): array
    {
        $currentTotal = 0;
        $estimatedTotal = 0;
        $items = [];
        $swappable = 0;
        $noMapping = 0;

        // Get validation results
        $ruleSet = $ruleSet ?? $bom->getEffectiveRuleSet();
        $validation = $ruleSet
            ? $this->validationService->validateBomBrandSwap($bom, $targetBrand, $ruleSet)
            : ['valid' => true, 'items' => [], 'summary' => []];

        // Index validation results by bom_item_id
        $validationByItem = collect($validation['items'])->keyBy('bom_item_id');

        foreach ($bom->materialItems as $item) {
            $currentCost = $item->unit_cost * $item->quantity;
            $currentTotal += $currentCost;

            $preview = $this->previewItemSwap($item, $targetBrand);
            $estimatedCost = $preview['estimated_unit_cost'] * $item->quantity;
            $estimatedTotal += $estimatedCost;

            if ($preview['can_swap']) {
                $swappable++;
            } else {
                $noMapping++;
            }

            // Get validation for this item
            $itemValidation = $validationByItem->get($item->id);

            $items[] = [
                'bom_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'current_unit_cost' => $item->unit_cost,
                'current_total' => $currentCost,
                'estimated_unit_cost' => $preview['estimated_unit_cost'],
                'estimated_total' => $estimatedCost,
                'cost_change' => $estimatedCost - $currentCost,
                'can_swap' => $preview['can_swap'],
                'target_product' => $preview['target_product'],
                'target_sku' => $preview['target_sku'],
                'validation' => $itemValidation ? [
                    'status' => $itemValidation['status'] ?? 'valid',
                    'warnings' => $itemValidation['warnings'] ?? [],
                    'errors' => $itemValidation['errors'] ?? [],
                ] : null,
            ];
        }

        $savings = $currentTotal - $estimatedTotal;
        $savingsPercentage = $currentTotal > 0 ? ($savings / $currentTotal) * 100 : 0;

        return [
            'target_brand' => $targetBrand,
            'current_total' => $currentTotal,
            'estimated_total' => $estimatedTotal,
            'savings' => $savings,
            'savings_percentage' => round($savingsPercentage, 1),
            'coverage' => [
                'total' => count($items),
                'swappable' => $swappable,
                'no_mapping' => $noMapping,
                'percentage' => count($items) > 0 ? round(($swappable / count($items)) * 100) : 0,
            ],
            'items' => $items,
            'validation' => [
                'valid' => $validation['valid'],
                'rule_set' => $ruleSet ? [
                    'id' => $ruleSet->id,
                    'name' => $ruleSet->name,
                    'code' => $ruleSet->code,
                ] : null,
                'summary' => $validation['summary'] ?? [],
            ],
        ];
    }

    /**
     * Compare BOM across all available brands at once.
     *
     * @return array{current: array, brands: array, best_value: string|null, best_coverage: string|null}
     */
    public function compareBrands(Bom $bom): array
    {
        // Get all unique brands from mappings
        $availableBrands = ComponentBrandMapping::query()
            ->select('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand')
            ->toArray();

        // Calculate current BOM total
        $currentTotal = $bom->materialItems->sum(fn ($item) => $item->unit_cost * $item->quantity);
        $totalItems = $bom->materialItems->count();

        // Get preview for each brand
        $brandPreviews = [];
        $bestValue = null;
        $bestValueSavings = 0;
        $bestCoverage = null;
        $bestCoveragePercent = 0;

        foreach ($availableBrands as $brand) {
            $preview = $this->previewSwapBrand($bom, $brand);

            $brandPreviews[] = [
                'brand' => $brand,
                'brand_label' => ComponentBrandMapping::getBrands()[$brand] ?? ucfirst($brand),
                'estimated_total' => $preview['estimated_total'],
                'savings' => $preview['savings'],
                'savings_percentage' => $preview['savings_percentage'],
                'coverage_percentage' => $preview['coverage']['percentage'],
                'swappable_items' => $preview['coverage']['swappable'],
                'no_mapping_items' => $preview['coverage']['no_mapping'],
            ];

            // Track best value (highest savings with decent coverage)
            if ($preview['coverage']['percentage'] >= 50 && $preview['savings'] > $bestValueSavings) {
                $bestValue = $brand;
                $bestValueSavings = $preview['savings'];
            }

            // Track best coverage
            if ($preview['coverage']['percentage'] > $bestCoveragePercent) {
                $bestCoverage = $brand;
                $bestCoveragePercent = $preview['coverage']['percentage'];
            }
        }

        // Sort by savings (highest first)
        usort($brandPreviews, fn ($a, $b) => $b['savings'] <=> $a['savings']);

        return [
            'current' => [
                'total' => $currentTotal,
                'total_items' => $totalItems,
            ],
            'brands' => $brandPreviews,
            'recommendations' => [
                'best_value' => $bestValue,
                'best_coverage' => $bestCoverage,
            ],
        ];
    }

    /**
     * Preview a single item swap without persisting.
     *
     * @return array{can_swap: bool, estimated_unit_cost: float, target_product: string|null, target_sku: string|null}
     */
    public function previewItemSwap(BomItem $item, string $targetBrand): array
    {
        $result = [
            'can_swap' => false,
            'estimated_unit_cost' => $item->unit_cost,
            'target_product' => null,
            'target_sku' => null,
        ];

        if (! $item->product_id) {
            return $result;
        }

        // Find component standard for this product
        $mapping = ComponentBrandMapping::query()
            ->where('product_id', $item->product_id)
            ->first();

        if (! $mapping) {
            return $result;
        }

        // Find equivalent in target brand
        $targetMapping = ComponentBrandMapping::query()
            ->with('product')
            ->where('component_standard_id', $mapping->component_standard_id)
            ->where('brand', $targetBrand)
            ->where('is_preferred', true)
            ->first()
            ?? ComponentBrandMapping::query()
                ->with('product')
                ->where('component_standard_id', $mapping->component_standard_id)
                ->where('brand', $targetBrand)
                ->first();

        if ($targetMapping && $targetMapping->product) {
            $result['can_swap'] = true;
            $result['estimated_unit_cost'] = $targetMapping->product->purchase_price;
            $result['target_product'] = $targetMapping->product->name;
            $result['target_sku'] = $targetMapping->brand_sku;
        }

        return $result;
    }

    /**
     * Swap all components in a BOM to a different brand.
     *
     * @return array{bom: Bom, swap_report: array<string, mixed>}
     */
    public function swapBomBrand(
        Bom $bom,
        string $targetBrand,
        bool $createVariant = true,
        ?BomVariantGroup $variantGroup = null
    ): array {
        return DB::transaction(function () use ($bom, $targetBrand, $createVariant, $variantGroup) {
            $swapReport = [
                'total_items' => 0,
                'swapped' => 0,
                'no_mapping' => 0,
                'partial_match' => 0,
                'kept_original' => 0,
                'items' => [],
            ];

            // Duplicate the BOM if creating variant
            if ($createVariant) {
                $newBom = $bom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
                $newBom->bom_number = Bom::generateBomNumber();
                $newBom->status = DocumentStatus::Draft;
                $newBom->name = $bom->name.' ('.ucfirst($targetBrand).')';
                $newBom->variant_name = ucfirst($targetBrand);
                $newBom->variant_label = 'Brand: '.ucfirst($targetBrand);
                $newBom->save();
            } else {
                $newBom = $bom;
            }

            // Process each material item
            foreach ($bom->materialItems as $item) {
                $swapReport['total_items']++;
                $itemReport = $this->swapItem($item, $newBom, $targetBrand, $createVariant);
                $swapReport['items'][] = $itemReport;

                match ($itemReport['status']) {
                    'swapped' => $swapReport['swapped']++,
                    'no_mapping' => $swapReport['no_mapping']++,
                    'partial_match' => $swapReport['partial_match']++,
                    default => $swapReport['kept_original']++,
                };
            }

            // Copy labor and overhead items unchanged
            if ($createVariant) {
                foreach ($bom->laborItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }

                foreach ($bom->overheadItems as $item) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }
            }

            // Recalculate costs
            $newBom->calculateTotals();
            $newBom->save();

            // Add to variant group if specified
            if ($variantGroup) {
                $this->variantGroupService->addBom($variantGroup, $newBom, [
                    'variant_name' => ucfirst($targetBrand),
                    'variant_label' => 'Brand: '.ucfirst($targetBrand),
                ]);
            }

            return [
                'bom' => $newBom->fresh(['items.product', 'product']),
                'swap_report' => $swapReport,
            ];
        });
    }

    /**
     * Generate all brand variants for a BOM at once.
     *
     * @param  array<string>  $brands
     * @return array{variant_group: BomVariantGroup, boms: Collection<int, Bom>, report: array<string, mixed>}
     */
    public function generateBrandVariants(
        Bom $bom,
        array $brands,
        ?string $groupName = null
    ): array {
        return DB::transaction(function () use ($bom, $brands, $groupName) {
            // Create variant group
            $groupName ??= "{$bom->product->name} - Brand Variants";
            $group = BomVariantGroup::create([
                'product_id' => $bom->product_id,
                'name' => $groupName,
                'description' => 'Auto-generated brand variants',
                'status' => DocumentStatus::Draft,
                'created_by' => auth()->id(),
            ]);

            // Add original BOM to group
            $this->variantGroupService->addBom($group, $bom, [
                'variant_name' => 'Original',
                'is_primary_variant' => true,
            ]);

            $generatedBoms = collect();
            $reports = [];

            foreach ($brands as $brand) {
                $result = $this->swapBomBrand($bom, $brand, true, $group);
                $generatedBoms->push($result['bom']);
                $reports[$brand] = $result['swap_report'];
            }

            return [
                'variant_group' => $group->fresh(['boms', 'product']),
                'boms' => $generatedBoms,
                'report' => $reports,
            ];
        });
    }

    /**
     * Get available alternatives for a BOM item.
     *
     * @return array{alternatives: array, current: array}
     */
    public function getItemAlternatives(BomItem $item): array
    {
        $current = [
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name ?? $item->description,
            'product_sku' => $item->product?->sku,
            'unit_cost' => $item->unit_cost,
            'brand' => null,
            'component_standard_id' => $item->component_standard_id,
        ];

        // If no component standard, no alternatives available
        if (! $item->component_standard_id) {
            return [
                'current' => $current,
                'alternatives' => [],
                'has_standard' => false,
            ];
        }

        // Find current brand mapping
        $currentMapping = null;
        if ($item->product_id) {
            $currentMapping = ComponentBrandMapping::query()
                ->where('product_id', $item->product_id)
                ->where('component_standard_id', $item->component_standard_id)
                ->first();
        }

        $current['brand'] = $currentMapping?->brand;
        $current['brand_label'] = $currentMapping
            ? (ComponentBrandMapping::getBrands()[$currentMapping->brand] ?? ucfirst($currentMapping->brand))
            : null;

        // Get all mappings for this standard (excluding current product)
        $alternatives = ComponentBrandMapping::query()
            ->with('product')
            ->where('component_standard_id', $item->component_standard_id)
            ->when($item->product_id, fn ($q) => $q->where('product_id', '!=', $item->product_id))
            ->orderBy('is_preferred', 'desc')
            ->orderBy('brand')
            ->get()
            ->filter(fn ($m) => $m->product && $m->product->purchase_price > 0)
            ->map(function ($mapping) use ($item) {
                $priceDiff = $mapping->product->purchase_price - $item->unit_cost;
                $priceDiffPercent = $item->unit_cost > 0
                    ? round(($priceDiff / $item->unit_cost) * 100, 1)
                    : 0;

                return [
                    'mapping_id' => $mapping->id,
                    'product_id' => $mapping->product_id,
                    'product_name' => $mapping->product->name,
                    'product_sku' => $mapping->product->sku,
                    'brand' => $mapping->brand,
                    'brand_label' => ComponentBrandMapping::getBrands()[$mapping->brand] ?? ucfirst($mapping->brand),
                    'brand_sku' => $mapping->brand_sku,
                    'unit_cost' => $mapping->product->purchase_price,
                    'price_diff' => $priceDiff,
                    'price_diff_percent' => $priceDiffPercent,
                    'is_preferred' => $mapping->is_preferred,
                    'is_verified' => $mapping->is_verified,
                    'stock' => $mapping->product->current_stock ?? 0,
                ];
            })
            ->values()
            ->toArray();

        return [
            'current' => $current,
            'alternatives' => $alternatives,
            'has_standard' => true,
        ];
    }

    /**
     * Quick swap a BOM item to a different product (in-place edit).
     *
     * @return array{success: bool, item: BomItem, previous: array, savings: float}
     */
    public function quickSwapItem(BomItem $item, Product $newProduct, ?string $reason = null): array
    {
        return DB::transaction(function () use ($item, $newProduct, $reason) {
            // Store previous state for audit
            $previous = [
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? $item->description,
                'product_sku' => $item->product?->sku,
                'unit_cost' => $item->unit_cost,
            ];

            $oldCost = $item->unit_cost;
            $newCost = $newProduct->purchase_price;

            // Update the item
            $item->product_id = $newProduct->id;
            $item->description = $newProduct->name;
            $item->unit_cost = $newCost;

            // Find component standard from the new product's mapping
            $newMapping = ComponentBrandMapping::query()
                ->where('product_id', $newProduct->id)
                ->first();

            if ($newMapping) {
                $item->component_standard_id = $newMapping->component_standard_id;
            }

            $item->calculateTotalCost();
            $item->save();

            // Recalculate BOM totals
            $item->bom->calculateTotals();
            $item->bom->save();

            $savings = ($oldCost - $newCost) * $item->quantity;

            return [
                'success' => true,
                'item' => $item->fresh(['product']),
                'previous' => $previous,
                'new' => [
                    'product_id' => $newProduct->id,
                    'product_name' => $newProduct->name,
                    'product_sku' => $newProduct->sku,
                    'unit_cost' => $newCost,
                ],
                'savings' => $savings,
                'reason' => $reason,
            ];
        });
    }

    /**
     * Swap a single BOM item to target brand.
     *
     * @return array{status: string, original: array<string, mixed>, new: array<string, mixed>|null, notes: string|null}
     */
    private function swapItem(
        BomItem $item,
        Bom $newBom,
        string $targetBrand,
        bool $isNew
    ): array {
        $result = [
            'status' => 'no_change',
            'original' => [
                'product_id' => $item->product_id,
                'description' => $item->description,
                'unit_cost' => $item->unit_cost,
            ],
            'new' => null,
            'notes' => null,
        ];

        if (! $item->product_id) {
            // Non-product items (manual entries) - just copy
            if ($isNew) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }
            $result['status'] = 'no_product';

            return $result;
        }

        // Find component standard for this product
        $mapping = ComponentBrandMapping::query()
            ->where('product_id', $item->product_id)
            ->first();

        if (! $mapping) {
            // No cross-reference mapping exists
            if ($isNew) {
                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;
                $newItem->save();
            }
            $result['status'] = 'no_mapping';
            $result['notes'] = 'No component standard mapping found';

            return $result;
        }

        // Find equivalent in target brand
        $targetMapping = ComponentBrandMapping::query()
            ->where('component_standard_id', $mapping->component_standard_id)
            ->where('brand', $targetBrand)
            ->where('is_preferred', true)
            ->first()
            ?? ComponentBrandMapping::query()
                ->where('component_standard_id', $mapping->component_standard_id)
                ->where('brand', $targetBrand)
                ->first();

        if (! $targetMapping) {
            // Try partial match
            $partialMatches = $this->equivalenceService->findPartialMatches(
                $mapping->componentStandard,
                $targetBrand,
                70
            );

            if ($partialMatches->isNotEmpty()) {
                $targetMapping = $partialMatches->first()['mapping'];
                $result['status'] = 'partial_match';
                $result['notes'] = 'Using partial match (score: '.
                    $partialMatches->first()['match_score'].'%)';
            } else {
                // No equivalent found - keep original
                if ($isNew) {
                    $newItem = $item->replicate();
                    $newItem->bom_id = $newBom->id;
                    $newItem->save();
                }
                $result['status'] = 'no_mapping';
                $result['notes'] = "No equivalent found for brand: {$targetBrand}";

                return $result;
            }
        } else {
            $result['status'] = 'swapped';
        }

        // Create new item with target brand product
        $newProduct = $targetMapping->product;

        if ($isNew) {
            $newItem = $item->replicate();
            $newItem->bom_id = $newBom->id;
        } else {
            $newItem = $item;
        }

        $newItem->product_id = $newProduct->id;
        $newItem->component_standard_id = $mapping->component_standard_id;
        $newItem->description = $newProduct->name;
        $newItem->unit_cost = $newProduct->purchase_price;
        $newItem->calculateTotalCost();
        $newItem->save();

        $result['new'] = [
            'product_id' => $newProduct->id,
            'description' => $newProduct->name,
            'unit_cost' => $newProduct->purchase_price,
            'brand' => $targetBrand,
            'brand_sku' => $targetMapping->brand_sku,
        ];

        return $result;
    }
}
