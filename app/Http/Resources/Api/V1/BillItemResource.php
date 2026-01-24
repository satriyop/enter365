<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\BillItem
 */
class BillItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   bill_id: int,
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
     *   notes: string|null,
     *   expense_account_id: int|null,
     *   expense_account?: AccountResource,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_id' => $this->bill_id,
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
            'notes' => $this->notes,
            'expense_account_id' => $this->expense_account_id,
            'expense_account' => new AccountResource($this->whenLoaded('expenseAccount')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
