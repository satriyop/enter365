<?php

declare(strict_types=1);

namespace App\Filters\Traits;

/**
 * Trait for status filtering.
 *
 * Provides common status filtering methods:
 * - status (single status)
 * - statuses (multiple statuses)
 * - excludeStatus
 *
 * Use in filter classes that need status filtering.
 */
trait HasStatusFilter
{
    /**
     * Get the status field name.
     * Override in filter class to customize.
     */
    protected function getStatusField(): string
    {
        return 'status';
    }

    /**
     * Filter by single status.
     */
    public function status(string $value): void
    {
        $this->builder->where($this->getStatusField(), $value);
    }

    /**
     * Filter by multiple statuses (comma-separated or array).
     *
     * @param  string|array<string>  $value
     */
    public function statuses(string|array $value): void
    {
        $statuses = is_array($value) ? $value : explode(',', $value);
        $statuses = array_map('trim', $statuses);

        $this->builder->whereIn($this->getStatusField(), $statuses);
    }

    /**
     * Exclude a specific status.
     */
    public function excludeStatus(string $value): void
    {
        $this->builder->where($this->getStatusField(), '!=', $value);
    }

    /**
     * Filter active records (status-agnostic helper).
     */
    public function isActive(bool|string $value): void
    {
        $isActive = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($isActive) {
            $this->builder->where('is_active', true);
        } else {
            $this->builder->where('is_active', false);
        }
    }
}
