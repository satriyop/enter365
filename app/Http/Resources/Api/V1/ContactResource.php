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
     *   currency: string,
     *   payment_term_days: int,
     *   early_discount_percent: float|null,
     *   early_discount_days: int|null,
     *   bank_name: string|null,
     *   bank_account_number: string|null,
     *   bank_account_name: string|null,
     *   is_subcontractor: bool,
     *   subcontractor_services: array|string|null,
     *   hourly_rate: int|null,
     *   daily_rate: int|null,
     *   notes: string|null,
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

            // Payment terms
            'credit_limit' => $this->credit_limit,
            'currency' => $this->currency,
            'payment_term_days' => $this->payment_term_days,
            'early_discount_percent' => $this->early_discount_percent,
            'early_discount_days' => $this->early_discount_days,

            // Bank account details
            'bank_name' => $this->bank_name,
            'bank_account_number' => $this->bank_account_number,
            'bank_account_name' => $this->bank_account_name,

            // Subcontractor fields
            'is_subcontractor' => $this->is_subcontractor,
            'subcontractor_services' => $this->subcontractor_services,
            'hourly_rate' => $this->hourly_rate,
            'daily_rate' => $this->daily_rate,

            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'receivable_balance' => $this->whenAppended('receivable_balance'),
            'payable_balance' => $this->whenAppended('payable_balance'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
