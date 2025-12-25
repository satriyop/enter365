<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankTransaction>
 */
class BankTransactionFactory extends Factory
{
    protected $model = BankTransaction::class;

    public function definition(): array
    {
        $isDebit = $this->faker->boolean();
        $amount = $this->faker->randomElement([500000, 1000000, 2500000, 5000000, 10000000]);

        return [
            'account_id' => Account::factory()->asset(),
            'transaction_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'description' => $this->faker->sentence(4),
            'reference' => $this->faker->optional()->bothify('TRX-########'),
            'debit' => $isDebit ? $amount : 0,
            'credit' => $isDebit ? 0 : $amount,
            'balance' => $this->faker->numberBetween(10000000, 100000000),
            'status' => BankTransaction::STATUS_UNMATCHED,
            'matched_payment_id' => null,
            'matched_journal_line_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
            'import_batch' => null,
            'external_id' => $this->faker->optional()->uuid(),
            'created_by' => null,
        ];
    }

    public function debit(int $amount = 1000000): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    public function credit(int $amount = 1000000): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    public function unmatched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BankTransaction::STATUS_UNMATCHED,
            'matched_payment_id' => null,
            'matched_journal_line_id' => null,
        ]);
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BankTransaction::STATUS_MATCHED,
        ]);
    }

    public function reconciled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BankTransaction::STATUS_RECONCILED,
            'reconciled_at' => now(),
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }

    public function inBatch(string $batchId): static
    {
        return $this->state(fn (array $attributes) => [
            'import_batch' => $batchId,
        ]);
    }
}
