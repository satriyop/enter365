<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Payment
 */
class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'type' => $this->type,
            'contact_id' => $this->contact_id,
            'payment_date' => $this->payment_date?->toDateString(),
            'amount' => $this->amount,
            'payment_method' => $this->payment_method,
            'reference' => $this->reference,
            'notes' => $this->notes,
            'cash_account_id' => $this->cash_account_id,
            'journal_entry_id' => $this->journal_entry_id,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'is_voided' => $this->is_voided,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'cash_account' => new AccountResource($this->whenLoaded('cashAccount')),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
