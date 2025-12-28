<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ComponentCrossReferenceService
{
    public function __construct(
        private BomService $bomService,
        private BomVariantGroupService $variantGroupService
    ) {}

    /**
     * Find equivalent products for a given product.
     *
     * @return Collection<int, ComponentBrandMapping>
     */
    public function findEquivalents(Product $product, ?string $targetBrand = null): Collection
    {
        // Find component standard linked to this product
        $mapping = ComponentBrandMapping::query()
            ->where('product_id', $product->id)
            ->first();

        if (! $mapping) {
            return collect();
        }

        $query = ComponentBrandMapping::query()
            ->with(['product', 'componentStandard'])
            ->where('component_standard_id', $mapping->component_standard_id)
            ->where('product_id', '!=', $product->id);

        if ($targetBrand) {
            $query->where('brand', $targetBrand);
        }

        return $query->orderBy('is_preferred', 'desc')
            ->orderBy('is_verified', 'desc')
            ->get();
    }

    /**
     * Find products matching a component standard by specs.
     *
     * @param  array<string, mixed>  $specs
     * @return Collection<int, ComponentStandard>
     */
    public function searchBySpecs(
        string $category,
        array $specs,
        ?string $brand = null
    ): Collection {
        $query = ComponentStandard::query()
            ->with(['brandMappings.product'])
            ->active()
            ->inCategory($category);

        // Apply spec filters
        foreach ($specs as $key => $value) {
            if ($value !== null) {
                $query->whereJsonContains("specifications->{$key}", $value);
            }
        }

        $standards = $query->get();

        // Filter by brand if specified
        if ($brand) {
            $standards = $standards->filter(function ($standard) use ($brand) {
                return $standard->brandMappings->contains('brand', $brand);
            });
        }

        return $standards->values();
    }

    /**
     * Preview swap without creating a new BOM.
     * Shows estimated costs and which items can be swapped.
     *
     * @return array{current_total: float, estimated_total: float, savings: float, savings_percentage: float, coverage: array, items: array}
     */
    public function previewSwapBrand(Bom $bom, string $targetBrand): array
    {
        $currentTotal = 0;
        $estimatedTotal = 0;
        $items = [];
        $swappable = 0;
        $noMapping = 0;

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

            $items[] = [
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
        ];
    }

    /**
     * Compare BOM across all available brands at once.
     * Returns side-by-side comparison data for quick decision making.
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
    private function previewItemSwap(BomItem $item, string $targetBrand): array
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
                $newBom->status = Bom::STATUS_DRAFT;
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
                'status' => BomVariantGroup::STATUS_DRAFT,
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
     * Find partial matches when exact equivalent not available.
     *
     * @return Collection<int, array{mapping: ComponentBrandMapping, match_score: int, differences: array<string, mixed>}>
     */
    public function findPartialMatches(
        ComponentStandard $standard,
        string $targetBrand,
        int $minScore = 70
    ): Collection {
        // Get all standards in same category and subcategory
        $candidates = ComponentStandard::query()
            ->active()
            ->inCategory($standard->category)
            ->when($standard->subcategory, fn ($q) => $q->inSubcategory($standard->subcategory))
            ->with(['brandMappings' => fn ($q) => $q->where('brand', $targetBrand)])
            ->whereHas('brandMappings', fn ($q) => $q->where('brand', $targetBrand))
            ->get();

        $matches = collect();
        $sourceSpecs = $standard->specifications ?? [];

        foreach ($candidates as $candidate) {
            if ($candidate->id === $standard->id) {
                continue;
            }

            $candidateSpecs = $candidate->specifications ?? [];
            $score = $this->calculateMatchScore($sourceSpecs, $candidateSpecs);
            $differences = $this->findSpecDifferences($sourceSpecs, $candidateSpecs);

            if ($score >= $minScore) {
                foreach ($candidate->brandMappings as $mapping) {
                    $matches->push([
                        'mapping' => $mapping,
                        'match_score' => $score,
                        'differences' => $differences,
                    ]);
                }
            }
        }

        return $matches->sortByDesc('match_score')->values();
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
            $partialMatches = $this->findPartialMatches(
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

    /**
     * Calculate match score between two spec arrays.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    private function calculateMatchScore(array $source, array $target): int
    {
        if (empty($source)) {
            return 0;
        }

        $matchedWeight = 0;
        $totalWeight = 0;

        // Weight different specs differently
        $weights = [
            'rating_amps' => 10,
            'poles' => 8,
            'breaking_capacity_ka' => 6,
            'curve' => 4,
            'voltage' => 5,
            'conductor_size_mm2' => 10,
            'cores' => 8,
        ];

        foreach ($source as $key => $value) {
            $weight = $weights[$key] ?? 3;
            $totalWeight += $weight;

            if (isset($target[$key])) {
                if ($target[$key] === $value) {
                    $matchedWeight += $weight;
                } elseif (is_numeric($value) && is_numeric($target[$key])) {
                    // Partial match for numeric values within 20%
                    $diff = abs($value - $target[$key]) / max($value, 1);
                    if ($diff <= 0.2) {
                        $matchedWeight += $weight * (1 - $diff);
                    }
                }
            }
        }

        return $totalWeight > 0 ? (int) round(($matchedWeight / $totalWeight) * 100) : 0;
    }

    /**
     * Find differences between spec arrays.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     * @return array<string, array{source: mixed, target: mixed}>
     */
    private function findSpecDifferences(array $source, array $target): array
    {
        $differences = [];

        foreach ($source as $key => $value) {
            if (! isset($target[$key]) || $target[$key] !== $value) {
                $differences[$key] = [
                    'source' => $value,
                    'target' => $target[$key] ?? null,
                ];
            }
        }

        return $differences;
    }

    /**
     * Preview cost optimization by finding cheapest option per item across all brands.
     *
     * @return array{current_total: float, optimized_total: float, savings: float, savings_percentage: float, items: array}
     */
    public function previewCostOptimization(Bom $bom): array
    {
        $currentTotal = 0;
        $optimizedTotal = 0;
        $items = [];

        foreach ($bom->materialItems as $item) {
            $currentCost = $item->unit_cost * $item->quantity;
            $currentTotal += $currentCost;

            $optimization = $this->findCheapestAlternative($item);

            $optimizedCost = $optimization['cheapest_unit_cost'] * $item->quantity;
            $optimizedTotal += $optimizedCost;

            $items[] = [
                'bom_item_id' => $item->id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'current_brand' => $optimization['current_brand'],
                'current_unit_cost' => $item->unit_cost,
                'current_total' => $currentCost,
                'cheapest_brand' => $optimization['cheapest_brand'],
                'cheapest_brand_label' => $optimization['cheapest_brand_label'],
                'cheapest_unit_cost' => $optimization['cheapest_unit_cost'],
                'cheapest_total' => $optimizedCost,
                'cheapest_product_id' => $optimization['cheapest_product_id'],
                'cheapest_product_name' => $optimization['cheapest_product_name'],
                'cheapest_sku' => $optimization['cheapest_sku'],
                'savings' => $currentCost - $optimizedCost,
                'savings_percentage' => $currentCost > 0 ? round((($currentCost - $optimizedCost) / $currentCost) * 100, 1) : 0,
                'can_optimize' => $optimization['can_optimize'],
                'is_already_cheapest' => $optimization['is_already_cheapest'],
            ];
        }

        $savings = $currentTotal - $optimizedTotal;
        $savingsPercentage = $currentTotal > 0 ? ($savings / $currentTotal) * 100 : 0;

        // Count stats
        $canOptimize = collect($items)->where('can_optimize', true)->where('is_already_cheapest', false)->count();
        $alreadyCheapest = collect($items)->where('is_already_cheapest', true)->count();
        $noAlternative = collect($items)->where('can_optimize', false)->count();

        return [
            'current_total' => $currentTotal,
            'optimized_total' => $optimizedTotal,
            'savings' => $savings,
            'savings_percentage' => round($savingsPercentage, 1),
            'summary' => [
                'total_items' => count($items),
                'can_optimize' => $canOptimize,
                'already_cheapest' => $alreadyCheapest,
                'no_alternative' => $noAlternative,
            ],
            'items' => $items,
        ];
    }

    /**
     * Find the cheapest alternative for a BOM item across all brands.
     *
     * @return array{can_optimize: bool, is_already_cheapest: bool, current_brand: string|null, cheapest_brand: string|null, cheapest_brand_label: string|null, cheapest_unit_cost: float, cheapest_product_id: int|null, cheapest_product_name: string|null, cheapest_sku: string|null}
     */
    private function findCheapestAlternative(BomItem $item): array
    {
        $result = [
            'can_optimize' => false,
            'is_already_cheapest' => false,
            'current_brand' => null,
            'cheapest_brand' => null,
            'cheapest_brand_label' => null,
            'cheapest_unit_cost' => $item->unit_cost,
            'cheapest_product_id' => null,
            'cheapest_product_name' => null,
            'cheapest_sku' => null,
        ];

        if (! $item->product_id) {
            return $result;
        }

        // Find component standard for this product
        $currentMapping = ComponentBrandMapping::query()
            ->where('product_id', $item->product_id)
            ->first();

        if (! $currentMapping) {
            return $result;
        }

        $result['current_brand'] = $currentMapping->brand;

        // Get all brand mappings for this component standard
        $allMappings = ComponentBrandMapping::query()
            ->with('product')
            ->where('component_standard_id', $currentMapping->component_standard_id)
            ->whereHas('product')
            ->get();

        if ($allMappings->isEmpty()) {
            return $result;
        }

        // Find the cheapest one
        $cheapest = $allMappings
            ->filter(fn ($m) => $m->product && $m->product->purchase_price > 0)
            ->sortBy(fn ($m) => $m->product->purchase_price)
            ->first();

        if (! $cheapest) {
            return $result;
        }

        $result['can_optimize'] = true;
        $result['cheapest_brand'] = $cheapest->brand;
        $result['cheapest_brand_label'] = ComponentBrandMapping::getBrands()[$cheapest->brand] ?? ucfirst($cheapest->brand);
        $result['cheapest_unit_cost'] = $cheapest->product->purchase_price;
        $result['cheapest_product_id'] = $cheapest->product->id;
        $result['cheapest_product_name'] = $cheapest->product->name;
        $result['cheapest_sku'] = $cheapest->brand_sku;

        // Check if current is already the cheapest
        if ($cheapest->product_id === $item->product_id) {
            $result['is_already_cheapest'] = true;
        }

        return $result;
    }

    /**
     * Apply cost optimization to create a new BOM with cheapest alternatives.
     *
     * @param  array<int>  $itemIds  BOM item IDs to optimize (empty = all)
     * @return array{bom: Bom, optimization_report: array}
     */
    public function applyCostOptimization(Bom $bom, array $itemIds = []): array
    {
        return DB::transaction(function () use ($bom, $itemIds) {
            // Create new BOM variant
            $newBom = $bom->replicate(['bom_number', 'status', 'approved_by', 'approved_at']);
            $newBom->bom_number = Bom::generateBomNumber();
            $newBom->status = Bom::STATUS_DRAFT;
            $newBom->name = $bom->name.' (Budget Optimized)';
            $newBom->variant_name = 'Budget Optimized';
            $newBom->variant_label = 'Mixed brands - lowest cost';
            $newBom->save();

            $report = [
                'total_items' => 0,
                'optimized' => 0,
                'kept_original' => 0,
                'total_savings' => 0,
                'items' => [],
            ];

            // Process material items
            foreach ($bom->materialItems as $item) {
                $report['total_items']++;
                $shouldOptimize = empty($itemIds) || in_array($item->id, $itemIds);

                $newItem = $item->replicate();
                $newItem->bom_id = $newBom->id;

                if ($shouldOptimize) {
                    $optimization = $this->findCheapestAlternative($item);

                    if ($optimization['can_optimize'] && ! $optimization['is_already_cheapest']) {
                        // Apply the optimization
                        $newItem->product_id = $optimization['cheapest_product_id'];
                        $newItem->description = $optimization['cheapest_product_name'];
                        $newItem->unit_cost = $optimization['cheapest_unit_cost'];
                        $newItem->calculateTotalCost();

                        $savings = ($item->unit_cost - $optimization['cheapest_unit_cost']) * $item->quantity;
                        $report['total_savings'] += $savings;
                        $report['optimized']++;

                        $report['items'][] = [
                            'status' => 'optimized',
                            'original' => [
                                'description' => $item->description,
                                'brand' => $optimization['current_brand'],
                                'unit_cost' => $item->unit_cost,
                            ],
                            'new' => [
                                'description' => $optimization['cheapest_product_name'],
                                'brand' => $optimization['cheapest_brand'],
                                'brand_label' => $optimization['cheapest_brand_label'],
                                'unit_cost' => $optimization['cheapest_unit_cost'],
                                'sku' => $optimization['cheapest_sku'],
                            ],
                            'savings' => $savings,
                        ];
                    } else {
                        $report['kept_original']++;
                        $report['items'][] = [
                            'status' => $optimization['is_already_cheapest'] ? 'already_cheapest' : 'no_alternative',
                            'original' => [
                                'description' => $item->description,
                                'brand' => $optimization['current_brand'],
                                'unit_cost' => $item->unit_cost,
                            ],
                            'new' => null,
                            'savings' => 0,
                        ];
                    }
                } else {
                    $report['kept_original']++;
                    $report['items'][] = [
                        'status' => 'excluded',
                        'original' => [
                            'description' => $item->description,
                            'unit_cost' => $item->unit_cost,
                        ],
                        'new' => null,
                        'savings' => 0,
                    ];
                }

                $newItem->save();
            }

            // Copy labor and overhead items unchanged
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

            // Recalculate costs
            $newBom->calculateTotals();
            $newBom->save();

            return [
                'bom' => $newBom->fresh(['items.product', 'product']),
                'optimization_report' => $report,
            ];
        });
    }
}
