<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Journal>
 */
class JournalFactory extends Factory
{
    protected $model = Journal::class;

    public function definition(): array
    {
        $type = fake()->randomElement(Journal::getTypes());

        return [
            'name' => fake()->unique()->words(2, true),
            'type' => $type,
            'sequence_prefix' => strtoupper(fake()->unique()->lexify('???-')),
            'default_account_id' => null,
            'suspense_account_id' => null,
            'outstanding_receipts_account_id' => null,
            'outstanding_payments_account_id' => null,
            'bank_account_number' => null,
            'dedicated_payment_sequence' => false,
            'currency' => null,
            'is_active' => true,
        ];
    }

    public function sales(): static
    {
        return $this->state(fn () => [
            'name' => 'Sales',
            'type' => Journal::TYPE_SALES,
            'sequence_prefix' => 'SALE-',
        ]);
    }

    public function miscellaneous(): static
    {
        return $this->state(fn () => [
            'name' => 'Miscellaneous',
            'type' => Journal::TYPE_MISCELLANEOUS,
            'sequence_prefix' => 'MISC-',
        ]);
    }

    public function bank(): static
    {
        return $this->state(fn () => [
            'name' => 'Bank',
            'type' => Journal::TYPE_BANK,
            'sequence_prefix' => 'BNK-',
        ]);
    }
}
