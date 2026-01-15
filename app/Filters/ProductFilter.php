<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Product queries.
 *
 * Supported filters:
 * - type: Product type (product, service)
 * - category_id: Filter by category
 * - is_active: Active status
 * - is_sellable: Sellable flag
 * - is_purchasable: Purchasable flag
 * - track_inventory: Inventory tracking flag
 * - low_stock: Only low stock products
 * - search: Search by name, SKU, or barcode
 */
class ProductFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['name', 'sku', 'barcode'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortField(): ?string
    {
        return 'name';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultSortDirection(): string
    {
        return 'asc';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'name',
            'sku',
            'type',
            'selling_price',
            'purchase_price',
            'stock_quantity',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Filter by product type.
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    /**
     * Filter by category.
     */
    public function categoryId(int|string $value): void
    {
        $this->builder->where('category_id', $value);
    }

    /**
     * Filter by active status.
     */
    public function isActive(bool|string $value): void
    {
        $this->builder->where('is_active', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by sellable flag.
     */
    public function isSellable(bool|string $value): void
    {
        $this->builder->where('is_sellable', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by purchasable flag.
     */
    public function isPurchasable(bool|string $value): void
    {
        $this->builder->where('is_purchasable', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter by inventory tracking flag.
     */
    public function trackInventory(bool|string $value): void
    {
        $this->builder->where('track_inventory', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * Filter only low stock products.
     */
    public function lowStock(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->lowStock();
        }
    }
}
