<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Accounting\PaymentProvider */
class PaymentProviderResource extends JsonResource
{
    /** @return array{id: int, code: string, name: string, is_active: bool, state: string, journal_id: int|null, website: string|null, payment_method_ids: list<int>} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'state' => $this->state,
            'journal_id' => $this->journal_id,
            'website' => $this->website,
            /** @var list<int> */
            'payment_method_ids' => $this->whenLoaded('paymentMethods', fn () => $this->paymentMethods->pluck('id')->all()),
        ];
    }
}
