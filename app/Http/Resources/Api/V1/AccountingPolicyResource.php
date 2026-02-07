<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\AccountingPolicy
 */
class AccountingPolicyResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   inventory_method: string,
     *   cogs_recognition: string,
     *   return_accounting: string,
     *   manufacturing_costing: string,
     *   closing_strategy: string,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_method' => $this->inventory_method,
            'cogs_recognition' => $this->cogs_recognition,
            'return_accounting' => $this->return_accounting,
            'manufacturing_costing' => $this->manufacturing_costing,
            'closing_strategy' => $this->closing_strategy,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
