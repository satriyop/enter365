<?php

namespace App\Services\Accounting\Reports\Financial;

use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;

class IncomeStatementReportService
{
    public function __construct(
        private AccountHierarchyBuilder $hierarchyBuilder
    ) {}

    /**
     * Generate Income Statement (Laporan Laba Rugi).
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     revenue: array<int, array{name: string, accounts: Collection, total: int}>,
     *     expenses: array<int, array{name: string, accounts: Collection, total: int}>,
     *     total_revenue: int,
     *     total_expenses: int,
     *     gross_profit: int,
     *     operating_income: int,
     *     net_income: int
     * }
     */
    public function getIncomeStatement(?string $startDate = null, ?string $endDate = null, bool $hierarchical = false): array
    {
        $endDate = $endDate ?? now()->toDateString();
        $startDate = $startDate ?? now()->startOfYear()->toDateString();

        $accounts = Account::query()
            ->whereIn('type', [Account::TYPE_REVENUE, Account::TYPE_EXPENSE])
            ->orderBy('code')
            ->get();

        // Bulk get movements for period
        $movements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.is_posted', true)
            ->whereBetween('je.entry_date', [$startDate, $endDate.' 23:59:59'])
            ->whereNull('je.deleted_at')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        $items = $accounts->map(function ($account) use ($movements) {
            $movement = $movements->get($account->id);
            $totalDebit = (int) ($movement->total_debit ?? 0);
            $totalCredit = (int) ($movement->total_credit ?? 0);

            $balance = $account->isDebitNormal()
                ? $totalDebit - $totalCredit
                : $totalCredit - $totalDebit;

            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'subtype' => $account->subtype,
                'parent_id' => $account->parent_id,
                'balance' => $balance,
            ];
        });

        if ($hierarchical) {
            $items = $this->hierarchyBuilder->build($items, $accounts);
        } else {
            $items = $items->filter(fn ($item) => $item->balance != 0);
        }

        $balanceKey = $hierarchical ? 'rollup_balance' : 'balance';

