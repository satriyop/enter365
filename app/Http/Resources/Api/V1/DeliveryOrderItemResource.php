<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\DeliveryOrderItem
 */
class DeliveryOrderItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   delivery_order_id: int,
     *   invoice_item_id: int|null,
     *   product_id: int,
     *   product?: array{id: int, name: string, sku: string},
     *   description: string,
     *   quantity: float,
     *   quantity_delivered: float,
     *   remaining_quantity: float,
     *   unit: string,
     *   notes: string|null,
     *   is_fully_delivered: bool,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'delivery_order_id' => $this->delivery_order_id,
            'invoice_item_id' => $this->invoice_item_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'quantity_delivered' => (float) $this->quantity_delivered,
            'remaining_quantity' => $this->getRemainingQuantity(),
            'unit' => $this->unit,
            'notes' => $this->notes,
            'is_fully_delivered' => $this->isFullyDelivered(),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
