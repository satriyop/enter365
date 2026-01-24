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
     *   product_id: int|null,
     *   product?: ProductResource,
     *   description: string,
     *   quantity: float,
     *   quantity_received: float,
     *   quantity_remaining: float,
     *   unit: string,
     *   unit_price: int,
     *   gross_amount: int,
     *   discount_percent: float,
     *   discount_amount: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   line_total: int,
     *   sort_order: int,
     *   notes: string|null,
     *   expense_account_id: int|null,
     *   expense_account?: array{id: int, code: string, name: string},
     *   is_fully_received: bool,
     *   receiving_progress: float,
     *   last_received_at: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_order_id' => $this->purchase_order_id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),

            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'quantity_received' => (float) $this->quantity_received,
            'quantity_remaining' => $this->getQuantityRemaining(),
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
            'expense_account_id' => $this->expense_account_id,
            'expense_account' => $this->whenLoaded('expenseAccount', fn () => [
                'id' => $this->expenseAccount->id,
                'code' => $this->expenseAccount->code,
                'name' => $this->expenseAccount->name,
            ]),

            // Receiving info
            'is_fully_received' => $this->isFullyReceived(),
            'receiving_progress' => $this->getReceivingProgress(),
            'last_received_at' => $this->last_received_at?->toIso8601String(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
