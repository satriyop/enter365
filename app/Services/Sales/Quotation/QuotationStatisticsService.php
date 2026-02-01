<?php

declare(strict_types=1);

namespace App\Services\Sales\Quotation;

use App\Domain\Sales\Quotations\Enums\QuotationOutcome;
use App\Domain\Sales\Quotations\QuotationStatistics;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Handles statistics and analytics for quotations.
 *
 * Extracted from QuotationService as part of the Coordinator Pattern refactoring.
 * This service wraps the QuotationStatistics domain class and provides
 * raw query methods for performance-critical aggregations.
 *
 * @see \App\Services\Sales\QuotationService The coordinator service
 * @see \App\Domain\Sales\Quotations\QuotationStatistics The domain statistics class
 */
class QuotationStatisticsService
{
    private const CACHE_TTL_MINUTES = 10;

    private const CACHE_PREFIX = 'quotation_stats:';

    public function __construct(
        private QuotationStatistics $statistics,
    ) {}

    /**
     * Get quotation statistics.
     *
     * @return array<string, mixed>
     */
    public function get(?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = self::CACHE_PREFIX.'overview:'.md5(($startDate ?? 'null').':'.($endDate ?? 'null'));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            return $this->statistics->get($startDate, $endDate);
        });
    }

    /**
     * Get win/loss statistics.
     *
     * @return array{won: int, lost: int, pending: int, win_rate: float}
     */
    public function getWinLoss(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'win_loss', now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            return $this->statistics->getWinLoss();
        });
    }

    /**
     * Get follow-up summary.
     *
     * @return array{needs_followup: int, overdue: int, high_priority: int}
     */
    public function getFollowUpSummary(): array
    {
        return Cache::remember(self::CACHE_PREFIX.'follow_up', now()->addMinutes(self::CACHE_TTL_MINUTES), function () {
            return $this->statistics->getFollowUpSummary();
        });
    }

    /**
     * Get dashboard data combining multiple statistics.
     *
     * @return array<string, mixed>
     */
    public function getDashboardData(?string $startDate = null, ?string $endDate = null): array
    {
        $cacheKey = self::CACHE_PREFIX.'dashboard:'.md5(($startDate ?? 'null').':'.($endDate ?? 'null'));

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            return [
                'overview' => $this->statistics->get($startDate, $endDate),
                'win_loss' => $this->statistics->getWinLoss(),
                'follow_up' => $this->statistics->getFollowUpSummary(),
            ];
        });
    }

    /**
     * Get outcome statistics for a date range.
     *
     * Uses DB::table() for performance - aggregation queries are more efficient
     * without Eloquent hydration overhead.
     *
     * @return object{
     *     total_quotations: int,
     *     won_count: int,
     *     lost_count: int,
     *     pending_count: int,
     *     won_value: int,
     *     lost_value: int,
     *     pending_value: int
     * }
     */
    public function getOutcomeStatistics(string $startDate, string $endDate): object
    {
        $cacheKey = self::CACHE_PREFIX.'outcomes:'.md5($startDate.':'.$endDate);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            return $this->computeOutcomeStatistics($startDate, $endDate);
        });
    }

    /**
     * Compute outcome statistics from database.
     */
    private function computeOutcomeStatistics(string $startDate, string $endDate): object
    {
        $stats = DB::table('quotations')
            ->whereBetween('quotation_date', [$startDate, $endDate])
            ->whereNull('deleted_at')
            ->select([
                DB::raw('COUNT(*) as total_quotations'),
                DB::raw('COUNT(CASE WHEN outcome = \'won\' THEN 1 END) as won_count'),
                DB::raw('COUNT(CASE WHEN outcome = \'lost\' THEN 1 END) as lost_count'),
                DB::raw('COUNT(CASE WHEN outcome IS NULL AND status NOT IN (\'draft\', \'expired\', \'converted\') THEN 1 END) as pending_count'),
                DB::raw('SUM(CASE WHEN outcome = \'won\' THEN total_amount ELSE 0 END) as won_value'),
                DB::raw('SUM(CASE WHEN outcome = \'lost\' THEN total_amount ELSE 0 END) as lost_value'),
                DB::raw('SUM(CASE WHEN outcome IS NULL AND status NOT IN (\'draft\', \'expired\', \'converted\') THEN total_amount ELSE 0 END) as pending_value'),
            ])
            ->first();

        // Cast to object with proper types (PHPStan hint)
        return (object) [
            'total_quotations' => (int) ($stats->total_quotations ?? 0),
            'won_count' => (int) ($stats->won_count ?? 0),
            'lost_count' => (int) ($stats->lost_count ?? 0),
            'pending_count' => (int) ($stats->pending_count ?? 0),
            'won_value' => (int) ($stats->won_value ?? 0),
            'lost_value' => (int) ($stats->lost_value ?? 0),
            'pending_value' => (int) ($stats->pending_value ?? 0),
        ];
    }

    /**
     * Get lost reasons breakdown.
     *
     * @return array<array{reason: string, label: string, count: int, value: int}>
     */
    public function getLostReasonsBreakdown(string $startDate, string $endDate): array
    {
        $cacheKey = self::CACHE_PREFIX.'lost_reasons:'.md5($startDate.':'.$endDate);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            $results = DB::table('quotations')
                ->whereBetween('quotation_date', [$startDate, $endDate])
                ->where('outcome', 'lost')
                ->whereNotNull('lost_reason')
                ->whereNull('deleted_at')
                ->groupBy('lost_reason')
                ->select([
                    'lost_reason',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_amount) as value'),
                ])
                ->get();

            return $results->map(fn ($row) => [
                'reason' => $row->lost_reason,
                'label' => QuotationOutcome::LOST_REASONS[$row->lost_reason] ?? $row->lost_reason,
                'count' => (int) $row->count,
                'value' => (int) $row->value,
            ])->toArray();
        });
    }

    /**
     * Get won reasons breakdown.
     *
     * @return array<array{reason: string, label: string, count: int, value: int}>
     */
    public function getWonReasonsBreakdown(string $startDate, string $endDate): array
    {
        $cacheKey = self::CACHE_PREFIX.'won_reasons:'.md5($startDate.':'.$endDate);

        return Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($startDate, $endDate) {
            $results = DB::table('quotations')
                ->whereBetween('quotation_date', [$startDate, $endDate])
                ->where('outcome', 'won')
                ->whereNotNull('won_reason')
                ->whereNull('deleted_at')
                ->groupBy('won_reason')
                ->select([
                    'won_reason',
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(total_amount) as value'),
                ])
                ->get();

            return $results->map(fn ($row) => [
                'reason' => $row->won_reason,
                'label' => QuotationOutcome::WON_REASONS[$row->won_reason] ?? $row->won_reason,
                'count' => (int) $row->count,
                'value' => (int) $row->value,
            ])->toArray();
        });
    }

    /**
     * Flush all quotation statistics cache entries.
     */
    public static function flushCache(): void
    {
        $patterns = [
            self::CACHE_PREFIX.'overview:',
            self::CACHE_PREFIX.'win_loss',
            self::CACHE_PREFIX.'follow_up',
            self::CACHE_PREFIX.'dashboard:',
            self::CACHE_PREFIX.'outcomes:',
            self::CACHE_PREFIX.'lost_reasons:',
            self::CACHE_PREFIX.'won_reasons:',
        ];

        foreach ($patterns as $key) {
            Cache::forget($key);
        }
    }
}
