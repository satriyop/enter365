<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Create a budget with lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<array<string, mixed>>  $lines
     */
    public function createBudget(array $data, array $lines = []): Budget
    {
        return DB::transaction(function () use ($data, $lines) {
            $budget = Budget::create($data);

            foreach ($lines as $lineData) {
                $this->addBudgetLine($budget, $lineData);
            }

            $budget->recalculateTotals();

            return $budget->fresh(['lines.account', 'fiscalPeriod']);
        });
    }

    /**
     * Add a budget line.
     *
     * @param  array<string, mixed>  $data
     */
    public function addBudgetLine(Budget $budget, array $data): BudgetLine
    {
        $line = new BudgetLine($data);
        $line->budget_id = $budget->id;

        // If annual_amount is provided but no monthly amounts, distribute evenly
        if (isset($data['annual_amount']) && ! isset($data['jan_amount'])) {
            $line->distributeEvenly($data['annual_amount']);
        } else {
            $line->recalculateAnnual();
        }

        $line->save();

        return $line;
    }

    /**
     * Update a budget line.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBudgetLine(BudgetLine $line, array $data): BudgetLine
    {
        $line->fill($data);

        // Recalculate annual if monthly amounts changed
        if (isset($data['jan_amount']) || isset($data['feb_amount']) || isset($data['mar_amount']) ||
            isset($data['apr_amount']) || isset($data['may_amount']) || isset($data['jun_amount']) ||
            isset($data['jul_amount']) || isset($data['aug_amount']) || isset($data['sep_amount']) ||
            isset($data['oct_amount']) || isset($data['nov_amount']) || isset($data['dec_amount'])) {
            $line->recalculateAnnual();
        }

        // If only annual_amount changed and user wants even distribution
        if (isset($data['annual_amount']) && isset($data['distribute_evenly']) && $data['distribute_evenly']) {
            $line->distributeEvenly($data['annual_amount']);
        }

        $line->save();

        // Recalculate budget totals
        $line->budget->recalculateTotals();

        return $line->fresh('account');
    }

    /**
     * Get budget vs actual comparison for a period.
     *
     * @return Collection<int, object>
     */
    public function getBudgetVsActual(Budget $budget, ?int $month = null): Collection
    {
        $period = $budget->fiscalPeriod;
        $lines = $budget->lines()->with('account')->get();

        return $lines->map(function (BudgetLine $line) use ($period, $month) {
            $account = $line->account;

            // Get budget amount
            $budgetAmount = $month !== null
                ? $line->getYtdBudget($month)
                : $line->annual_amount;

            // Get actual amount from journal entries
            $actualAmount = $this->getActualAmount($account, $period, $month);

            // Calculate variance
            $variance = $budgetAmount - $actualAmount;
            $variancePercent = $budgetAmount > 0
                ? round(($variance / $budgetAmount) * 100, 2)
                : 0;

            return (object) [
                'account_id' => $account->id,
                'account_code' => $account->code,
                'account_name' => $account->name,
                'account_type' => $account->type,
                'budget_amount' => $budgetAmount,
                'actual_amount' => $actualAmount,
                'variance' => $variance,
                'variance_percent' => $variancePercent,
                'is_over_budget' => $variance < 0,
            ];
        });
    }

    /**
     * Get actual amount for an account in a period.
     */
    public function getActualAmount(Account $account, FiscalPeriod $period, ?int $month = null): int
    {
        $query = JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($period, $month) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);

                if ($month !== null) {
                    $q->whereMonth('entry_date', '<=', $month)
                        ->whereYear('entry_date', $period->start_date->year);
                }
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        // For expenses: actual = debit - credit
        // For revenue: actual = credit - debit
        return $account->type === Account::TYPE_EXPENSE
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }

    /**
     * Get monthly budget vs actual breakdown.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyBreakdown(Budget $budget): array
    {
        $period = $budget->fiscalPeriod;
        $result = [];

        for ($month = 1; $month <= 12; $month++) {
            $monthBudget = $budget->getMonthlyBudget($month);

            // Get actual amounts for this month
            $actualRevenue = $this->getMonthlyActualByType($period, Account::TYPE_REVENUE, $month);
            $actualExpense = $this->getMonthlyActualByType($period, Account::TYPE_EXPENSE, $month);

            $result[$month] = [
                'month' => $month,
                'month_name' => $this->getMonthName($month),
                'budget' => [
                    'revenue' => $monthBudget['revenue'],
                    'expense' => $monthBudget['expense'],
                    'net' => $monthBudget['net'],
                ],
                'actual' => [
                    'revenue' => $actualRevenue,
                    'expense' => $actualExpense,
                    'net' => $actualRevenue - $actualExpense,
                ],
                'variance' => [
                    'revenue' => $monthBudget['revenue'] - $actualRevenue,
                    'expense' => $monthBudget['expense'] - $actualExpense,
                    'net' => $monthBudget['net'] - ($actualRevenue - $actualExpense),
                ],
            ];
        }

        return $result;
    }

    /**
     * Get monthly actual amount by account type.
     */
    protected function getMonthlyActualByType(FiscalPeriod $period, string $accountType, int $month): int
    {
        $query = JournalEntryLine::query()
            ->whereHas('account', fn ($q) => $q->where('type', $accountType))
            ->whereHas('journalEntry', function ($q) use ($period, $month) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id)
                    ->whereMonth('entry_date', $month)
                    ->whereYear('entry_date', $period->start_date->year);
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        return $accountType === Account::TYPE_EXPENSE
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }

    /**
     * Get budget summary.
     */
    public function getBudgetSummary(Budget $budget): array
    {
        $period = $budget->fiscalPeriod;
        $currentMonth = now()->month;

        // If we're not in this fiscal period, use the last month of the period
        if (! $period->containsDate(now())) {
            $currentMonth = $period->end_date->month;
        }

        // Get YTD budget
        $ytdBudgetRevenue = 0;
        $ytdBudgetExpense = 0;
        foreach ($budget->lines()->with('account')->get() as $line) {
            $ytdAmount = $line->getYtdBudget($currentMonth);
            if ($line->account->type === Account::TYPE_REVENUE) {
                $ytdBudgetRevenue += $ytdAmount;
            } elseif ($line->account->type === Account::TYPE_EXPENSE) {
                $ytdBudgetExpense += $ytdAmount;
            }
        }

        // Get YTD actual
        $ytdActualRevenue = $this->getYtdActualByType($period, Account::TYPE_REVENUE, $currentMonth);
        $ytdActualExpense = $this->getYtdActualByType($period, Account::TYPE_EXPENSE, $currentMonth);

        return [
            'budget' => [
                'name' => $budget->name,
                'type' => $budget->type,
                'status' => $budget->status,
                'fiscal_period' => $period->name,
            ],
            'annual' => [
                'budget_revenue' => $budget->total_revenue,
                'budget_expense' => $budget->total_expense,
                'budget_net' => $budget->net_budget,
            ],
            'ytd' => [
                'through_month' => $currentMonth,
                'through_month_name' => $this->getMonthName($currentMonth),
                'budget_revenue' => $ytdBudgetRevenue,
                'budget_expense' => $ytdBudgetExpense,
                'budget_net' => $ytdBudgetRevenue - $ytdBudgetExpense,
                'actual_revenue' => $ytdActualRevenue,
                'actual_expense' => $ytdActualExpense,
                'actual_net' => $ytdActualRevenue - $ytdActualExpense,
                'variance_revenue' => $ytdBudgetRevenue - $ytdActualRevenue,
                'variance_expense' => $ytdBudgetExpense - $ytdActualExpense,
                'variance_net' => ($ytdBudgetRevenue - $ytdBudgetExpense) - ($ytdActualRevenue - $ytdActualExpense),
            ],
        ];
    }

    /**
     * Get YTD actual amount by account type.
     */
    protected function getYtdActualByType(FiscalPeriod $period, string $accountType, int $throughMonth): int
    {
        $query = JournalEntryLine::query()
            ->whereHas('account', fn ($q) => $q->where('type', $accountType))
            ->whereHas('journalEntry', function ($q) use ($period, $throughMonth) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id)
                    ->whereMonth('entry_date', '<=', $throughMonth)
                    ->whereYear('entry_date', $period->start_date->year);
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        return $accountType === Account::TYPE_EXPENSE
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;
    }

    /**
     * Copy budget to a new fiscal period.
     */
    public function copyBudget(Budget $budget, FiscalPeriod $newPeriod, ?string $newName = null): Budget
    {
        return DB::transaction(function () use ($budget, $newPeriod, $newName) {
            $newBudget = Budget::create([
                'name' => $newName ?? 'Anggaran '.$newPeriod->name,
                'description' => $budget->description,
                'fiscal_period_id' => $newPeriod->id,
                'type' => $budget->type,
                'status' => Budget::STATUS_DRAFT,
                'total_revenue' => $budget->total_revenue,
                'total_expense' => $budget->total_expense,
                'net_budget' => $budget->net_budget,
            ]);

            foreach ($budget->lines as $line) {
                BudgetLine::create([
                    'budget_id' => $newBudget->id,
                    'account_id' => $line->account_id,
                    'jan_amount' => $line->jan_amount,
                    'feb_amount' => $line->feb_amount,
                    'mar_amount' => $line->mar_amount,
                    'apr_amount' => $line->apr_amount,
                    'may_amount' => $line->may_amount,
                    'jun_amount' => $line->jun_amount,
                    'jul_amount' => $line->jul_amount,
                    'aug_amount' => $line->aug_amount,
                    'sep_amount' => $line->sep_amount,
                    'oct_amount' => $line->oct_amount,
                    'nov_amount' => $line->nov_amount,
                    'dec_amount' => $line->dec_amount,
                    'annual_amount' => $line->annual_amount,
                    'notes' => $line->notes,
                ]);
            }

            return $newBudget->fresh(['lines.account', 'fiscalPeriod']);
        });
    }

    /**
     * Get accounts that are over budget.
     *
     * @return Collection<int, object>
     */
    public function getOverBudgetAccounts(Budget $budget, ?int $month = null): Collection
    {
        $comparison = $this->getBudgetVsActual($budget, $month);

        return $comparison->filter(fn ($item) => $item->is_over_budget);
    }

    /**
     * Get month name in Indonesian.
     */
    protected function getMonthName(int $month): string
    {
        $months = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];

        return $months[$month] ?? '';
    }
}
