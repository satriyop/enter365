<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GoodsReceiptNoteItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'goods_receipt_note_id' => $this->goods_receipt_note_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
            ]),

            // Quantities
            'quantity_ordered' => $this->quantity_ordered,
            'quantity_received' => $this->quantity_received,
            'quantity_rejected' => $this->quantity_rejected,
            'quantity_remaining' => $this->getQuantityRemaining(),

            // Status
            'is_received' => $this->isReceived(),
            'is_fully_received' => $this->isFullyReceived(),
            'has_rejections' => $this->hasRejections(),

            // Pricing
            'unit_price' => $this->unit_price,
            'total_value_received' => $this->getTotalValueReceived(),

            // Quality
            'rejection_reason' => $this->rejection_reason,
            'quality_notes' => $this->quality_notes,

            // Lot tracking
            'lot_number' => $this->lot_number,
            'expiry_date' => $this->expiry_date?->toDateString(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
