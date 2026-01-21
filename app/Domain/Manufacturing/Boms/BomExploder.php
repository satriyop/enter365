<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\Boms;

use App\Enums\DocumentStatus;
use App\Models\Manufacturing\Bom;

/**
 * Explodes multi-level BOMs into flat or hierarchical structure.
 *
 * Use cases:
 * - MRP: Calculate total material requirements across all BOM levels
 * - Costing: Calculate total costs including sub-assembly materials
 * - Planning: Visualize complete product structure
 *
 * The exploder handles:
 * - Multi-level BOMs (products that are themselves BOMs)
 * - Waste percentage calculations
 * - Output quantity normalization
 * - Circular reference detection
 */
class BomExploder
{
    private int $maxDepth = 10;

    private bool $includeInactive = false;

    /** @var array<int> Track visited BOMs to prevent circular references */
    private array $visitedBoms = [];

    /**
     * Explode BOM into flat list of raw materials.
     *
     * Returns only the lowest-level materials (leaf nodes), aggregating
     * quantities for materials that appear at multiple levels.
     *
     * @return array<int, array{
     *     product_id: int,
     *     description: string,
     *     quantity: float,
     *     unit: string,
     *     unit_cost: int,
     *     level: int,
     *     path: string
     * }>
     */
    public function explodeFlat(Bom $bom, float $quantity = 1): array
    {
        $this->visitedBoms = [];
        $result = [];
        $this->explodeRecursive($bom, $quantity, 0, $bom->name ?? "BOM-{$bom->id}", $result);

        return array_values($result);
    }

    /**
     * Explode BOM into hierarchical structure.
     *
     * Preserves the tree structure, showing sub-assemblies and their
     * components at each level.
     *
     * @return array<int, array{
     *     product_id: int,
     *     description: string,
     *     quantity: float,
     *     unit: string,
     *     unit_cost: int,
     *     is_subassembly: bool,
     *     children: array
     * }>
     */
    public function explodeHierarchical(Bom $bom, float $quantity = 1): array
    {
        $this->visitedBoms = [];

        return $this->explodeRecursiveHierarchical($bom, $quantity, 0);
    }

    /**
     * Calculate total material requirements (aggregated).
     *
     * Useful for MRP and purchasing - returns unique materials with
     * their total required quantities.
     *
     * @return array<int, array{
     *     product_id: int,
     *     product_name: string,
     *     total_quantity: float,
     *     unit: string,
     *     total_cost: int
     * }>
     */
    public function calculateRequirements(Bom $bom, float $quantity = 1): array
    {
        $flat = $this->explodeFlat($bom, $quantity);

        $requirements = [];
        foreach ($flat as $item) {
            $productId = $item['product_id'];
            if (! isset($requirements[$productId])) {
                $requirements[$productId] = [
                    'product_id' => $productId,
                    'product_name' => $item['description'],
                    'total_quantity' => 0.0,
                    'unit' => $item['unit'],
                    'total_cost' => 0,
                ];
            }
            $requirements[$productId]['total_quantity'] += $item['quantity'];
            $requirements[$productId]['total_cost'] += (int) round($item['quantity'] * $item['unit_cost']);
        }

        return array_values($requirements);
    }

    /**
     * Calculate total cost of producing the BOM.
     *
     * Sums up all material costs at the lowest level.
     */
    public function calculateTotalCost(Bom $bom, float $quantity = 1): int
    {
        $requirements = $this->calculateRequirements($bom, $quantity);

        return array_reduce(
            $requirements,
            fn (int $carry, array $item) => $carry + $item['total_cost'],
            0
        );
    }

    /**
     * Check if BOM has circular references.
     */
    public function hasCircularReference(Bom $bom): bool
    {
        $this->visitedBoms = [];

        return $this->detectCircular($bom);
    }

    /**
     * Set maximum depth for explosion.
     */
    public function setMaxDepth(int $depth): self
    {
        $this->maxDepth = max(1, $depth);

        return $this;
    }

    /**
     * Include inactive BOMs in explosion.
     */
    public function includeInactive(bool $include = true): self
    {
        $this->includeInactive = $include;

        return $this;
    }

