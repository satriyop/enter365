<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\Inventory\ProductCategory;

class ProductCategoryService
{
    /**
     * Create a new product category.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): ProductCategory
    {
        // Generate code if not provided
        if (empty($data['code'])) {
            $data['code'] = ProductCategory::generateCode($data['parent_id'] ?? null);
        }

        // Set defaults
        $data['is_active'] = $data['is_active'] ?? true;
        $data['sort_order'] = $data['sort_order'] ?? 0;

        $category = ProductCategory::create($data);

        return $category->load('parent');
    }

    /**
     * Update a product category.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(ProductCategory $category, array $data): ProductCategory
    {
        $category->update($data);

        return $category->fresh('parent');
    }

    /**
     * Delete a product category.
     *
     * Validates that the category has no children or products before deletion.
     *
     * @throws \App\Exceptions\Domain\ValidationException
     */
    public function delete(ProductCategory $category): void
    {
        if ($category->hasChildren()) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'category',
                'Kategori tidak bisa dihapus karena memiliki sub-kategori.'
            );
        }

        if ($category->products()->exists()) {
            throw \App\Exceptions\Domain\ValidationException::invalid(
                'category',
                'Kategori tidak bisa dihapus karena memiliki produk.'
            );
        }

        $category->delete();
    }
}
