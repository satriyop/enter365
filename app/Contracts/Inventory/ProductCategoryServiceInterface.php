<?php

declare(strict_types=1);

namespace App\Contracts\Inventory;

use App\Models\Inventory\ProductCategory;

/**
 * Interface for Product Category management operations.
 */
interface ProductCategoryServiceInterface
{
    /**
     * Create a new product category.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductCategory;

    /**
     * Update an existing product category.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ProductCategory $category, array $data): ProductCategory;

    /**
     * Delete a product category.
     */
    public function delete(ProductCategory $category): void;
}
