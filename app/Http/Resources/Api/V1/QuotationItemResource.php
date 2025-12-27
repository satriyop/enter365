<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\QuotationItem
 */
class QuotationItemResource extends JsonResource
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
            'quotation_id' => $this->quotation_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),

            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price,
            'gross_amount' => $this->getGrossAmount(),

            'discount_percent' => (float) $this->discount_percent,
            'discount_amount' => $this->discount_amount,

            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,

            'line_total' => $this->line_total,
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'revenue_account_id' => $this->revenue_account_id,
            'revenue_account' => $this->whenLoaded('revenueAccount', fn () => [
                'id' => $this->revenueAccount->id,
                'code' => $this->revenueAccount->code,
                'name' => $this->revenueAccount->name,
            ]),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
