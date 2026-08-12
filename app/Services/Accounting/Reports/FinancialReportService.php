<?php

namespace App\Services\Accounting\Reports;

use App\Services\Accounting\Reports\Financial\AccountHierarchyBuilder;
use App\Services\Accounting\Reports\Financial\BalanceSheetReportService;
use App\Services\Accounting\Reports\Financial\EquityStatementReportService;
use App\Services\Accounting\Reports\Financial\GeneralLedgerReportService;
use App\Services\Accounting\Reports\Financial\IncomeStatementReportService;
use Illuminate\Support\Collection;

class FinancialReportService
{
    public function __construct(
        private BalanceSheetReportService $balanceSheetService,
        private IncomeStatementReportService $incomeStatementService,
        private GeneralLedgerReportService $generalLedgerService,
        private EquityStatementReportService $equityStatementService,
        private AccountHierarchyBuilder $hierarchyBuilder
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
        return $this->balanceSheetService->getBalanceSheet($asOfDate, $hierarchical);
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
        return $this->incomeStatementService->getIncomeStatement($startDate, $endDate, $hierarchical);
    }

    /**
     * Get General Ledger (Buku Besar).
     */
    public function getGeneralLedger(?string $startDate = null, ?string $endDate = null): Collection
    {
        return $this->generalLedgerService->getGeneralLedger($startDate, $endDate);
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
        return $this->balanceSheetService->getComparativeBalanceSheet($currentDate, $previousDate);
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
        return $this->incomeStatementService->getComparativeIncomeStatement(
            $currentStart,
            $currentEnd,
            $previousStart,
            $previousEnd
        );
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
        return $this->equityStatementService->getStatementOfChangesInEquity($startDate, $endDate);
    }

    /**
     * Statement of Changes in Equity shaped for the API response contract.
     *
     * @return array{
     *     report_name: string,
     *     period: array{start_date: string, end_date: string},
     *     opening_equity: list<array{account_id: int, code: string, name: string, opening_balance: int}>,
     *     total_opening_equity: int,
     *     changes: array{
     *         capital_additions: int,
     *         capital_withdrawals: int,
     *         net_income: int,
     *         dividends: int,
     *         adjustments: int,
     *         total_changes: int
     *     },
     *     closing_equity: list<array{account_id: int, code: string, name: string, closing_balance: int}>,
     *     total_closing_equity: int
     * }
     */
    public function getStatementOfChangesInEquityForApi(?string $startDate = null, ?string $endDate = null): array
    {
        return $this->equityStatementService->getStatementOfChangesInEquityForApi($startDate, $endDate);
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
        return $this->hierarchyBuilder->build($flatBalances, $accounts);
    }
}
