<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   payment_number: string,
     *   type: string,
     *   contact_id: int,
     *   payment_date: string|null,
     *   amount: int,
     *   payment_method: string,
     *   reference: string|null,
     *   notes: string|null,
     *   cash_account_id: int,
     *   journal_entry_id: int|null,
     *   payable_type: string|null,
     *   payable_id: int|null,
     *   is_voided: bool,
     *   contact?: array{id: int, name: string},
     *   cash_account?: array{id: int, name: string, code: string},
     *   journal_entry?: array{id: int, entry_number: string},
     *   created_by: int|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
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
