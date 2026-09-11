<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetModel>
 */
class AssetModelFactory extends Factory
{
    protected $model = AssetModel::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('AM-###')),
            'name' => 'Kendaraan 5 tahun',
            'method' => AssetModel::METHOD_LINEAR,
            'method_number' => 60,
            'method_period' => AssetModel::PERIOD_MONTH,
            'salvage_value_percent' => 0,
            'asset_account_id' => Account::factory()->asset(),
            'depreciation_account_id' => Account::factory()->asset(),
            'expense_account_id' => Account::factory()->expense(),
            'is_active' => true,
        ];
    }
}
