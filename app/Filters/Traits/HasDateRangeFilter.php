<?php

declare(strict_types=1);

namespace App\Filters\Traits;

/**
 * Trait for date range filtering.
 *
 * Provides common date filtering methods:
 * - startDate / endDate (generic)
 * - createdFrom / createdTo
 * - dateFrom / dateTo
 *
 * Use in filter classes that need date range filtering.
 */
trait HasDateRangeFilter
{
    /**
     * Get the date field to filter on.
     * Override in filter class to customize.
     */
    protected function getDateField(): string
    {
        return 'created_at';
    }

    /**
     * Filter by start date (generic).
     */
    public function startDate(string $value): void
    {
        $this->builder->where($this->getDateField(), '>=', $value);
    }

    /**
     * Filter by end date (generic).
     */
    public function endDate(string $value): void
    {
        $this->builder->where($this->getDateField(), '<=', $value.' 23:59:59');
    }

    /**
     * Filter by date from.
     */
    public function dateFrom(string $value): void
    {
        $this->startDate($value);
    }

    /**
     * Filter by date to.
     */
    public function dateTo(string $value): void
    {
        $this->endDate($value);
    }

    /**
     * Filter by created from date.
     */
    public function createdFrom(string $value): void
    {
        $this->builder->where('created_at', '>=', $value);
    }

    /**
     * Filter by created to date.
     */
    public function createdTo(string $value): void
    {
        $this->builder->where('created_at', '<=', $value.' 23:59:59');
    }

    /**
     * Filter by date range (expects comma-separated: "2024-01-01,2024-12-31").
     */
    public function dateRange(string $value): void
    {
        $dates = explode(',', $value);

        if (count($dates) === 2) {
            $this->builder->whereBetween($this->getDateField(), [
                trim($dates[0]),
                trim($dates[1]).' 23:59:59',
            ]);
        }
    }

    /**
     * Filter by month (expects: "2024-01").
     */
    public function month(string $value): void
    {
        $this->builder->whereRaw(
            "DATE_FORMAT({$this->getDateField()}, '%Y-%m') = ?",
            [$value]
        );
    }

    /**
     * Filter by year.
     */
    public function year(int|string $value): void
    {
        $this->builder->whereYear($this->getDateField(), $value);
    }
}
