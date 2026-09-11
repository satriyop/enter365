<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('LN-####')),
            'name' => 'Bank loan',
            'principal' => 1_200_000,
            'annual_interest_rate' => 0,
            'duration_months' => 12,
            'start_date' => now()->startOfMonth()->toDateString(),
            'liability_account_id' => Account::factory()->liability(),
            'interest_account_id' => Account::factory()->expense(),
            'bank_account_id' => Account::factory()->asset(),
            'status' => Loan::STATUS_DRAFT,
            'remaining_principal' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => Loan::STATUS_RUNNING,
            'remaining_principal' => 1_200_000,
        ]);
    }
}
