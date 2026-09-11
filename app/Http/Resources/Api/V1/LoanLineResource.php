<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\LoanLine;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LoanLine
 */
class LoanLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'loan_id' => $this->loan_id,
            'sequence' => $this->sequence,
            'due_date' => $this->due_date?->toDateString(),
            'principal_amount' => $this->principal_amount,
            'interest_amount' => $this->interest_amount,
            'payment_amount' => $this->payment_amount,
            'remaining_principal' => $this->remaining_principal,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'posted_at' => $this->posted_at?->toIso8601String(),
        ];
    }
}
