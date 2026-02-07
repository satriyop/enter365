<?php

namespace Database\Factories\Purchasing;

use App\Models\Inventory\Product;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\GoodsReceiptNoteItem;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Purchasing\GoodsReceiptNoteItem>
 */
class GoodsReceiptNoteItemFactory extends Factory
{
    protected $model = GoodsReceiptNoteItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $qtyOrdered = $this->faker->numberBetween(10, 100);

        return [
            'goods_receipt_note_id' => GoodsReceiptNote::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
            'product_id' => Product::factory(),
            'quantity_ordered' => $qtyOrdered,
            'quantity_received' => 0,
            'quantity_rejected' => 0,
            'unit_price' => $this->faker->numberBetween(10000, 500000),
            'rejection_reason' => null,
            'quality_notes' => null,
            'lot_number' => null,
            'expiry_date' => null,
        ];
    }

    /**
     * Indicate the item has been fully received.
     */
    public function fullyReceived(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'quantity_received' => $attributes['quantity_ordered'],
                'quantity_rejected' => 0,
            ];
        });
    }

    /**
     * Indicate the item has been partially received.
     */
    public function partiallyReceived(): static
    {
        return $this->state(function (array $attributes) {
            $received = (int) ($attributes['quantity_ordered'] * 0.7);

            return [
                'quantity_received' => $received,
                'quantity_rejected' => 0,
            ];
        });
    }

    /**
     * Indicate the item has rejections.
     */
    public function withRejections(int $rejectedQty = 5): static
    {
        return $this->state(function (array $attributes) use ($rejectedQty) {
            $received = $attributes['quantity_ordered'] - $rejectedQty;

            return [
                'quantity_received' => $received,
                'quantity_rejected' => $rejectedQty,
                'rejection_reason' => 'Damaged during shipping',
            ];
        });
    }

    /**
     * Assign to a specific GRN.
     */
    public function forGoodsReceiptNote(GoodsReceiptNote $grn): static
    {
        return $this->state(fn (array $attributes) => [
            'goods_receipt_note_id' => $grn->id,
        ]);
    }

    /**
     * Assign to a specific PO item.
     */
    public function forPurchaseOrderItem(PurchaseOrderItem $item): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_order_item_id' => $item->id,
            'product_id' => $item->product_id,
            'quantity_ordered' => $item->quantity - $item->quantity_received,
            'unit_price' => $item->unit_price,
        ]);
    }
}
