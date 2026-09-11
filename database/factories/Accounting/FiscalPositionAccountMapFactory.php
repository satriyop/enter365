<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPosition;
use App\Models\Accounting\FiscalPositionAccountMap;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPositionAccountMap>
 */
class FiscalPositionAccountMapFactory extends Factory
{
    protected $model = FiscalPositionAccountMap::class;

    public function definition(): array
    {
        return [
            'fiscal_position_id' => FiscalPosition::factory(),
            'source_account_id' => Account::factory(),
            'dest_account_id' => Account::factory(),
        ];
    }
}
