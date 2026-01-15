<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomVariantGroup;

/**
 * Interface for BOM Variant Group service operations.
 */
interface BomVariantGroupServiceInterface
{
    /**
     * Create a new BOM variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BomVariantGroup;

    /**
     * Update a BOM variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(BomVariantGroup $group, array $data): BomVariantGroup;

    /**
     * Delete a BOM variant group.
     */
    public function delete(BomVariantGroup $group): bool;

    /**
     * Add a BOM to a variant group.
     *
     * @param  array<string, mixed>  $data
     */
    public function addBom(BomVariantGroup $group, Bom $bom, array $data = []): Bom;

    /**
     * Remove a BOM from a variant group.
     */
    public function removeBom(BomVariantGroup $group, Bom $bom): Bom;

    /**
     * Set a BOM as the primary variant in a group.
     */
    public function setPrimaryVariant(BomVariantGroup $group, Bom $bom): Bom;

    /**
     * Reorder variants in a group.
     *
     * @param  array<int>  $bomIdOrder
     */
    public function reorderVariants(BomVariantGroup $group, array $bomIdOrder): BomVariantGroup;

    /**
     * Get comparison data for all variants in a group.
     *
     * @return array<string, mixed>
     */
    public function getComparisonData(BomVariantGroup $group): array;

    /**
     * Get detailed comparison data for all variants.
     *
     * @return array<string, mixed>
     */
    public function getDetailedComparison(BomVariantGroup $group): array;

    /**
     * Create a new variant BOM from an existing BOM.
     *
     * @param  array<string, mixed>  $variantData
     */
    public function createVariantFromBom(BomVariantGroup $group, Bom $sourceBom, array $variantData): Bom;
}
