<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Shared\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'type' => $this->type,
            'contact_id' => $this->contact_id,
            'payment_date' => $this->payment_date->toDateString(),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate,
            'base_currency_amount' => $this->base_currency_amount,
            'pph_category' => $this->pph_category instanceof \App\Enums\PphCategory
                ? $this->pph_category->value
                : $this->pph_category,
            'pph_rate' => $this->pph_rate,
            'pph_base_amount' => $this->pph_base_amount,
            'pph_amount' => $this->pph_amount,
            'pph_account_id' => $this->pph_account_id,
            'cash_disbursement' => $this->getCashDisbursement(),
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'cash_account_id' => $this->cash_account_id,
            'journal_entry_id' => $this->journal_entry_id,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'is_voided' => $this->is_voided,
            'voided_at' => $this->when($this->is_voided, fn () => $this->voided_at
                ? \Carbon\Carbon::parse($this->voided_at)->toIso8601String()
                : null),
            'voided_by' => $this->when($this->is_voided, fn () => $this->voided_by),
            'void_reason' => $this->when($this->is_voided, fn () => $this->void_reason),
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'cash_account' => new AccountResource($this->whenLoaded('cashAccount')),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'allocations' => $this->whenLoaded('allocations', fn () => $this->allocations->map(fn ($alloc) => [
                'id' => $alloc->id,
                'allocatable_type' => $alloc->allocatable_type,
                'allocatable_id' => $alloc->allocatable_id,
                'amount' => $alloc->amount,
            ])),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
