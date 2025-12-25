<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinancialReportService
{
    public function __construct(
        private AccountBalanceService $balanceService
    ) {}

    /**
     * Generate Balance Sheet (Laporan Posisi Keuangan).
     *
     * @return array{
     *     as_of_date: string,
     *     assets: array{
     *         current: Collection,
     *         fixed: Collection,
     *         total: int
     *     },
     *     liabilities: array{
     *         current: Collection,
     *         long_term: Collection,
     *         total: int
     *     },
     *     equity: array{
     *         items: Collection,
     *         total: int
     *     },
     *     total_liabilities_equity: int
     * }
     */
    public function getBalanceSheet(?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('type', [Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY])
            ->orderBy('code')
            ->get();

        $balanceItems = $accounts->map(function ($account) use ($asOfDate) {
            $balance = $account->getBalance($asOfDate);
            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'balance' => $balance,
            ];
        })->filter(fn ($item) => $item->balance != 0);

        // Group by type
        $currentAssets = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_ASSET && $i->subtype === Account::SUBTYPE_CURRENT_ASSET);
        $fixedAssets = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_ASSET && $i->subtype === Account::SUBTYPE_FIXED_ASSET);
        $currentLiabilities = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_LIABILITY && $i->subtype === Account::SUBTYPE_CURRENT_LIABILITY);
        $longTermLiabilities = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_LIABILITY && $i->subtype === Account::SUBTYPE_LONG_TERM_LIABILITY);
        $equityItems = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_EQUITY);

        // Calculate net income and add to equity
        $netIncome = $this->calculateNetIncome($asOfDate);
        if ($netIncome != 0) {
            $equityItems->push((object) [
                'account_id' => null,
                'code' => null,
                'name' => 'Laba/Rugi Berjalan',
                'type' => Account::TYPE_EQUITY,
                'subtype' => Account::SUBTYPE_EQUITY,
                'balance' => $netIncome,
            ]);
        }

        $totalAssets = $currentAssets->sum('balance') + $fixedAssets->sum('balance');
        $totalLiabilities = $currentLiabilities->sum('balance') + $longTermLiabilities->sum('balance');
        $totalEquity = $equityItems->sum('balance');

        return [
            'as_of_date' => $asOfDate,
            'assets' => [
                'current' => $currentAssets->values(),
                'fixed' => $fixedAssets->values(),
                'total' => $totalAssets,
            ],
            'liabilities' => [
                'current' => $currentLiabilities->values(),
                'long_term' => $longTermLiabilities->values(),
                'total' => $totalLiabilities,
            ],
            'equity' => [
                'items' => $equityItems->values(),
                'total' => $totalEquity,
            ],
            'total_liabilities_equity' => $totalLiabilities + $totalEquity,
        ];
    }

    /**
     * Generate Income Statement (Laporan Laba Rugi).
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     revenue: array{
     *         operating: Collection,
     *         other: Collection,
     *         total: int
     *     },
     *     expenses: array{
     *         cost_of_goods: Collection,
     *         operating: Collection,
     *         other: Collection,
     *         total: int
     *     },
     *     gross_profit: int,
     *     operating_income: int,
     *     net_income: int
     * }
     */
    public function getIncomeStatement(?string $startDate = null, ?string $endDate = null): array
    {
        $endDate = $endDate ?? now()->toDateString();
        $startDate = $startDate ?? now()->startOfYear()->toDateString();

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->orderBy('code')
            ->get();

        $items = $accounts->map(function ($account) use ($startDate, $endDate) {
            $balance = $this->getAccountBalanceForPeriod($account, $startDate, $endDate);
            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'balance' => abs($balance),
            ];
        })->filter(fn ($item) => $item->balance != 0);

        // Revenue
        $operatingRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OPERATING_REVENUE);
        $otherRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OTHER_REVENUE);
        $totalRevenue = $operatingRevenue->sum('balance') + $otherRevenue->sum('balance');

        // Expenses
        $costOfGoods = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && str_starts_with($i->code, '5-1'));
        $operatingExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OPERATING_EXPENSE && !str_starts_with($i->code, '5-1'));
        $otherExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OTHER_EXPENSE);
        $totalExpenses = $costOfGoods->sum('balance') + $operatingExpense->sum('balance') + $otherExpense->sum('balance');

        $grossProfit = $operatingRevenue->sum('balance') - $costOfGoods->sum('balance');
        $operatingIncome = $grossProfit - $operatingExpense->sum('balance');
        $netIncome = $totalRevenue - $totalExpenses;

        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'revenue' => [
                'operating' => $operatingRevenue->values(),
                'other' => $otherRevenue->values(),
                'total' => $totalRevenue,
            ],
            'expenses' => [
                'cost_of_goods' => $costOfGoods->values(),
                'operating' => $operatingExpense->values(),
                'other' => $otherExpense->values(),
                'total' => $totalExpenses,
            ],
            'gross_profit' => $grossProfit,
            'operating_income' => $operatingIncome,
            'net_income' => $netIncome,
        ];
    }

    /**
     * Get General Ledger (Buku Besar).
     */
    public function getGeneralLedger(?string $startDate = null, ?string $endDate = null): Collection
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return $accounts->map(function ($account) use ($startDate, $endDate) {
            $ledger = $this->balanceService->getLedger($account, $startDate, $endDate);
            $closingBalance = $ledger->isNotEmpty() ? $ledger->last()->balance : $account->opening_balance;

            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'opening_balance' => $account->opening_balance,
                'entries' => $ledger,
                'closing_balance' => $closingBalance,
            ];
        })->filter(fn ($item) => $item->entries->isNotEmpty());
    }

    /**
     * Calculate net income for the period.
     */
    private function calculateNetIncome(?string $asOfDate = null): int
    {
        $startOfYear = now()->startOfYear()->toDateString();
        $endDate = $asOfDate ?? now()->toDateString();

        $incomeStatement = $this->getIncomeStatement($startOfYear, $endDate);
        return $incomeStatement['net_income'];
    }

    /**
     * Get account balance for a specific period.
     */
    private function getAccountBalanceForPeriod(Account $account, string $startDate, string $endDate): int
    {
        $movements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('jel.account_id', $account->id)
            ->where('je.is_posted', true)
            ->whereBetween('je.entry_date', [$startDate, $endDate])
            ->whereNull('je.deleted_at')
            ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
            ->first();

        $totalDebit = (int) ($movements->total_debit ?? 0);
        $totalCredit = (int) ($movements->total_credit ?? 0);

        return $account->isDebitNormal()
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }
}
