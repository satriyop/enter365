<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AssetModel;
use App\Models\Accounting\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FixedAsset>
 */
class FixedAssetFactory extends Factory
{
    protected $model = FixedAsset::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FA-####')),
            'name' => 'Toyota Avanza',
            'original_value' => 180_000_000,
            'salvage_value' => 0,
            'acquisition_date' => now()->startOfMonth()->toDateString(),
            'method' => AssetModel::METHOD_LINEAR,
            'method_number' => 36,
            'method_period' => AssetModel::PERIOD_MONTH,
            'asset_account_id' => Account::factory()->asset(),
            'depreciation_account_id' => Account::factory()->asset(),
            'expense_account_id' => Account::factory()->expense(),
            'status' => FixedAsset::STATUS_DRAFT,
            'accumulated_depreciation' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => ['status' => FixedAsset::STATUS_RUNNING]);
    }
}
