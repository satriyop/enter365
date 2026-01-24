<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Warehouse queries.
 */
class WarehouseFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['code', 'name', 'address', 'contact_person'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'code', 'name', 'is_default', 'is_active', 'created_at', 'updated_at'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'productStocks',
            'productStocks.product',
        ];
    }

    /**
     * Filter by default status.
     */
    public function isDefault(bool|string $value): void
    {
        $this->builder->where('is_default', filter_var($value, FILTER_VALIDATE_BOOLEAN));
    }
}
