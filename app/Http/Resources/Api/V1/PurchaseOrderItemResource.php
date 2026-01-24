<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\PurchaseOrderItem
 */
class PurchaseOrderItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   purchase_order_id: int,
     *   product_id: int,
     *   product?: array{id: int, sku: string, name: string, unit: string},
     *   description: string,
     *   quantity: float,
     *   unit: string,
     *   unit_price: int,
     *   subtotal: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   total_amount: int,
     *   quantity_received: float,
     *   quantity_remaining: float,
     *   delivery_date: string|null,
     *   delivery_status: string,
     *   created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var \Carbon\Carbon|null $createdAt */
        $createdAt = $this->created_at;

        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'sku' => $this->product->sku,
                'name' => $this->product->name,
                'unit' => $this->product->unit,
            ]),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->line_total,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->line_total + $this->tax_amount,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_remaining' => $this->getQuantityRemaining(),
            'delivery_date' => $this->last_received_at instanceof \Carbon\Carbon ? $this->last_received_at->toDateString() : null,
            'delivery_status' => $this->isFullyReceived() ? 'Received' : 'Pending',
            'created_at' => $this->created_at instanceof \Carbon\Carbon ? $this->created_at->toIso8601String() : '',
        ];
    }
}