<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountReconciliation>
 */
class AccountReconciliationFactory extends Factory
{
    protected $model = AccountReconciliation::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory()->asset(),
            'amount' => 100_000,
            'reconciled_at' => now(),
        ];
    }
}
