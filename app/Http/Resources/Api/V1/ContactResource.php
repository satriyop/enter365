<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Contacts\Contact
 */
class ContactResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   type: string,
     *   email: string|null,
     *   phone: string|null,
     *   address: string|null,
     *   city: string|null,
     *   province: string|null,
     *   postal_code: string|null,
     *   npwp: string|null,
     *   nik: string|null,
     *   credit_limit: int,
     *   payment_term_days: int,
     *   is_active: bool,
     *   receivable_balance?: int,
     *   payable_balance?: int,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postal_code,
            'npwp' => $this->npwp,
            'nik' => $this->nik,
            'credit_limit' => $this->credit_limit,
            'payment_term_days' => $this->payment_term_days,
            'is_active' => $this->is_active,
            'receivable_balance' => $this->whenAppended('receivable_balance'),
            'payable_balance' => $this->whenAppended('payable_balance'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
