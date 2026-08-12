<?php

namespace App\Services\Accounting\Reports\Financial;

use App\Models\Accounting\Account;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EquityStatementReportService
{
    public function __construct(
        private IncomeStatementReportService $incomeStatementService
    ) {}

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
        $netIncome = $this->incomeStatementService->getIncomeStatement($startDate, $endDate)['net_income'];

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
