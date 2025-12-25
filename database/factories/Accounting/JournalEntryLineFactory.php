<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntryLine>
 */
class JournalEntryLineFactory extends Factory
{
    protected $model = JournalEntryLine::class;

    public function definition(): array
    {
        return [
            'journal_entry_id' => JournalEntry::factory(),
            'account_id' => Account::factory(),
            'description' => $this->faker->optional()->sentence(),
            'debit' => 0,
            'credit' => 0,
        ];
    }

    public function debit(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => $amount,
            'credit' => 0,
        ]);
    }

    public function credit(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'debit' => 0,
            'credit' => $amount,
        ]);
    }

    public function forEntry(JournalEntry $entry): static
    {
        return $this->state(fn (array $attributes) => [
            'journal_entry_id' => $entry->id,
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }
}
