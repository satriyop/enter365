<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel\BrandSwap;

use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Services\ElectricalPanel\SpecValidationService;

/**
 * Service for previewing brand swap operations.
 *
 * Handles read-only operations: previewing swaps, comparing brands,
 * and finding alternatives for BOM items.
 */
class BrandSwapPreviewService
{
    public function __construct(
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
        $validation = $this->validationService->validateBomBrandSwap($bom, $targetBrand, $ruleSet);

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
     * Get available alternatives for a BOM item.
     *
     * @return array{alternatives: array, current: array}
     */
    public function getItemAlternatives(BomItem $item): array
    {
        $current = [
            'product_id' => $item->product_id,
            'product_name' => $item->product->name ?? $item->description,
            'product_sku' => $item->product->sku ?? null,
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

        $current['brand'] = $currentMapping->brand ?? null;
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
}
