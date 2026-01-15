<?php

namespace Database\Factories\Purchasing;

use App\Models\Inventory\Product;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 10);
        $unitPrice = $this->faker->randomElement([100000, 250000, 500000, 1000000, 2500000]);
        $grossAmount = (int) round($quantity * $unitPrice);
        $discountPercent = 0;
        $discountAmount = 0;
        $taxRate = 11.00;
        $netAmount = $grossAmount - $discountAmount;
        $taxAmount = (int) round($netAmount * ($taxRate / 100));
        $lineTotal = $netAmount;

        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'product_id' => null,
            'description' => $this->faker->sentence(3),
            'quantity' => $quantity,
            'quantity_received' => 0,
            'unit' => $this->faker->randomElement(['unit', 'pcs', 'set', 'lot', 'm', 'm2', 'kg']),
            'unit_price' => $unitPrice,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'sort_order' => 0,
            'notes' => null,
            'last_received_at' => null,
            'expense_account_id' => null,
        ];
    }

    public function forPurchaseOrder(PurchaseOrder $purchaseOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_order_id' => $purchaseOrder->id,
        ]);
    }

    public function withProduct(Product $product): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $quantity = $attributes['quantity'];
            $unitPrice = $product->purchase_price ?? $product->selling_price;
            $grossAmount = (int) round($quantity * $unitPrice);
            $netAmount = $grossAmount;
            $taxRate = $attributes['tax_rate'];
            $taxAmount = (int) round($netAmount * ($taxRate / 100));

            return [
                'product_id' => $product->id,
                'description' => $product->name,
                'unit' => $product->unit,
                'unit_price' => $unitPrice,
                'line_total' => $netAmount,
                'tax_amount' => $taxAmount,
                'expense_account_id' => $product->purchase_account_id ?? $product->cogs_account_id,
            ];
        });
    }

    public function withDiscount(float $percent): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $grossAmount = (int) round($attributes['quantity'] * $attributes['unit_price']);
            $discountAmount = (int) round($grossAmount * ($percent / 100));
            $netAmount = $grossAmount - $discountAmount;
            $taxAmount = (int) round($netAmount * ($attributes['tax_rate'] / 100));

            return [
                'discount_percent' => $percent,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'line_total' => $netAmount,
            ];
        });
    }

    public function withoutTax(): static
    {
        return $this->state(fn (array $attributes) => [
            'tax_rate' => 0,
            'tax_amount' => 0,
        ]);
    }

    public function withAmount(int $quantity, int $unitPrice): static
    {
        return $this->state(function (array $attributes) use ($quantity, $unitPrice) {
            $grossAmount = $quantity * $unitPrice;
            $taxRate = $attributes['tax_rate'];
            $taxAmount = (int) round($grossAmount * ($taxRate / 100));

            return [
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $grossAmount,
                'tax_amount' => $taxAmount,
            ];
        });
    }

    public function partiallyReceived(float $receivedQty): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $receivedQty,
            'last_received_at' => now(),
        ]);
    }

    public function fullyReceived(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_received' => $attributes['quantity'],
            'last_received_at' => now(),
        ]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $position,
        ]);
    }
}
