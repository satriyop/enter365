<?php

namespace Database\Factories\Purchasing;

use App\Models\Inventory\Product;
use App\Models\Purchasing\BillItem;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseReturnItem>
 */
class PurchaseReturnItemFactory extends Factory
{
    protected $model = PurchaseReturnItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
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
            'purchase_return_id' => PurchaseReturn::factory(),
            'bill_item_id' => null,
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
                PurchaseReturnItem::CONDITION_GOOD,
                PurchaseReturnItem::CONDITION_DAMAGED,
                PurchaseReturnItem::CONDITION_DEFECTIVE,
            ]),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific purchase return.
     */
    public function forPurchaseReturn(PurchaseReturn $purchaseReturn): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_return_id' => $purchaseReturn->id,
        ]);
    }

    /**
     * From bill item.
     */
    public function fromBillItem(?BillItem $billItem = null): static
    {
        return $this->state(function (array $attributes) use ($billItem) {
            $item = $billItem ?? BillItem::factory()->create();

            return [
                'bill_item_id' => $item->id,
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
            'condition' => PurchaseReturnItem::CONDITION_GOOD,
        ]);
    }

    /**
     * Damaged condition.
     */
    public function damaged(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => PurchaseReturnItem::CONDITION_DAMAGED,
        ]);
    }

    /**
     * Defective condition.
     */
    public function defective(): static
    {
        return $this->state(fn (array $attributes) => [
            'condition' => PurchaseReturnItem::CONDITION_DEFECTIVE,
        ]);
    }
}
