<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AnalyticAccount;
use App\Models\Accounting\AnalyticDistributionModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticDistributionModel>
 */
class AnalyticDistributionModelFactory extends Factory
{
    protected $model = AnalyticDistributionModel::class;

    public function definition(): array
    {
        $account = AnalyticAccount::factory()->create();

        return [
            'name' => 'Default marketing split',
            'analytic_distribution' => [(string) $account->id => 100],
            'sequence' => 10,
            'is_active' => true,
        ];
    }
}
