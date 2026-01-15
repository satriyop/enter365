<?php

namespace Database\Factories\Sales;

use App\Models\Inventory\Product;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\DeliveryOrderItem;
use App\Models\Sales\InvoiceItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrderItem>
 */
class DeliveryOrderItemFactory extends Factory
{
    protected $model = DeliveryOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'delivery_order_id' => DeliveryOrder::factory(),
            'invoice_item_id' => null,
            'product_id' => null,
            'description' => $this->faker->words(3, true),
            'quantity' => $this->faker->randomFloat(2, 1, 100),
            'quantity_delivered' => 0,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'set', 'box', 'kg', 'm']),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific delivery order.
     */
    public function forDeliveryOrder(DeliveryOrder $deliveryOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_order_id' => $deliveryOrder->id,
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
     * Partially delivered.
     */
    public function partiallyDelivered(?float $deliveredQty = null): static
    {
        return $this->state(function (array $attributes) use ($deliveredQty) {
            $quantity = $attributes['quantity'] ?? 10;
            $delivered = $deliveredQty ?? $quantity * 0.5;

            return [
                'quantity' => $quantity,
                'quantity_delivered' => $delivered,
            ];
        });
    }

    /**
     * Fully delivered.
     */
    public function fullyDelivered(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity'] ?? 10;

            return [
                'quantity_delivered' => $quantity,
            ];
        });
    }

    /**
     * With specific quantity.
     */
    public function withQuantity(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
        ]);
    }
}
