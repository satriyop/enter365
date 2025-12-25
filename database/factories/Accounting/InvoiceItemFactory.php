<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    protected $model = InvoiceItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitPrice = $this->faker->randomElement([50000, 100000, 250000, 500000, 1000000]);
        $amount = (int) round($quantity * $unitPrice);

        return [
            'invoice_id' => Invoice::factory(),
            'product_id' => null,
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['unit', 'pcs', 'kg', 'liter', 'jam']),
            'unit_price' => $unitPrice,
            'amount' => $amount,
            'revenue_account_id' => null,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'description' => $product->name,
            'unit' => $product->unit,
            'unit_price' => $product->selling_price,
            'amount' => (int) round(($attributes['quantity'] ?? 1) * $product->selling_price),
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
        ]);
    }

    public function withRevenueAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'revenue_account_id' => $account->id,
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
