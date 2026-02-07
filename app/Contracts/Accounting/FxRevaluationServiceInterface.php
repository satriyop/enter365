<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\JournalEntry;
use Illuminate\Support\Collection;

interface FxRevaluationServiceInterface
{
    /**
     * Revalue all open foreign-currency AR/AP at the closing rate.
     *
     * @param  string  $date  Balance sheet date (YYYY-MM-DD)
     * @param  array<string, float>  $closingRates  Currency => rate map (e.g. ['USD' => 15800.0])
     * @param  bool  $withReversal  Auto-create reversal JE dated next day
     * @return Collection<int, JournalEntry> Created journal entries (revaluation + optional reversal)
     */
    public function revalue(string $date, array $closingRates, bool $withReversal = false): Collection;

    /**
     * Preview revaluation without persisting.
     *
     * @param  string  $date  Balance sheet date
     * @param  array<string, float>  $closingRates  Currency => rate map
     * @return array{items: array<int, array{type: string, reference: string, currency: string, outstanding: int, booked_rate: float, closing_rate: float, booked_base: int, revalued_base: int, unrealized_fx: int}>, total_gain: int, total_loss: int}
     */
    public function preview(string $date, array $closingRates): array;
}
