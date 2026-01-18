<?php

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;

/**
 * Interface for Product service operations.
 */
interface ProductServiceInterface
{
    /**
     * Create a new product.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Product;

    /**
     * Update a product.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product;

    /**
     * Delete a product.
     *
     * @return bool True if deleted, false if deactivated
     */
    public function delete(Product $product): bool;

    /**
     * Duplicate a product.
     */
    public function duplicate(Product $product): Product;

    /**
     * Activate a product.
     */
    public function activate(Product $product): Product;

    /**
     * Deactivate a product.
     */
    public function deactivate(Product $product): Product;

    /**
     * Adjust product stock with audit trail.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed> Adjustment result
     */
    public function adjustStock(Product $product, Warehouse $warehouse, array $data): array;
}