    private function explodeRecursive(
        Bom $bom,
        float $multiplier,
        int $level,
        string $path,
        array &$result
    ): void {
        if ($level > $this->maxDepth) {
            return;
        }

        // Circular reference detection
        if (in_array($bom->id, $this->visitedBoms, true)) {
            return;
        }
        $this->visitedBoms[] = $bom->id;

        // Calculate output multiplier (how many BOMs we need)
        $outputQty = (float) $bom->output_quantity ?: 1.0;
        $outputMultiplier = $multiplier / $outputQty;

        foreach ($bom->materialItems as $item) {
            $effectiveQty = $item->getEffectiveQuantity() * $outputMultiplier;
            $itemPath = $path.' > '.($item->description ?: "Item-{$item->id}");

            // Check if this material has its own BOM (sub-assembly)
            $subBom = $this->findActiveBomForProduct($item->product_id);

            if ($subBom !== null) {
                // Recurse into sub-assembly
                $this->explodeRecursive($subBom, $effectiveQty, $level + 1, $itemPath, $result);
            } else {
                // Raw material - add to results (aggregate by product_id)
                $productId = $item->product_id;
                $key = $productId;

                if (! isset($result[$key])) {
                    $result[$key] = [
                        'product_id' => $productId,
                        'description' => $item->description ?: $item->product?->name ?? 'Unknown',
                        'quantity' => 0.0,
                        'unit' => $item->unit ?? 'pcs',
                        'unit_cost' => $item->unit_cost ?? 0,
                        'level' => $level,
                        'path' => $itemPath,
                    ];
                }
                $result[$key]['quantity'] += $effectiveQty;
            }
        }

        // Remove this BOM from visited (for multiple paths to same BOM)
        $this->visitedBoms = array_filter(
            $this->visitedBoms,
            fn ($id) => $id !== $bom->id
        );
    }

    /**
     * @return array<int, array{
     *     product_id: int,
     *     description: string,
     *     quantity: float,
     *     unit: string,
     *     unit_cost: int,
     *     is_subassembly: bool,
     *     children: array
     * }>
     */
    private function explodeRecursiveHierarchical(
        Bom $bom,
        float $multiplier,
        int $level
    ): array {
        if ($level > $this->maxDepth) {
            return [];
        }

        // Circular reference detection
        if (in_array($bom->id, $this->visitedBoms, true)) {
            return [];
        }
        $this->visitedBoms[] = $bom->id;

        $outputQty = (float) $bom->output_quantity ?: 1.0;
        $outputMultiplier = $multiplier / $outputQty;

        $items = [];

        foreach ($bom->materialItems as $item) {
            $effectiveQty = $item->getEffectiveQuantity() * $outputMultiplier;

            $subBom = $this->findActiveBomForProduct($item->product_id);
            $children = [];

            if ($subBom !== null) {
                $children = $this->explodeRecursiveHierarchical($subBom, $effectiveQty, $level + 1);
            }

            $items[] = [
                'product_id' => $item->product_id,
                'description' => $item->description ?: $item->product?->name ?? 'Unknown',
                'quantity' => $effectiveQty,
                'unit' => $item->unit ?? 'pcs',
                'unit_cost' => $item->unit_cost ?? 0,
                'is_subassembly' => $subBom !== null,
                'children' => $children,
            ];
        }

        // Remove from visited
        $this->visitedBoms = array_filter(
            $this->visitedBoms,
            fn ($id) => $id !== $bom->id
        );

        return $items;
    }

    private function detectCircular(Bom $bom): bool
    {
        if (in_array($bom->id, $this->visitedBoms, true)) {
            return true;
        }

        $this->visitedBoms[] = $bom->id;

        foreach ($bom->materialItems as $item) {
            $subBom = $this->findActiveBomForProduct($item->product_id);

            if ($subBom !== null && $this->detectCircular($subBom)) {
                return true;
            }
        }

        // Remove from visited for other paths
        $this->visitedBoms = array_filter(
            $this->visitedBoms,
            fn ($id) => $id !== $bom->id
        );

        return false;
    }

    private function findActiveBomForProduct(?int $productId): ?Bom
    {
        if ($productId === null) {
            return null;
        }

        $query = Bom::where('product_id', $productId);

        if (! $this->includeInactive) {
            $query->where('status', DocumentStatus::Active);
        }

        return $query->first();
    }
}
