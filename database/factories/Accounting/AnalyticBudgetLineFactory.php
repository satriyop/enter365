<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\AnalyticBudget;
use App\Models\Accounting\AnalyticBudgetLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticBudgetLine>
 */
class AnalyticBudgetLineFactory extends Factory
{
    protected $model = AnalyticBudgetLine::class;

    public function definition(): array
    {
        return [
            'analytic_budget_id' => AnalyticBudget::factory(),
            'analytic_account_id' => AnalyticAccount::factory(),
            'planned_amount' => 1_000_000,
        ];
    }
}
