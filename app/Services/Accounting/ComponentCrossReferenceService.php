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

    // =========================================================================
    // Quick Swap - Inline Item-Level Brand Swap
    // =========================================================================

    /**
     * Get available alternatives for a BOM item.
     *
     * Returns all equivalent products from other brands that can replace the item.
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
     * Unlike swapBomBrand which creates a new BOM, this modifies the item directly.
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

    // =========================================================================
    // Auto-Mapping - Suggest Component Standards from Product Names
    // =========================================================================

    /**
     * Get products without mappings for a given brand.
     *
     * @return Collection<int, Product>
     */
    public function getUnmappedProducts(?string $brand = null, int $limit = 50): Collection
    {
        $query = Product::query()
            ->whereDoesntHave('componentBrandMappings')
            ->where('is_active', true)
            ->where('type', 'component') // Only electrical components
            ->orderBy('name');

        if ($brand) {
            $query->where('brand', $brand);
        }

        return $query->limit($limit)->get();
    }

    /**
     * Suggest component standards for a product based on name parsing.
     *
     * @return array{product_id: int, product_name: string, suggestions: array, parsed_specs: array}
     */
    public function suggestMappingForProduct(Product $product): array
    {
        $parsedSpecs = $this->parseProductName($product->name);
        $suggestions = [];

        if (! empty($parsedSpecs['category'])) {
            // Search for matching standards
            $matchingStandards = ComponentStandard::query()
                ->active()
                ->inCategory($parsedSpecs['category'])
                ->when($parsedSpecs['subcategory'] ?? null, fn ($q, $sub) => $q->inSubcategory($sub))
                ->get()
                ->map(function ($standard) use ($parsedSpecs) {
                    $score = $this->calculateMatchScore($parsedSpecs['specs'] ?? [], $standard->specifications ?? []);

                    return [
                        'standard' => $standard,
                        'score' => $score,
                    ];
                })
                ->filter(fn ($item) => $item['score'] >= 50)
                ->sortByDesc('score')
                ->take(5);

            foreach ($matchingStandards as $match) {
                $standard = $match['standard'];
                $suggestions[] = [
                    'component_standard_id' => $standard->id,
                    'code' => $standard->code,
                    'name' => $standard->name,
                    'category' => $standard->category,
                    'subcategory' => $standard->subcategory,
                    'specifications' => $standard->specifications,
                    'match_score' => $match['score'],
                    'existing_brands' => $standard->brandMappings()
                        ->distinct('brand')
                        ->pluck('brand')
                        ->toArray(),
                ];
            }
        }

        return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'product_sku' => $product->sku,
            'product_brand' => $product->brand,
            'parsed_specs' => $parsedSpecs,
            'suggestions' => $suggestions,
            'has_suggestions' => count($suggestions) > 0,
        ];
    }

    /**
     * Get mapping suggestions for multiple products.
     *
     * @param  Collection<int, Product>  $products
     * @return array<int, array>
     */
    public function suggestMappingsForProducts(Collection $products): array
    {
        return $products->map(fn ($product) => $this->suggestMappingForProduct($product))->toArray();
    }

    /**
     * Parse product name to extract category and specifications.
     *
     * @return array{category: string|null, subcategory: string|null, specs: array, brand: string|null}
     */
    public function parseProductName(string $name): array
    {
        $name = strtoupper($name);
        $result = [
            'category' => null,
            'subcategory' => null,
            'specs' => [],
            'brand' => null,
        ];

        // Detect brand
        $brands = [
            'schneider' => ['SCHNEIDER', 'EASY9', 'ACTI9', 'DOMAE', 'RCCB'],
            'abb' => ['ABB', 'S200', 'S201', 'S202', 'S203', 'SH200'],
            'siemens' => ['SIEMENS', 'SENTRON', '5SL', '5SY', '5SV'],
            'chint' => ['CHINT', 'NB1', 'NXB', 'NXBLE'],
            'hager' => ['HAGER', 'MC', 'MU', 'MY'],
            'legrand' => ['LEGRAND', 'DX3', 'RX3'],
            'ls' => ['LS', 'BKN', 'BKH', 'METASOL'],
        ];

        foreach ($brands as $brandCode => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($name, $keyword)) {
                    $result['brand'] = $brandCode;
                    break 2;
                }
            }
        }

        // Detect category and subcategory from keywords
        $categoryPatterns = [
            'circuit_breaker' => [
                'subcategories' => [
                    'mcb' => ['MCB', 'MINIATURE CIRCUIT BREAKER', 'C1', 'C2', 'C4', 'C6', 'C10', 'C16', 'C20', 'C25', 'C32', 'C40', 'C50', 'C63'],
                    'mccb' => ['MCCB', 'MOLDED CASE', 'NSX', 'NZM', '3VT', 'CVS'],
                    'rccb' => ['RCCB', 'ELCB', 'RESIDUAL CURRENT', 'RCD'],
                    'rcbo' => ['RCBO', 'RCMC'],
                ],
                'keywords' => ['BREAKER', 'MCB', 'MCCB', 'RCCB', 'RCBO', 'ACB', 'CIRCUIT'],
            ],
            'contactor' => [
                'subcategories' => [
                    'power' => ['CONTACTOR', 'LC1', 'AF', 'A9C', 'NC1'],
                    'auxiliary' => ['AUXILIARY', 'CA', 'LADN', 'LADT'],
                ],
                'keywords' => ['CONTACTOR', 'LC1', 'AF0', 'AF09', 'AF12', 'AF16', 'AF26'],
            ],
            'relay' => [
                'subcategories' => [
                    'thermal' => ['THERMAL', 'OVERLOAD', 'LRD', 'LR2', 'LRE'],
                    'control' => ['CONTROL', 'TIMER', 'TIME DELAY'],
                ],
                'keywords' => ['RELAY', 'OVERLOAD', 'LRD', 'LRE', 'THERMAL'],
            ],
            'cable' => [
                'subcategories' => [
                    'power' => ['NYY', 'NYA', 'NYM', 'NYFGBY'],
                    'control' => ['NYMHY', 'NYAF', 'FLEXIBLE'],
                ],
                'keywords' => ['CABLE', 'NYY', 'NYA', 'NYM', 'KABEL', 'NYFGBY'],
            ],
            'busbar' => [
                'subcategories' => [],
                'keywords' => ['BUSBAR', 'BUS BAR', 'COPPER BAR', 'DISTRIBUSI'],
            ],
            'terminal' => [
                'subcategories' => [
                    'din_rail' => ['DIN RAIL', 'TERMINAL BLOCK'],
                    'lug' => ['LUG', 'CABLE LUG', 'SKUN'],
                ],
                'keywords' => ['TERMINAL', 'BLOCK', 'LUG', 'SKUN'],
            ],
            'enclosure' => [
                'subcategories' => [
                    'panel_box' => ['PANEL BOX', 'DISTRIBUTION BOX', 'DB BOX'],
                    'junction' => ['JUNCTION', 'PULL BOX'],
                ],
                'keywords' => ['PANEL', 'BOX', 'ENCLOSURE', 'CABINET'],
            ],
        ];

        foreach ($categoryPatterns as $category => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($name, $keyword)) {
                    $result['category'] = $category;

                    // Check subcategories
                    foreach ($config['subcategories'] as $subcat => $subKeywords) {
                        foreach ($subKeywords as $subKeyword) {
                            if (str_contains($name, $subKeyword)) {
                                $result['subcategory'] = $subcat;
                                break 2;
                            }
                        }
                    }
                    break 2;
                }
            }
        }

        // Extract specifications based on category
        $result['specs'] = $this->extractSpecsFromName($name, $result['category']);

        return $result;
    }

    /**
     * Extract technical specifications from product name.
     *
     * @return array<string, mixed>
     */
    private function extractSpecsFromName(string $name, ?string $category): array
    {
        $specs = [];

        // Extract amperage (e.g., "16A", "16 A", "16 AMPERE")
        if (preg_match('/(\d+)\s*(A|AMP|AMPERE)\b/i', $name, $matches)) {
            $specs['rating_amps'] = (int) $matches[1];
        }

        // Extract poles (e.g., "1P", "2P", "3P", "4P", "1 POLE", "3 PHASE")
        if (preg_match('/(\d)\s*(P|POLE|PHASE)\b/i', $name, $matches)) {
            $specs['poles'] = (int) $matches[1];
        }

        // Extract curve type (e.g., "C16", "B10", "D32")
        if (preg_match('/\b([BCD])[\s-]?(\d+)/i', $name, $matches)) {
            $specs['curve'] = strtoupper($matches[1]);
            if (! isset($specs['rating_amps'])) {
                $specs['rating_amps'] = (int) $matches[2];
            }
        }

        // Extract breaking capacity (e.g., "6kA", "10 kA", "6000A")
        if (preg_match('/(\d+)\s*(K|KILO)?A\s*(BREAKING|CAPACITY)?/i', $name, $matches)) {
            $value = (int) $matches[1];
            if (stripos($matches[2] ?? '', 'K') !== false || $value < 100) {
                $specs['breaking_capacity_ka'] = $value;
            }
        }

        // Extract voltage (e.g., "220V", "380V", "400V")
        if (preg_match('/(\d{2,3})\s*V\b/i', $name, $matches)) {
            $specs['voltage'] = (int) $matches[1];
        }

        // Category-specific extractions
        if ($category === 'cable') {
            // Extract conductor size (e.g., "2.5mm2", "2.5 mm²", "4mm")
            if (preg_match('/(\d+(?:\.\d+)?)\s*mm(?:²|2)?/i', $name, $matches)) {
                $specs['conductor_size_mm2'] = (float) $matches[1];
            }

            // Extract cores (e.g., "3x2.5", "4 core", "3C")
            if (preg_match('/(\d)\s*(?:X|x|C|CORE)/i', $name, $matches)) {
                $specs['cores'] = (int) $matches[1];
            }
        }

        if ($category === 'contactor') {
            // Extract coil voltage (e.g., "220VAC", "COIL 220V")
            if (preg_match('/(?:COIL\s*)?(\d{2,3})\s*V?\s*AC/i', $name, $matches)) {
                $specs['coil_voltage'] = (int) $matches[1];
            }

            // Extract AC category (e.g., "AC3", "AC-3")
            if (preg_match('/AC[\s-]?(\d)/i', $name, $matches)) {
                $specs['ac_category'] = 'AC'.$matches[1];
            }
        }

        if ($category === 'busbar') {
            // Extract dimensions (e.g., "20x3mm", "30x5")
            if (preg_match('/(\d+)\s*[xX]\s*(\d+)\s*(?:mm)?/i', $name, $matches)) {
                $specs['width_mm'] = (int) $matches[1];
                $specs['thickness_mm'] = (int) $matches[2];
            }

            // Detect material
            if (str_contains($name, 'COPPER') || str_contains($name, 'TEMBAGA')) {
                $specs['material'] = 'copper';
            } elseif (str_contains($name, 'ALUMINIUM') || str_contains($name, 'ALUMINUM')) {
                $specs['material'] = 'aluminium';
            }
        }

        return $specs;
    }

    /**
     * Accept a mapping suggestion and create the mapping.
     */
    public function acceptMappingSuggestion(
        Product $product,
        ComponentStandard $standard,
        ?string $brandSku = null,
        bool $isPreferred = false
    ): ComponentBrandMapping {
        // Detect brand from product
        $parsedSpecs = $this->parseProductName($product->name);
        $brand = $parsedSpecs['brand'] ?? $product->brand ?? 'other';

        return ComponentBrandMapping::create([
            'component_standard_id' => $standard->id,
            'brand' => $brand,
            'product_id' => $product->id,
            'brand_sku' => $brandSku ?? $product->sku,
            'is_preferred' => $isPreferred,
            'is_verified' => false, // Needs manual verification
        ]);
    }

    /**
     * Bulk accept mapping suggestions.
     *
     * @param  array<int, array{product_id: int, component_standard_id: int, brand_sku?: string}>  $mappings
     * @return array{created: int, skipped: int, errors: array}
     */
    public function bulkAcceptMappingSuggestions(array $mappings): array
    {
        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($mappings as $mapping) {
            try {
                $product = Product::find($mapping['product_id']);
                $standard = ComponentStandard::find($mapping['component_standard_id']);

                if (! $product || ! $standard) {
                    $skipped++;
                    $errors[] = "Product or Standard not found for product_id: {$mapping['product_id']}";

                    continue;
                }

                // Check if mapping already exists
                $exists = ComponentBrandMapping::query()
                    ->where('product_id', $product->id)
                    ->where('component_standard_id', $standard->id)
                    ->exists();

                if ($exists) {
                    $skipped++;

                    continue;
                }

                $this->acceptMappingSuggestion(
                    $product,
                    $standard,
                    $mapping['brand_sku'] ?? null,
                    $mapping['is_preferred'] ?? false
                );

                $created++;
            } catch (\Exception $e) {
                $errors[] = "Error for product_id {$mapping['product_id']}: ".$e->getMessage();
            }
        }

        return [
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
