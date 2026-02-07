<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliationSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliationSession>
 */
class BankReconciliationSessionFactory extends Factory
{
    protected $model = BankReconciliationSession::class;

    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'statement_date' => now()->toDateString(),
            'statement_balance' => $this->faker->randomElement([10000000, 25000000, 50000000]),
            'book_balance' => null,
            'status' => BankReconciliationSession::STATUS_IN_PROGRESS,
            'reconciled_count' => 0,
            'created_by' => User::factory(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BankReconciliationSession::STATUS_COMPLETED,
            'completed_at' => now(),
            'completed_by' => $attributes['created_by'],
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }
}
