<?php

namespace App\Services\Accounting\Reports;

use App\Models\Accounting\Account;
use App\Services\Accounting\AccountBalanceService;
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
     *     assets: array<int, array{name: string, accounts: Collection, total: int}>,
     *     liabilities: array<int, array{name: string, accounts: Collection, total: int}>,
     *     equity: array<int, array{name: string, accounts: Collection, total: int}>,
     *     total_assets: int,
     *     total_liabilities: int,
     *     total_equity: int,
     *     total_liabilities_equity: int,
     *     is_balanced: bool
     * }
     */
    public function getBalanceSheet(?string $asOfDate = null, bool $hierarchical = false): array
    {
        $asOfDate = $asOfDate ?? now()->toDateString();

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('type', [Account::TYPE_ASSET, Account::TYPE_LIABILITY, Account::TYPE_EQUITY])
            ->orderBy('code')
            ->get();

        // Bulk get balances
        $movements = DB::table('journal_entry_lines as jel')
            ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
            ->where('je.is_posted', true)
            ->where('je.entry_date', '<=', $asOfDate.' 23:59:59')
            ->whereNull('je.deleted_at')
            ->selectRaw('jel.account_id, SUM(jel.debit) as total_debit, SUM(jel.credit) as total_credit')
            ->groupBy('jel.account_id')
            ->get()
            ->keyBy('account_id');

        $balanceItems = $accounts->map(function ($account) use ($movements) {
            $movement = $movements->get($account->id);
            $totalDebit = (int) ($movement->total_debit ?? 0);
            $totalCredit = (int) ($movement->total_credit ?? 0);

            $balance = (int) $account->opening_balance + ($account->isDebitNormal()
                ? $totalDebit - $totalCredit
                : $totalCredit - $totalDebit);

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
            // Pass ALL items (including zero-balance) so parents can be included as tree nodes
            $balanceItems = $this->buildAccountHierarchy($balanceItems, $accounts);
        } else {
            $balanceItems = $balanceItems->filter(fn ($item) => $item->balance != 0);
        }

        // For hierarchical mode, use rollup_balance for totals; for flat, use balance
        $balanceKey = $hierarchical ? 'rollup_balance' : 'balance';

        // Group by type
        $currentAssets = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_ASSET && $i->subtype === Account::SUBTYPE_CURRENT_ASSET);
        $fixedAssets = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_ASSET && $i->subtype === Account::SUBTYPE_FIXED_ASSET);
        $currentLiabilities = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_LIABILITY && $i->subtype === Account::SUBTYPE_CURRENT_LIABILITY);
        $longTermLiabilities = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_LIABILITY && $i->subtype === Account::SUBTYPE_LONG_TERM_LIABILITY);
        $equityItems = $balanceItems->filter(fn ($i) => $i->type === Account::TYPE_EQUITY);

        // Calculate net income and add to equity
        $netIncome = $this->calculateNetIncome($asOfDate);
        if ($netIncome != 0) {
            $netIncomeItem = [
                'account_id' => null,
                'code' => null,
                'name' => 'Laba/Rugi Berjalan',
                'type' => Account::TYPE_EQUITY,
                'subtype' => Account::SUBTYPE_EQUITY,
                'balance' => $netIncome,
            ];

            if ($hierarchical) {
                $netIncomeItem['rollup_balance'] = $netIncome;
                $netIncomeItem['children'] = collect();
                $netIncomeItem['depth'] = 0;
            }

            $equityItems->push((object) $netIncomeItem);
        }

        $totalAssets = $currentAssets->sum($balanceKey) + $fixedAssets->sum($balanceKey);
        $totalLiabilities = $currentLiabilities->sum($balanceKey) + $longTermLiabilities->sum($balanceKey);
        $totalEquity = $equityItems->sum($balanceKey);

        // Build sectioned arrays matching frontend BalanceSheetSection[] contract
        $assetSections = collect();
        if ($currentAssets->isNotEmpty()) {
            $assetSections->push([
                'name' => 'Aset Lancar',
                'accounts' => $currentAssets->values(),
                'total' => $currentAssets->sum($balanceKey),
            ]);
        }
        if ($fixedAssets->isNotEmpty()) {
            $assetSections->push([
                'name' => 'Aset Tetap',
                'accounts' => $fixedAssets->values(),
                'total' => $fixedAssets->sum($balanceKey),
            ]);
        }

        $liabilitySections = collect();
        if ($currentLiabilities->isNotEmpty()) {
            $liabilitySections->push([
                'name' => 'Liabilitas Jangka Pendek',
                'accounts' => $currentLiabilities->values(),
                'total' => $currentLiabilities->sum($balanceKey),
            ]);
        }
        if ($longTermLiabilities->isNotEmpty()) {
            $liabilitySections->push([
                'name' => 'Liabilitas Jangka Panjang',
                'accounts' => $longTermLiabilities->values(),
                'total' => $longTermLiabilities->sum($balanceKey),
            ]);
        }

        $equitySections = collect();
        if ($equityItems->isNotEmpty()) {
            $equitySections->push([
                'name' => 'Ekuitas',
                'accounts' => $equityItems->values(),
                'total' => $totalEquity,
            ]);
        }

        return [
            'as_of_date' => $asOfDate,
            'assets' => $assetSections->values(),
            'liabilities' => $liabilitySections->values(),
            'equity' => $equitySections->values(),
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_equity' => $totalLiabilities + $totalEquity,
            'is_balanced' => $totalAssets === ($totalLiabilities + $totalEquity),
        ];
    }

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
            ->where('is_active', true)
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
                'balance' => abs($balance),
            ];
        });

        if ($hierarchical) {
            $items = $this->buildAccountHierarchy($items, $accounts);
        } else {
            $items = $items->filter(fn ($item) => $item->balance != 0);
        }

        $balanceKey = $hierarchical ? 'rollup_balance' : 'balance';

        // Revenue
        $operatingRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OPERATING_REVENUE);
        $otherRevenue = $items->filter(fn ($i) => $i->type === Account::TYPE_REVENUE && $i->subtype === Account::SUBTYPE_OTHER_REVENUE);
        $totalRevenue = $operatingRevenue->sum($balanceKey) + $otherRevenue->sum($balanceKey);

        // Expenses
        $costOfGoods = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && str_starts_with($i->code, '5-1'));
        $operatingExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OPERATING_EXPENSE && ! str_starts_with($i->code, '5-1'));
        $otherExpense = $items->filter(fn ($i) => $i->type === Account::TYPE_EXPENSE && $i->subtype === Account::SUBTYPE_OTHER_EXPENSE);
        $totalExpenses = $costOfGoods->sum($balanceKey) + $operatingExpense->sum($balanceKey) + $otherExpense->sum($balanceKey);

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
     * Get General Ledger (Buku Besar).
     */
    public function getGeneralLedger(?string $startDate = null, ?string $endDate = null): Collection
    {
        $accounts = Account::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        return $this->balanceService->getLedgers($accounts, $startDate, $endDate)
            ->filter(fn ($item) => ! empty($item->entries));
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
     * Generate Comparative Balance Sheet.
     *
     * @return array{
     *     report_name: string,
     *     current_period: array,
     *     previous_period: array,
     *     variance: array{
     *         assets_change: int,
     *         assets_change_percent: float,
     *         liabilities_change: int,
     *         liabilities_change_percent: float,
     *         equity_change: int,
     *         equity_change_percent: float
     *     }
     * }
     */
    public function getComparativeBalanceSheet(?string $currentDate = null, ?string $previousDate = null): array
    {
        $currentDate = $currentDate ?? now()->toDateString();
        $previousDate = $previousDate ?? now()->subYear()->toDateString();

        $current = $this->getBalanceSheet($currentDate);
        $previous = $this->getBalanceSheet($previousDate);

        return [
            'report_name' => 'Laporan Posisi Keuangan Komparatif',
            'current_period' => $current,
            'previous_period' => $previous,
            'variance' => [
                'assets_change' => $current['total_assets'] - $previous['total_assets'],
                'assets_change_percent' => $previous['total_assets'] != 0
                    ? round((($current['total_assets'] - $previous['total_assets']) / $previous['total_assets']) * 100, 2)
                    : 0,
                'liabilities_change' => $current['total_liabilities'] - $previous['total_liabilities'],
                'liabilities_change_percent' => $previous['total_liabilities'] != 0
                    ? round((($current['total_liabilities'] - $previous['total_liabilities']) / $previous['total_liabilities']) * 100, 2)
                    : 0,
                'equity_change' => $current['total_equity'] - $previous['total_equity'],
                'equity_change_percent' => $previous['total_equity'] != 0
                    ? round((($current['total_equity'] - $previous['total_equity']) / $previous['total_equity']) * 100, 2)
                    : 0,
            ],
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

    /**
     * Generate Statement of Changes in Equity (Laporan Perubahan Ekuitas).
     *
     * @return array{
     *     period_start: string,
     *     period_end: string,
     *     opening_equity: array{items: Collection, total: int},
     *     changes: array{
     *         capital_additions: int,
     *         capital_withdrawals: int,
     *         net_income: int,
     *         dividends: int,
     *         other_adjustments: int,
     *         total_changes: int
     *     },
     *     closing_equity: array{items: Collection, total: int}
     * }
     */
    public function getStatementOfChangesInEquity(?string $startDate = null, ?string $endDate = null): array
    {
        $endDate = $endDate ?? now()->toDateString();
        $startDate = $startDate ?? now()->startOfYear()->toDateString();

        // Get opening equity (day before start date)
        $openingDate = date('Y-m-d', strtotime($startDate.' -1 day'));

        $equityAccounts = Account::query()
            ->where('is_active', true)
            ->where('type', Account::TYPE_EQUITY)
            ->orderBy('code')
            ->get();

        // Calculate opening balances
        $openingItems = $equityAccounts->map(function ($account) use ($openingDate) {
            $balance = $account->getBalance($openingDate);

            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'subtype' => $account->subtype,
                'balance' => $balance,
            ];
        });

        // Calculate closing balances
        $closingItems = $equityAccounts->map(function ($account) use ($endDate) {
            $balance = $account->getBalance($endDate);

            return (object) [
                'account_id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'subtype' => $account->subtype,
                'balance' => $balance,
            ];
        });

        // Calculate changes during the period
        $changes = $this->calculateEquityChanges($equityAccounts, $startDate, $endDate);

        // Get net income for the period
        $netIncome = $this->getIncomeStatement($startDate, $endDate)['net_income'];

        $openingTotal = $openingItems->sum('balance');
        $closingTotal = $closingItems->sum('balance');

        return [
            'period_start' => $startDate,
            'period_end' => $endDate,
            'opening_equity' => [
                'items' => $openingItems->filter(fn ($i) => $i->balance != 0)->values(),
                'total' => $openingTotal,
            ],
            'changes' => [
                'capital_additions' => $changes['additions'],
                'capital_withdrawals' => $changes['withdrawals'],
                'net_income' => $netIncome,
                'dividends' => $changes['dividends'],
                'other_adjustments' => $changes['other'],
                'total_changes' => $closingTotal - $openingTotal,
            ],
            'closing_equity' => [
                'items' => $closingItems->filter(fn ($i) => $i->balance != 0)->values(),
                'total' => $closingTotal,
            ],
        ];
    }

    /**
     * Build a hierarchical tree from flat balance items using Account parent_id relationships.
     *
     * Returns only top-level nodes (root accounts or accounts whose parents have no balance).
     * Each node contains:
     * - balance: own transactions only
     * - rollup_balance: own + all descendants
     * - children: recursive collection
     * - depth: nesting level
     */
    public function buildAccountHierarchy(Collection $flatBalances, Collection $accounts): Collection
    {
        // Build a map of account_id => parent_id from the full accounts collection
        $parentMap = $accounts->pluck('parent_id', 'id');

        // Index flat balances by account_id
        $balanceById = $flatBalances->keyBy('account_id');

        // Build a subtype map from all accounts
        $subtypeMap = $accounts->pluck('subtype', 'id');

        // Collect ancestor IDs within the same subtype boundary
        $ancestorsToInclude = collect();

        foreach ($flatBalances as $item) {
            if ($item->balance == 0) {
                continue; // Only trace ancestors for items that have actual balance
            }
            $currentParentId = $parentMap->get($item->account_id);
            $itemSubtype = $item->subtype;
            while ($currentParentId !== null) {
                // Stop if parent has different subtype
                if ($subtypeMap->get($currentParentId) !== $itemSubtype) {
                    break;
                }
                if ($ancestorsToInclude->contains($currentParentId)) {
                    break;
                }
                $ancestorsToInclude->push($currentParentId);
                $currentParentId = $parentMap->get($currentParentId);
            }
        }

        // Add zero-balance parent nodes so tree is complete
        foreach ($ancestorsToInclude as $ancestorId) {
            if (! $balanceById->has($ancestorId)) {
                $account = $accounts->firstWhere('id', $ancestorId);
                if ($account) {
                    $node = (object) [
                        'account_id' => $account->id,
                        'code' => $account->code,
                        'name' => $account->name,
                        'type' => $account->type,
                        'subtype' => $account->subtype,
                        'parent_id' => $account->parent_id,
                        'balance' => 0,
                    ];
                    $balanceById->put($ancestorId, $node);
                }
            }
        }

        // Build child-lookup: parent_id => [child items]
        // Only nest within same subtype to keep category grouping intact
        $childrenOf = [];
        foreach ($balanceById as $item) {
            $pid = $item->parent_id;
            if ($pid !== null && $balanceById->has($pid)) {
                $parent = $balanceById->get($pid);
                if ($parent->subtype === $item->subtype) {
                    $childrenOf[$pid][] = $item;
                }
            }
        }

        // Recursive tree builder
        $buildTree = function ($item, int $depth) use (&$buildTree, &$childrenOf): object {
            $children = collect($childrenOf[$item->account_id] ?? [])
                ->map(fn ($child) => $buildTree($child, $depth + 1))
                ->sortBy('code')
                ->values();

            $rollupBalance = $item->balance + $children->sum('rollup_balance');

            return (object) [
                'account_id' => $item->account_id,
                'code' => $item->code,
                'name' => $item->name,
                'type' => $item->type,
                'subtype' => $item->subtype,
                'balance' => $item->balance,
                'rollup_balance' => $rollupBalance,
                'children' => $children,
                'depth' => $depth,
            ];
        };

        // Find root nodes: items whose parent is null, whose parent isn't in the balance set,
        // or whose parent has a different subtype (don't cross subtype boundaries)
        $roots = $balanceById->filter(function ($item) use ($balanceById) {
            if ($item->parent_id === null || ! $balanceById->has($item->parent_id)) {
                return true;
            }

            $parent = $balanceById->get($item->parent_id);

            return $parent->subtype !== $item->subtype;
        });

        return $roots->map(fn ($item) => $buildTree($item, 0))
            ->filter(fn ($item) => $item->rollup_balance != 0)
            ->sortBy('code')
            ->values();
    }

    /**
     * Calculate equity changes for the period.
     *
     * @return array{additions: int, withdrawals: int, dividends: int, other: int}
     */
    private function calculateEquityChanges(Collection $equityAccounts, string $startDate, string $endDate): array
    {
        $additions = 0;
        $withdrawals = 0;
        $dividends = 0;
        $other = 0;

        foreach ($equityAccounts as $account) {
            $movements = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.is_posted', true)
                ->whereBetween('je.entry_date', [$startDate, $endDate.' 23:59:59'])
                ->whereNull('je.deleted_at')
                ->selectRaw('COALESCE(SUM(jel.debit), 0) as total_debit, COALESCE(SUM(jel.credit), 0) as total_credit')
                ->first();

            $debit = (int) ($movements->total_debit ?? 0);
            $credit = (int) ($movements->total_credit ?? 0);

            // For equity accounts: credits increase (additions), debits decrease (withdrawals/dividends)
            // Capital accounts typically have 'modal' or 'capital' in the name
            if (str_contains(strtolower($account->name), 'modal') || str_contains(strtolower($account->name), 'capital')) {
                $additions += $credit;
                $withdrawals += $debit;
            } elseif (str_contains(strtolower($account->name), 'dividen')) {
                $dividends += $debit;
            } else {
                // Retained earnings or other equity accounts
                $other += ($credit - $debit);
            }
        }

        return [
            'additions' => $additions,
            'withdrawals' => $withdrawals,
            'dividends' => $dividends,
            'other' => $other,
        ];
    }
}
