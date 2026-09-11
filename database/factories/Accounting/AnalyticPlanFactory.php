<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AnalyticPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticPlan>
 */
class AnalyticPlanFactory extends Factory
{
    protected $model = AnalyticPlan::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PLAN-##')),
            'name' => fake()->words(2, true),
            'default_applicability' => AnalyticPlan::APPLICABILITY_OPTIONAL,
            'is_active' => true,
        ];
    }
}
