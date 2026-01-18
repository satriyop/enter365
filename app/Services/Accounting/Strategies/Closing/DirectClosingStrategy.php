<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Closing;

use App\Contracts\Accounting\Strategies\ClosingStrategy;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\AccountLookupService;
use App\Services\Accounting\JournalService;

/**
 * Direct closing strategy: closes revenue/expense directly to retained earnings.
 *
 * Flow:
 * 1. closeRevenueAccounts: Dr Revenue → Cr Retained Earnings
 * 2. closeExpenseAccounts: Dr Retained Earnings → Cr Expense
 * 3. closeDividends: Dr Retained Earnings → Cr Dividends
 *
 * This is the simpler approach used by smaller businesses.
 * Net income is calculated as part of the closing entries.
 */
class DirectClosingStrategy implements ClosingStrategy
{
    public function __construct(
        private JournalService $journalService,
        private AccountLookupService $accountLookup
    ) {}

    public function closeRevenueAccounts(FiscalPeriod $period): ?JournalEntry
    {
        $retainedEarnings = $this->accountLookup->getRetainedEarningsAccount();

        $lines = [];
        $totalRevenue = 0;

        // Get all revenue account balances for this period
        $revenueBalances = $this->getAccountBalances($period, Account::TYPE_REVENUE);

        foreach ($revenueBalances as $balance) {
            if ($balance->balance == 0) {
                continue;
            }

            // Revenue has credit balance, so debit to close
            $lines[] = [
                'account_id' => $balance->account_id,
                'description' => "Penutupan pendapatan periode {$period->name}",
                'debit' => $balance->balance > 0 ? $balance->balance : 0,
                'credit' => $balance->balance < 0 ? abs($balance->balance) : 0,
            ];

            $totalRevenue += $balance->balance;
        }

        if (empty($lines)) {
            return null;
        }

        // Credit retained earnings with total revenue
        $lines[] = [
            'account_id' => $retainedEarnings->id,
            'description' => "Penutupan pendapatan ke laba ditahan periode {$period->name}",
            'debit' => $totalRevenue < 0 ? abs($totalRevenue) : 0,
            'credit' => $totalRevenue > 0 ? $totalRevenue : 0,
        ];

        return $this->journalService->createEntry([
            'entry_date' => $period->end_date->toDateString(),
            'description' => "Jurnal penutup pendapatan periode {$period->name}",
            'reference' => "CLOSE-REV-{$period->id}",
            'source_type' => JournalEntry::SOURCE_CLOSING,
            'fiscal_period_id' => $period->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function closeExpenseAccounts(FiscalPeriod $period): ?JournalEntry
    {
        $retainedEarnings = $this->accountLookup->getRetainedEarningsAccount();

        $lines = [];
        $totalExpense = 0;

        // Get all expense account balances for this period
        $expenseBalances = $this->getAccountBalances($period, Account::TYPE_EXPENSE);

        foreach ($expenseBalances as $balance) {
            if ($balance->balance == 0) {
                continue;
            }

            // Expense has debit balance, so credit to close
            $lines[] = [
                'account_id' => $balance->account_id,
                'description' => "Penutupan beban periode {$period->name}",
                'debit' => $balance->balance < 0 ? abs($balance->balance) : 0,
                'credit' => $balance->balance > 0 ? $balance->balance : 0,
            ];

            $totalExpense += $balance->balance;
        }

        if (empty($lines)) {
            return null;
        }

        // Debit retained earnings with total expense
        $lines[] = [
            'account_id' => $retainedEarnings->id,
            'description' => "Penutupan beban ke laba ditahan periode {$period->name}",
            'debit' => $totalExpense > 0 ? $totalExpense : 0,
            'credit' => $totalExpense < 0 ? abs($totalExpense) : 0,
        ];

        return $this->journalService->createEntry([
            'entry_date' => $period->end_date->toDateString(),
            'description' => "Jurnal penutup beban periode {$period->name}",
            'reference' => "CLOSE-EXP-{$period->id}",
            'source_type' => JournalEntry::SOURCE_CLOSING,
            'fiscal_period_id' => $period->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function closeIncomeSummary(FiscalPeriod $period): ?JournalEntry
    {
        // Direct closing doesn't use income summary account
        return null;
    }

    public function closeDividends(FiscalPeriod $period): ?JournalEntry
    {
        $retainedEarnings = $this->accountLookup->getRetainedEarningsAccount();
        $dividendsAccount = $this->accountLookup->getDividendsAccount();

        if ($dividendsAccount === null) {
            return null;
        }

        // Get dividends balance for this period
        $dividendsBalance = $this->getAccountBalance($period, $dividendsAccount->id);

        if ($dividendsBalance == 0) {
            return null;
        }

        $lines = [
            // Credit dividends to close (dividends has debit balance)
            [
                'account_id' => $dividendsAccount->id,
                'description' => "Penutupan dividen/prive periode {$period->name}",
                'debit' => 0,
                'credit' => $dividendsBalance,
            ],
            // Debit retained earnings
            [
                'account_id' => $retainedEarnings->id,
                'description' => "Penutupan dividen ke laba ditahan periode {$period->name}",
                'debit' => $dividendsBalance,
                'credit' => 0,
            ],
        ];

        return $this->journalService->createEntry([
            'entry_date' => $period->end_date->toDateString(),
            'description' => "Jurnal penutup dividen/prive periode {$period->name}",
            'reference' => "CLOSE-DIV-{$period->id}",
            'source_type' => JournalEntry::SOURCE_CLOSING,
            'fiscal_period_id' => $period->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function getIdentifier(): string
    {
        return 'direct';
    }

    public function getName(): string
    {
        return 'Penutupan Langsung';
    }

    /**
     * Get account balances for a specific account type in a period.
     *
     * @return \Illuminate\Support\Collection<int, object{account_id: int, balance: int}>
     */
    protected function getAccountBalances(FiscalPeriod $period, string $accountType): \Illuminate\Support\Collection
    {
        $balanceFormula = match ($accountType) {
            Account::TYPE_REVENUE => 'SUM(credit - debit)',
            Account::TYPE_EXPENSE => 'SUM(debit - credit)',
            default => 'SUM(debit - credit)',
        };

        return JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);
            })
            ->whereHas('account', function ($q) use ($accountType) {
                $q->where('type', $accountType);
            })
            ->select('account_id')
            ->selectRaw("{$balanceFormula} as balance")
            ->groupBy('account_id')
            ->get();
    }

    /**
     * Get balance for a specific account in a period.
     */
    protected function getAccountBalance(FiscalPeriod $period, int $accountId): int
    {
        $result = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);
            })
            ->where('account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->first();

        return (int) ($result->balance ?? 0);
    }
}
