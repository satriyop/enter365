<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\DocumentStatus;
use App\Filters\Traits\HasDateRangeFilter;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Project queries.
 *
 * Supported filters:
 * - status: Project status
 * - priority: Priority level
 * - contact_id: Filter by contact/customer
 * - manager_id: Filter by project manager
 * - start_date: Project start date from
 * - end_date: Project start date to
 * - overdue_only: Only overdue projects
 * - over_budget_only: Only over-budget projects
 * - search: Search by project number, name, or contact name
 */
class ProjectFilter extends QueryFilter
{
    use HasDateRangeFilter;
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['project_number', 'name', 'contact.name'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getDateField(): string
    {
        return 'start_date';
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'project_number',
            'name',
            'status',
            'priority',
            'start_date',
            'end_date',
            'budget_amount',
            'total_cost',
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
            'contact',
            'quotation',
            'manager',
            'creator',
            'costs',
            'costs.bill',
            'costs.product',
            'revenues',
            'revenues.invoice',
            'revenues.downPayment',
            'workOrders',
        ];
    }

    /**
     * Filter by priority.
     */
    public function priority(string $value): void
    {
        $this->builder->where('priority', $value);
    }

    /**
     * Filter by contact/customer.
     */
    public function contactId(int|string $value): void
    {
        $this->builder->where('contact_id', $value);
    }

    /**
     * Filter by project manager.
     */
    public function managerId(int|string $value): void
    {
        $this->builder->where('manager_id', $value);
    }

    /**
     * Filter by end date.
     */
    public function endDate(string $value): void
    {
        $this->builder->where('start_date', '<=', $value);
    }

    /**
     * Filter only overdue projects.
     */
    public function overdueOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder
                ->where('status', DocumentStatus::InProgress)
                ->where('end_date', '<', now());
        }
    }

    /**
     * Filter only over-budget projects.
     */
    public function overBudgetOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder->whereColumn('total_cost', '>', 'budget_amount');
        }
    }
}
