<?php

declare(strict_types=1);

namespace App\Filters;

/**
 * Filter for ProductStock queries.
 */
class ProductStockFilter extends QueryFilter
{
    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'product_id', 'warehouse_id', 'quantity', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'product',
            'warehouse',
        ];
    }

    /**
     * Filter by product ID.
     */
    public function productId(int|string $value): void
    {
        $this->builder->where('product_id', $value);
    }

    /**
     * Filter by warehouse ID.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }

    /**
     * Filter by quantity.
     */
    public function minQuantity(float $value): void
    {
        $this->builder->where('quantity', '>=', $value);
    }
}
