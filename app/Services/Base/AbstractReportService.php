<?php

declare(strict_types=1);

namespace App\Services\Base;

use Illuminate\Support\Collection;

/**
 * Abstract base class for report generation services.
 *
 * Provides common patterns for generating reports:
 * - Date range filtering
 * - Currency handling
 * - Data aggregation
 * - Export formatting
 */
abstract class AbstractReportService
{
    /**
     * Generate the report data.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    abstract public function generate(array $parameters = []): array;

    /**
     * Get the base query for the report.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    abstract protected function getBaseQuery();

    /**
     * Parse date range from parameters.
     *
     * @param  array<string, mixed>  $parameters
     * @return array{start: \Carbon\Carbon, end: \Carbon\Carbon}
     */
    protected function parseDateRange(array $parameters): array
    {
        $start = isset($parameters['start_date'])
            ? now()->parse($parameters['start_date'])->startOfDay()
            : now()->startOfMonth();

        $end = isset($parameters['end_date'])
            ? now()->parse($parameters['end_date'])->endOfDay()
            : now()->endOfMonth();

        return compact('start', 'end');
    }

    /**
     * Apply date range filter to query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array{start: \Carbon\Carbon, end: \Carbon\Carbon}  $dateRange
     * @return \Illuminate\Database\Query\Builder
     */
    protected function applyDateFilter($query, string $dateField, array $dateRange)
    {
        return $query->whereBetween($dateField, [$dateRange['start'], $dateRange['end']]);
    }

    /**
     * Group data by period (daily, weekly, monthly).
     *
     * @param  Collection<int, mixed>  $data
     * @param  string  $period  'daily', 'weekly', 'monthly'
     * @return Collection<string, Collection<int, mixed>>
     */
    protected function groupByPeriod(Collection $data, string $dateField, string $period = 'monthly'): Collection
    {
        return $data->groupBy(function ($item) use ($dateField, $period) {
            $date = now()->parse($item->{$dateField});

            return match ($period) {
                'daily' => $date->format('Y-m-d'),
                'weekly' => $date->startOfWeek()->format('Y-m-d'),
                'monthly' => $date->format('Y-m'),
                default => $date->format('Y-m'),
            };
        });
    }

    /**
     * Calculate percentage of total.
     */
    protected function calculatePercentage(float $value, float $total): float
    {
        if ($total == 0) {
            return 0;
        }

        return round(($value / $total) * 100, 2);
    }

    /**
     * Format currency value.
     */
    protected function formatCurrency(float $value, string $currency = 'IDR'): string
    {
        return match ($currency) {
            'IDR' => 'Rp '.number_format($value, 0, ',', '.'),
            'USD' => '$'.number_format($value, 2, '.', ','),
            default => number_format($value, 2),
        };
    }

    /**
     * Build summary statistics.
     *
     * @param  Collection<int, mixed>  $data
     * @return array<string, float>
     */
    protected function buildSummary(Collection $data, string $valueField): array
    {
        $values = $data->pluck($valueField)->filter();

        return [
            'count' => $data->count(),
            'total' => $values->sum(),
            'average' => $values->avg() ?? 0,
            'min' => $values->min() ?? 0,
            'max' => $values->max() ?? 0,
        ];
    }

    /**
     * Get report metadata.
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    protected function getMetadata(array $parameters): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'generated_by' => auth()->user()?->name ?? 'System',
            'parameters' => $parameters,
        ];
    }
}
