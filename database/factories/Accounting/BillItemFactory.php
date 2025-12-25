<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillItem>
 */
class BillItemFactory extends Factory
{
    protected $model = BillItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomElement([50000, 100000, 250000, 500000, 1000000]);
        $amount = (int) round($quantity * $unitPrice);

        return [
            'bill_id' => Bill::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['unit', 'pcs', 'kg', 'liter', 'jam']),
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'expense_account_id' => null,
        ];
    }

    public function forBill(Bill $bill): static
    {
        return $this->state(fn (array $attributes) => [
            'bill_id' => $bill->id,
        ]);
    }

    public function withExpenseAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'expense_account_id' => $account->id,
        ]);
    }

    public function withAmount(int $unitPrice, float $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'amount' => (int) round($quantity * $unitPrice),
        ]);
    }
}
