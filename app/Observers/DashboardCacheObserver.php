<?php

declare(strict_types=1);

namespace App\Observers;

use App\Services\Sales\Quotation\QuotationStatisticsService;
use App\Services\Shared\DashboardQueryService;
use Illuminate\Database\Eloquent\Model;

/**
 * Flushes dashboard caches when financial data changes.
 *
 * Attached to models that feed dashboard aggregations:
 * Invoice, Bill, Payment, JournalEntry, Quotation.
 *
 * Uses a single-request debounce flag to avoid redundant
 * flushes when multiple models change in the same request.
 */
class DashboardCacheObserver
{
    private static bool $flushedThisRequest = false;

    public function saved(Model $model): void
    {
        $this->flushOnce();
    }

    public function deleted(Model $model): void
    {
        $this->flushOnce();
    }

    private function flushOnce(): void
    {
        if (self::$flushedThisRequest) {
            return;
        }

        DashboardQueryService::flushCache();
        QuotationStatisticsService::flushCache();

        self::$flushedThisRequest = true;
    }

    /**
     * Reset the debounce flag (useful in tests).
     */
    public static function resetDebounce(): void
    {
        self::$flushedThisRequest = false;
    }
}
