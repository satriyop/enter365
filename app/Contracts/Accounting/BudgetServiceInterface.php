<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Budget;
use App\Models\Accounting\BudgetLine;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Support\Collection;

/**
 * Interface for Budget management operations.
 */
interface BudgetServiceInterface
{
    /**
     * Create a new budget with optional lines.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function createBudget(array $data, array $lines = []): Budget;

    /**
     * Add a line item to a budget.
     *
     * @param  array<string, mixed>  $data
     */
    public function addBudgetLine(Budget $budget, array $data): BudgetLine;

    /**
     * Update a budget line item.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateBudgetLine(BudgetLine $line, array $data): BudgetLine;

    /**
     * Get budget vs actual comparison.
     *
     * @return Collection<int, mixed>
     */
    public function getBudgetVsActual(Budget $budget, ?int $month = null): Collection;

    /**
     * Get actual amount for an account in a period.
     */
    public function getActualAmount(Account $account, FiscalPeriod $period, ?int $month = null): int;

    /**
     * Get monthly breakdown for a budget.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyBreakdown(Budget $budget): array;

    /**
     * Get budget summary with totals.
     *
     * @return array<string, mixed>
     */
    public function getBudgetSummary(Budget $budget): array;

    /**
     * Copy a budget to a new fiscal period.
     */
    public function copyBudget(Budget $budget, FiscalPeriod $newPeriod, ?string $newName = null): Budget;

    /**
     * Get accounts that are over budget.
     *
     * @return Collection<int, mixed>
     */
    public function getOverBudgetAccounts(Budget $budget, ?int $month = null): Collection;
}
