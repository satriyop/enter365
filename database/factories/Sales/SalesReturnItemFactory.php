<?php

namespace Database\Factories\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturnItem>
 */
class SalesReturnItemFactory extends Factory
{
    protected $model = SalesReturnItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 10);
        $unitPrice = $this->faker->numberBetween(10000, 500000);
        $subtotal = (int) round($quantity * $unitPrice);
        $discountPercent = $this->faker->randomElement([0, 5, 10]);
        $discountAmount = (int) round($subtotal * $discountPercent / 100);
        $afterDiscount = $subtotal - $discountAmount;
        $taxRate = $this->faker->randomElement([0, 11]);
        $taxAmount = (int) round($afterDiscount * $taxRate / 100);
        $lineTotal = $afterDiscount + $taxAmount;

        return [
            'sales_return_id' => SalesReturn::factory(),
            'invoice_item_id' => null,
            'product_id' => null,
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'set', 'box', 'kg', 'm']),
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'sort_order' => 0,
            'condition' => $this->faker->randomElement([
                SalesReturnItem::CONDITION_GOOD,
                SalesReturnItem::CONDITION_DAMAGED,
                SalesReturnItem::CONDITION_DEFECTIVE,
            ]),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific sales return.
     */
    public function forSalesReturn(SalesReturn $salesReturn): static
    {
        return $this->state(fn (array $attributes) => [
            'sales_return_id' => $salesReturn->id,
        ]);
    }

    /**
     * From invoice item.
     */
    public function fromInvoiceItem(?InvoiceItem $invoiceItem = null): static
    {
        return $this->state(function (array $attributes) use ($invoiceItem) {
            $item = $invoiceItem ?? InvoiceItem::factory()->create();

            return [
                'invoice_item_id' => $item->id,
                'product_id' => $item->product_id,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'discount_amount' => $item->discount_amount,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'line_total' => $item->line_total,
                'sort_order' => $item->sort_order,
            ];
        });
    }

    /**
     * With product.
     */
    public function withProduct(?Product $product = null): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $p = $product ?? Product::factory()->create();

            return [
                'product_id' => $p->id,
                'description' => $p->name,
                'unit' => $p->unit,
            ];
        });
    }

    /**
     * With specific quantity.
     */
    public function withQuantity(float $quantity): static
    {
        return $this->state(function (array $attributes) use ($quantity) {
            $unitPrice = $attributes['unit_price'] ?? 100000;
            $discountPercent = $attributes['discount_percent'] ?? 0;
            $taxRate = $attributes['tax_rate'] ?? 0;

            $subtotal = (int) round($quantity * $unitPrice);
            $discountAmount = (int) round($subtotal * $discountPercent / 100);
            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = (int) round($afterDiscount * $taxRate / 100);
            $lineTotal = $afterDiscount + $taxAmount;

            return [
                'quantity' => $quantity,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ];
        });
    }

    /**
     * With specific line total.
     */
    public function withLineTotal(int $unitPrice, float $quantity = 1): static
    {
        return $this->state(function (array $attributes) use ($unitPrice, $quantity) {
            $discountPercent = $attributes['discount_percent'] ?? 0;
            $taxRate = $attributes['tax_rate'] ?? 0;

            $subtotal = (int) round($quantity * $unitPrice);
            $discountAmount = (int) round($subtotal * $discountPercent / 100);
            $afterDiscount = $subtotal - $discountAmount;
            $taxAmount = (int) round($afterDiscount * $taxRate / 100);
            $lineTotal = $afterDiscount + $taxAmount;

            return [
                'unit_price' => $unitPrice,
                'quantity' => $quantity,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $lineTotal,
            ];
        });
    }

    /**
     * Good condition.
     */
    public function goodCondition(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => SalesReturnItem::CONDITION_GOOD,
        ]);
    }

    /**
     * Damaged condition.
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => SalesReturnItem::CONDITION_DAMAGED,
        ]);
    }

    /**
     * Defective condition.
     */
    public function defective(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => SalesReturnItem::CONDITION_DEFECTIVE,
        ]);
    }
}
