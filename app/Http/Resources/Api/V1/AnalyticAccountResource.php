<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\AnalyticAccount
 */
class AnalyticAccountResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, is_active: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'analytic_plan_id' => $this->analytic_plan_id,
            'plan' => $this->whenLoaded('plan', fn () => $this->plan === null ? null : [
                'id' => $this->plan->id,
                'code' => $this->plan->code,
                'name' => $this->plan->name,
            ]),
            'is_active' => $this->is_active,
        ];
    }
}