        // Revenue
        $operatingRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OPERATING_REVENUE);
        $otherRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OTHER_REVENUE);
        $uncategorizedRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE
            && $i->subtype !== Account::SUBTYPE_OPERATING_REVENUE
            && $i->subtype !== Account::SUBTYPE_OTHER_REVENUE);
        $totalRevenue = $operatingRevenue->sum($balanceKey) + $otherRevenue->sum($balanceKey) + $uncategorizedRevenue->sum($balanceKey);

        // Expenses
        $costOfGoods = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && str_starts_with($i->code, '5-1'));
        $operatingExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OPERATING_EXPENSE && ! str_starts_with($i->code, '5-1'));
        $otherExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OTHER_EXPENSE);
        $uncategorizedExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE
            && ! str_starts_with($i->code, '5-1')
            && $i->subtype !== Account::SUBTYPE_OPERATING_EXPENSE
            && $i->subtype !== Account::SUBTYPE_OTHER_EXPENSE);
        $totalExpenses = $costOfGoods->sum($balanceKey) + $operatingExpense->sum($balanceKey) + $otherExpense->sum($balanceKey) + $uncategorizedExpense->sum($balanceKey);

        $grossProfit = $operatingRevenue->sum($balanceKey) - $costOfGoods->sum($balanceKey);
        $operatingIncome = $grossProfit - $operatingExpense->sum($balanceKey);
        $netIncome = $totalRevenue - $totalExpenses;

        // Build sectioned arrays matching frontend IncomeStatementSection[] contract
        $revenueSections = collect();
        if ($operatingRevenue->isNotEmpty()) {
            $revenueSections->push([
                'name' => 'Pendapatan Operasional',
                'accounts' => $operatingRevenue->values(),
                'total' => $operatingRevenue->sum($balanceKey),
            ]);
        }
        if ($otherRevenue->isNotEmpty()) {
            $revenueSections->push([
                'name' => 'Pendapatan Lain-lain',
                'accounts' => $otherRevenue->values(),
                'total' => $otherRevenue->sum($balanceKey),
            ]);
        }
        if ($uncategorizedRevenue->isNotEmpty()) {
            $revenueSections->push([
                'name' => 'Pendapatan Lain-lain',
                'accounts' => $uncategorizedRevenue->values(),
                'total' => $uncategorizedRevenue->sum($balanceKey),
            ]);
        }

        $expenseSections = collect();
        if ($costOfGoods->isNotEmpty()) {
            $expenseSections->push([
                'name' => 'Harga Pokok Penjualan',
                'accounts' => $costOfGoods->values(),
                'total' => $costOfGoods->sum($balanceKey),
            ]);
        }
        if ($operatingExpense->isNotEmpty()) {
            $expenseSections->push([
                'name' => 'Beban Operasional',
                'accounts' => $operatingExpense->values(),
                'total' => $operatingExpense->sum($balanceKey),
            ]);
        }
        if ($otherExpense->isNotEmpty()) {
            $expenseSections->push([
                'name' => 'Beban Lain-lain',
                'accounts' => $otherExpense->values(),
                'total' => $otherExpense->sum($balanceKey),
            ]);
        }
        if ($uncategorizedExpense->isNotEmpty()) {
            $expenseSections->push([
                'name' => 'Beban Lain-lain',
                'accounts' => $uncategorizedExpense->values(),
                'total' => $uncategorizedExpense->sum($balanceKey),
            ]);
        }

        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'revenue' => $revenueSections->values()->all(),
            'expenses' => $expenseSections->values()->all(),
            'total_revenue' => $totalRevenue,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $grossProfit,
            'operating_income' => $operatingIncome,
            'net_income' => $netIncome,
        ];
    }

    /**
     * Generate Comparative Income Statement.
     *
     * @return array{
     *     report_name: string,
     *     current_period: array,
     *     previous_period: array,
     *     variance: array{
     *         revenue_change: int,
     *         revenue_change_percent: float,
     *         expenses_change: int,
     *         expenses_change_percent: float,
     *         net_income_change: int,
     *         net_income_change_percent: float
     *     }
     * }
     */
    public function getComparativeIncomeStatement(
        ?string $currentStart = null,
        ?string $currentEnd = null,
        ?string $previousStart = null,
        ?string $previousEnd = null
    ): array {
        $currentEnd = $currentEnd ?? now()->toDateString();
        $currentStart = $currentStart ?? now()->startOfYear()->toDateString();

        // Default previous period is one year before current period
        $previousEnd = $previousEnd ?? now()->subYear()->toDateString();
        $previousStart = $previousStart ?? now()->subYear()->startOfYear()->toDateString();

        $current = $this->getIncomeStatement($currentStart, $currentEnd);
        $previous = $this->getIncomeStatement($previousStart, $previousEnd);

        return [
            'report_name' => 'Laporan Laba Rugi Komparatif',
            'current_period' => $current,
            'previous_period' => $previous,
            'variance' => [
                'revenue_change' => $current['total_revenue'] - $previous['total_revenue'],
                'revenue_change_percent' => $previous['total_revenue'] != 0
                    ? round((($current['total_revenue'] - $previous['total_revenue']) / $previous['total_revenue']) * 100, 2)
                    : 0,
                'expenses_change' => $current['total_expenses'] - $previous['total_expenses'],
                'expenses_change_percent' => $previous['total_expenses'] != 0
                    ? round((($current['total_expenses'] - $previous['total_expenses']) / $previous['total_expenses']) * 100, 2)
                    : 0,
                'net_income_change' => $current['net_income'] - $previous['net_income'],
                'net_income_change_percent' => $previous['net_income'] != 0
                    ? round((($current['net_income'] - $previous['net_income']) / abs($previous['net_income'])) * 100, 2)
                    : 0,
            ],
        ];
    }
}
