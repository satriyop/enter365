<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;

/**
 * Filter for InventoryMovement queries.
 */
class InventoryMovementFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['movement_number', 'product.name', 'product.sku', 'notes'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'movement_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'movement_number', 'movement_date', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'product',
            'warehouse',
            'transferWarehouse',
            'createdByUser',
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
     * Filter by movement type.
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }
}
