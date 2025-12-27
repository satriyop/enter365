<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Budget
 */
class BudgetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'fiscal_period_id' => $this->fiscal_period_id,
            'fiscal_period' => $this->whenLoaded('fiscalPeriod', fn () => [
                'id' => $this->fiscalPeriod->id,
                'name' => $this->fiscalPeriod->name,
                'start_date' => $this->fiscalPeriod->start_date->toDateString(),
                'end_date' => $this->fiscalPeriod->end_date->toDateString(),
            ]),
            'type' => $this->type,
            'type_label' => $this->type_label,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'is_editable' => $this->isEditable(),

            // Totals
            'total_revenue' => $this->total_revenue,
            'total_expense' => $this->total_expense,
            'net_budget' => $this->net_budget,

            // Approval info
            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->whenLoaded('approvedByUser', fn () => [
                'id' => $this->approvedByUser->id,
                'name' => $this->approvedByUser->name,
            ]),
            'approved_at' => $this->approved_at?->toIso8601String(),

            'notes' => $this->notes,

            // Lines
            'lines' => BudgetLineResource::collection($this->whenLoaded('lines')),
            'lines_count' => $this->whenCounted('lines'),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
