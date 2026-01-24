<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for MaterialRequisition queries.
 */
class MaterialRequisitionFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['requisition_number', 'workOrder.wo_number', 'workOrder.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'requested_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'requisition_number',
            'status',
            'requested_date',
            'required_date',
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
            'workOrder',
            'warehouse',
            'items',
            'items.product',
        ];
    }

    /**
     * Filter by work order ID.
     */
    public function workOrderId(int|string $value): void
    {
        $this->builder->where('work_order_id', $value);
    }

    /**
     * Filter by warehouse ID.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }
}
