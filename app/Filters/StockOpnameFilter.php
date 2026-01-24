<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Stock Opname queries.
 *
 * Supported filters:
 * - status: Stock Opname status
 * - warehouse_id: Filter by warehouse
 * - start_date: Opname date from
 * - end_date: Opname date to
 * - search: Search by opname number or name
 */
class StockOpnameFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['opname_number', 'name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'opname_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'opname_number',
            'opname_date',
            'status',
            'warehouse_id',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'warehouse',
            'items',
            'items.product',
            'countedByUser',
            'reviewedByUser',
            'approvedByUser',
            'createdByUser',
        ];
    }

    /**
     * Filter by warehouse.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }
}
