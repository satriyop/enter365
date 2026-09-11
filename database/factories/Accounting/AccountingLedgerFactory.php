<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AccountingLedger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingLedger>
 */
class AccountingLedgerFactory extends Factory
{
    protected $model = AccountingLedger::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->words(2, true),
            'currency_code' => 'IDR',
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
