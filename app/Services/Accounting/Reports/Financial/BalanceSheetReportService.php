<?php

namespace App\Services\Accounting\Reports\Financial;

use App\Models\Accounting\Account;
use Illuminate\Support\Facades\DB;

class BalanceSheetReportService
{
    public function __construct(
        private AccountHierarchyBuilder $hierarchyBuilder,
        private IncomeStatementReportService $incomeStatementService
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
            $balanceItems = $this->hierarchyBuilder->build($balanceItems, $accounts);
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
     * Calculate net income for the period.
     */
    private function calculateNetIncome(?string $asOfDate = null): int
    {
        $startOfYear = now()->startOfYear()->toDateString();
        $endDate = $asOfDate ?? now()->toDateString();

        $incomeStatement = $this->incomeStatementService->getIncomeStatement($startOfYear, $endDate);

        return $incomeStatement['net_income'];
    }
}
