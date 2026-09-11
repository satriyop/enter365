<?php

namespace App\Http\Resources\Api\V1;

use App\Casts\AnalyticDistributionCast;
use App\Models\Accounting\AnalyticDistributionModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AnalyticDistributionModel
 */
class AnalyticDistributionModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'partner_id' => $this->partner_id,
            'account_prefix' => $this->account_prefix,
            'product_id' => $this->product_id,
            'analytic_distribution' => AnalyticDistributionCast::forApi($this->analytic_distribution),
            'sequence' => $this->sequence,
            'is_active' => $this->is_active,
            'partner' => $this->whenLoaded('partner', fn () => $this->partner === null ? null : [
                'id' => $this->partner->id,
                'name' => $this->partner->name,
            ]),
            'product' => $this->whenLoaded('product', fn () => $this->product === null ? null : [
                'id' => $this->product->id,
                'name' => $this->product->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
