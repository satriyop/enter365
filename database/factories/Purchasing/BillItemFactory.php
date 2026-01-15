<?php

namespace Database\Factories\Purchasing;

use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
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
        $lineTotal = (int) round($quantity * $unitPrice);

        return [
            'bill_id' => Bill::factory(),
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['unit', 'pcs', 'kg', 'liter', 'jam']),
            'unit_price' => $unitPrice,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'line_total' => $lineTotal,
            'sort_order' => 0,
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
            'line_total' => (int) round($quantity * $unitPrice),
        ]);
    }
}
