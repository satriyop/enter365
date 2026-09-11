<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AssetDepreciationLine;
use App\Models\Accounting\FixedAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetDepreciationLine>
 */
class AssetDepreciationLineFactory extends Factory
{
    protected $model = AssetDepreciationLine::class;

    public function definition(): array
    {
        return [
            'fixed_asset_id' => FixedAsset::factory(),
            'sequence' => 1,
            'depreciation_date' => now()->endOfMonth()->toDateString(),
            'amount' => 1_000_000,
            'depreciated_value' => 1_000_000,
            'remaining_value' => 35_000_000,
            'status' => AssetDepreciationLine::STATUS_DRAFT,
        ];
    }
}
