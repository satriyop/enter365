<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\SalesReturnItem
 */
class SalesReturnItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   sales_return_id: int,
     *   invoice_item_id: int|null,
     *   product_id: int|null,
     *   product?: array{id: int, name: string, sku: string},
     *   description: string,
     *   quantity: float,
     *   unit: string,
     *   unit_price: int,
     *   discount_percent: float,
     *   discount_amount: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   line_total: int,
     *   sort_order: int,
     *   condition: string|null,
     *   notes: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sales_return_id' => $this->sales_return_id,
            'invoice_item_id' => $this->invoice_item_id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'line_total' => $this->line_total,
            'sort_order' => $this->sort_order,
            'condition' => $this->condition,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
