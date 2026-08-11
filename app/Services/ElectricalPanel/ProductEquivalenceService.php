<?php

declare(strict_types=1);

namespace App\Services\ElectricalPanel;

use App\Models\Inventory\Product;
use App\Models\ElectricalPanel\ComponentBrandMapping;
use App\Models\ElectricalPanel\ComponentStandard;
use Illuminate\Support\Collection;

/**
 * Service for finding equivalent products across brands.
 *
 * Handles product equivalence discovery, partial matching, and spec-based searches.
 */
class ProductEquivalenceService
{
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
     * Calculate match score between two spec arrays.
     *
     * @param  array<string, mixed>  $source
     * @param  array<string, mixed>  $target
     */
    public function calculateMatchScore(array $source, array $target): int
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
    public function findSpecDifferences(array $source, array $target): array
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
