<?php

declare(strict_types=1);

namespace App\Contracts\Accounting\Strategies;

use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;

/**
 * Strategy for year-end closing process.
 *
 * Implementations determine how temporary accounts (revenue, expense, dividends)
 * are closed to retained earnings:
 *
 * - Direct: Close directly to retained earnings (2 entries)
 * - Income Summary: Close via income summary account (3 entries)
 */
interface ClosingStrategy
{
    /**
     * Close all revenue accounts to retained earnings (or income summary).
     *
     * Creates a journal entry that debits all revenue accounts
     * with credit balances to zero them out.
     *
     * @return JournalEntry|null Journal entry created, or null if no revenue to close
     */
    public function closeRevenueAccounts(FiscalPeriod $period): ?JournalEntry;

    /**
     * Close all expense accounts to retained earnings (or income summary).
     *
     * Creates a journal entry that credits all expense accounts
     * with debit balances to zero them out.
     *
     * @return JournalEntry|null Journal entry created, or null if no expenses to close
     */
    public function closeExpenseAccounts(FiscalPeriod $period): ?JournalEntry;

    /**
     * Close income summary to retained earnings (for income summary strategy).
     *
     * For direct closing strategy, this returns null as there's no income summary.
     *
     * @return JournalEntry|null Journal entry created, or null for direct strategy
     */
    public function closeIncomeSummary(FiscalPeriod $period): ?JournalEntry;

    /**
     * Close dividends/withdrawals account to retained earnings.
     *
     * Creates a journal entry that credits the dividends account
     * and debits retained earnings.
     *
     * @return JournalEntry|null Journal entry created, or null if no dividends to close
     */
    public function closeDividends(FiscalPeriod $period): ?JournalEntry;

    /**
     * Get the strategy identifier for configuration.
     */
    public function getIdentifier(): string;

    /**
     * Get human-readable strategy name.
     */
    public function getName(): string;
}
