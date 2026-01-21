<?php

declare(strict_types=1);

namespace App\Contracts\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;

/**
 * Interface for BOM (Bill of Materials) service operations.
 */
interface BomServiceInterface
{
    /**
     * Create a new BOM with items.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Bom;

    /**
     * Update an existing BOM.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Bom $bom, array $data): Bom;

    /**
     * Delete a BOM.
     */
    public function delete(Bom $bom): bool;

    /**
     * Activate a BOM.
     * User ID is obtained from OperationContext via $this->getUserId().
     */
    public function activate(Bom $bom): Bom;

    /**
     * Deactivate a BOM.
     */
    public function deactivate(Bom $bom): Bom;

    /**
     * Duplicate a BOM with new data.
     */
    public function duplicate(Bom $bom): Bom;

    /**
     * Get active BOM for a product.
     */
    public function getActiveForProduct(Product $product): ?Bom;

    /**
     * Calculate production cost for a given quantity.
     *
     * @return array<string, mixed>
     */
    public function calculateProductionCost(Bom $bom, float $quantity): array;

    /**
     * Get BOM statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array;
}
