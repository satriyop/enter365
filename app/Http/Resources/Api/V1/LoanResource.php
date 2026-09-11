<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Loan
 */
class LoanResource extends JsonResource
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
            'contact_id' => $this->contact_id,
            'principal' => $this->principal,
            'annual_interest_rate' => (float) $this->annual_interest_rate,
            'duration_months' => $this->duration_months,
            'start_date' => $this->start_date?->toDateString(),
            'liability_account_id' => $this->liability_account_id,
            'interest_account_id' => $this->interest_account_id,
            'bank_account_id' => $this->bank_account_id,
            'journal_id' => $this->journal_id,
            'status' => $this->status,
            'remaining_principal' => $this->remaining_principal,
            'disbursement_journal_entry_id' => $this->disbursement_journal_entry_id,
            'notes' => $this->notes,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact === null ? null : [
                'id' => $this->contact->id,
                'code' => $this->contact->code,
                'name' => $this->contact->name,
            ]),
            'lines' => LoanLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
