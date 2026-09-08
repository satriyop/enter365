<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasStatusFilter;

class StockTransferFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasStatusFilter;

    /**
     * @return list<string>
     */
    protected function getSearchableFields(): array
    {
        return ['transfer_number', 'source_document', 'notes'];
    }

    protected function getDateField(): string
    {
        return 'scheduled_date';
    }

    /**
     * @return list<string>
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'transfer_number',
            'scheduled_date',
            'status',
            'operation_type',
            'created_at',
        ];
    }

    /**
     * @return list<string>
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'fromWarehouse',
            'toWarehouse',
            'contact',
            'items',
            'items.product',
            'createdByUser',
        ];
    }

    public function operationType(string $value): void
    {
        $this->builder->where('operation_type', $value);
    }

    public function warehouseId(int|string $value): void
    {
        $this->builder->where(function ($query) use ($value) {
            $query->where('from_warehouse_id', $value)
                ->orWhere('to_warehouse_id', $value);
        });
    }
}
