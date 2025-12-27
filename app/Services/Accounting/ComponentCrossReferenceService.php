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
}
