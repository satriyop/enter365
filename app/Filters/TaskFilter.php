<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\DocumentStatus;
use App\Filters\Traits\HasSearchFilter;
use App\Filters\Traits\HasStatusFilter;

/**
 * Filter for Task queries.
 *
 * Supported filters:
 * - status: Task status (todo, in_progress, done, cancelled)
 * - priority: Priority level
 * - assigned_to: Filter by assignee
 * - parent_id: Filter by parent task
 * - overdue_only: Only overdue tasks
 * - search: Search by task_number, title
 */
class TaskFilter extends QueryFilter
{
    use HasSearchFilter;
    use HasStatusFilter;

    /**
     * {@inheritdoc}
     */
    protected function getSearchableFields(): array
    {
        return ['task_number', 'title'];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedSortFields(): array
    {
        return [
            'id',
            'task_number',
            'title',
            'status',
            'priority',
            'due_date',
            'sort_order',
            'created_at',
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getAllowedIncludes(): array
    {
        return [
            'project',
            'parent',
            'subtasks',
            'subtasks.assignee',
            'assignee',
            'creator',
            'dependencies',
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
     * Filter by assigned user.
     */
    public function assignedTo(int|string $value): void
    {
        $this->builder->where('assigned_to', $value);
    }

    /**
     * Filter by parent task.
     */
    public function parentId(int|string $value): void
    {
        if ($value === 'null' || $value === '0') {
            $this->builder->whereNull('parent_id');
        } else {
            $this->builder->where('parent_id', $value);
        }
    }

    /**
     * Filter only overdue tasks.
     */
    public function overdueOnly(bool|string $value): void
    {
        if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
            $this->builder
                ->whereNotIn('status', [DocumentStatus::Done->value, DocumentStatus::Cancelled->value])
                ->where('due_date', '<', now());
        }
    }
}
