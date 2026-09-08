<?php

namespace App\Http\Resources\Api\V1;

use App\Casts\AnalyticDistributionCast;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\BillItem
 */
class BillItemResource extends JsonResource
{
    /**
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
     *   product_id: int|null,
     *   tax_record_ids: list<int>|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        $taxRecordIds = $this->taxRecordIds();

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
            'account_id' => $this->expense_account_id,
            'purchase_order_item_id' => $this->purchase_order_item_id,
            'expense_account' => new AccountResource($this->whenLoaded('expenseAccount')),
            'analytic_distribution' => AnalyticDistributionCast::forApi($this->analytic_distribution),
            'tax_tag_ids' => $this->tax_tag_ids,
            'tax_record_ids' => $taxRecordIds === [] ? null : $taxRecordIds,
            'product_id' => $this->product_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
