<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\BudgetLine
 */
class BudgetLineResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   budget_id: int,
     *   account_id: int,
     *   account?: array{id: int, code: string, name: string, type: string},
     *   jan_amount: int,
     *   feb_amount: int,
     *   mar_amount: int,
     *   apr_amount: int,
     *   may_amount: int,
     *   jun_amount: int,
     *   jul_amount: int,
     *   aug_amount: int,
     *   sep_amount: int,
     *   oct_amount: int,
     *   nov_amount: int,
     *   dec_amount: int,
     *   annual_amount: int,
     *   monthly_amounts: array<int, int>,
     *   q1_amount: int,
     *   q2_amount: int,
     *   q3_amount: int,
     *   q4_amount: int,
     *   notes: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'budget_id' => $this->budget_id,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => [
                'id' => $this->account->id,
                'code' => $this->account->code,
                'name' => $this->account->name,
                'type' => $this->account->type,
            ]),

            // Monthly amounts
            'jan_amount' => $this->jan_amount,
            'feb_amount' => $this->feb_amount,
            'mar_amount' => $this->mar_amount,
            'apr_amount' => $this->apr_amount,
            'may_amount' => $this->may_amount,
            'jun_amount' => $this->jun_amount,
            'jul_amount' => $this->jul_amount,
            'aug_amount' => $this->aug_amount,
            'sep_amount' => $this->sep_amount,
            'oct_amount' => $this->oct_amount,
            'nov_amount' => $this->nov_amount,
            'dec_amount' => $this->dec_amount,

            // Summary
            'annual_amount' => $this->annual_amount,
            'monthly_amounts' => $this->getMonthlyAmounts(),

            // Quarterly summary
            'q1_amount' => $this->getQuarterAmount(1),
            'q2_amount' => $this->getQuarterAmount(2),
            'q3_amount' => $this->getQuarterAmount(3),
            'q4_amount' => $this->getQuarterAmount(4),

            'notes' => $this->notes,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
