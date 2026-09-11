<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Accounting\PaymentMethod */
class PaymentMethodResource extends JsonResource
{
    /** @return array{id: int, code: string, name: string, is_active: bool, direction: string, payment_type: string, journal_id: int|null} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'direction' => $this->direction,
            'payment_type' => $this->payment_type,
            'journal_id' => $this->journal_id,
        ];
    }
}
