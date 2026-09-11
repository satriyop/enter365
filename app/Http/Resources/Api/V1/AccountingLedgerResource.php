<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\AccountingLedger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AccountingLedger
 */
class AccountingLedgerResource extends JsonResource
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
            'currency_code' => $this->currency_code,
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency === null ? null : [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'name' => $this->currency->name,
                'symbol' => $this->currency->symbol,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
