<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AnalyticBudgetLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnalyticBudgetLine
 */
class AnalyticBudgetLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'analytic_budget_id' => $this->analytic_budget_id,
            'analytic_account_id' => $this->analytic_account_id,
            'planned_amount' => $this->planned_amount,
            'actual_amount' => $this->getAttribute('actual_amount') ?? 0,
            'variance' => $this->getAttribute('variance') ?? 0,
            'analytic_account' => $this->whenLoaded('analyticAccount', fn () => $this->analyticAccount === null ? null : [
                'id' => $this->analyticAccount->id,
                'code' => $this->analyticAccount->code,
                'name' => $this->analyticAccount->name,
            ]),
        ];
    }
}
