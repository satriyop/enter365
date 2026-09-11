<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AnalyticPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnalyticPlan
 */
class AnalyticPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'parent_id' => $this->parent_id,
            'default_applicability' => $this->default_applicability,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'analytic_accounts_count' => $this->whenCounted('analyticAccounts'),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent === null ? null : [
                'id' => $this->parent->id,
                'code' => $this->parent->code,
                'name' => $this->parent->name,
            ]),
            'analytic_accounts' => $this->whenLoaded('analyticAccounts', fn () => $this->analyticAccounts->map(fn ($account) => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
