<?php

declare(strict_types=1);

namespace App\Filters;

use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for WorkOrder queries.
 *
 * Supported filters:
 * - status: Single status filter
 * - type: Work order type (production, installation)
 * - priority: Priority level
 * - project_id: Filter by project
 * - bom_id: Filter by BOM
 * - product_id: Filter by product
 * - warehouse_id: Filter by warehouse
 * - start_date: Planned start date from
 * - end_date: Planned end date to
 * - parent_only: Only parent work orders (no sub-WOs)
 * - search: Search by WO number or name
 */
class WorkOrderFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['wo_number', 'name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'planned_start_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'wo_number',
            'name',
            'status',
            'priority',
            'planned_start_date',
            'planned_end_date',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Filter by work order type.
     */
    public function type(string $value): void
    {
        $this->builder->where('type', $value);
    }

    /**
     * Filter by priority.
     */
    public function priority(string $value): void
    {
        $this->builder->where('priority', $value);
    }

    /**
     * Filter by project.
     */
    public function projectId(int|string $value): void
    {
        $this->builder->where('project_id', $value);
    }

    /**
     * Filter by BOM.
     */
    public function bomId(int|string $value): void
    {
        $this->builder->where('bom_id', $value);
    }

    /**
     * Filter by product.
     */
    public function productId(int|string $value): void
    {
        $this->builder->where('product_id', $value);
    }

    /**
     * Filter by warehouse.
     */
    public function warehouseId(int|string $value): void
    {
        $this->builder->where('warehouse_id', $value);
    }

    /**
     * Filter by planned end date.
     */
    public function endDate(string $value): void
    {
        $this->builder->where('planned_end_date', '<=', $value);
    }

    /**
     * Filter only parent work orders (exclude sub-WOs).
     */
    public function parentOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereNull('parent_work_order_id');
        }
    }
}
