<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\CashRounding;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashRounding
 */
class CashRoundingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'rounding' => $this->rounding,
            'strategy' => $this->strategy,
            'profit_account_id' => $this->profit_account_id,
            'loss_account_id' => $this->loss_account_id,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'profit_account' => $this->whenLoaded('profitAccount', fn () => $this->profitAccount === null ? null : [
                'id' => $this->profitAccount->id,
                'code' => $this->profitAccount->code,
                'name' => $this->profitAccount->name,
            ]),
            'loss_account' => $this->whenLoaded('lossAccount', fn () => $this->lossAccount === null ? null : [
                'id' => $this->lossAccount->id,
                'code' => $this->lossAccount->code,
                'name' => $this->lossAccount->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
