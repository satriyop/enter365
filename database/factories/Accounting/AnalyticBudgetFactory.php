<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AnalyticBudget;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticBudget>
 */
class AnalyticBudgetFactory extends Factory
{
    protected $model = AnalyticBudget::class;

    public function definition(): array
    {
        return [
            'name' => 'Q1 analytic budget',
            'date_from' => '2026-01-01',
            'date_to' => '2026-03-31',
            'status' => AnalyticBudget::STATUS_DRAFT,
        ];
    }
}
