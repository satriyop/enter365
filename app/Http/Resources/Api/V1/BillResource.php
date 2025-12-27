<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Bill
 */
class BillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bill_number' => $this->bill_number,
            'vendor_invoice_number' => $this->vendor_invoice_number,
            'contact_id' => $this->contact_id,
            'bill_date' => $this->bill_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'tax_rate' => (float) $this->tax_rate,
            'discount_amount' => $this->discount_amount,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'outstanding_amount' => $this->getOutstandingAmount(),
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'payable_account_id' => $this->payable_account_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),
            'items' => BillItemResource::collection($this->whenLoaded('items')),
            'journal_entry' => new JournalEntryResource($this->whenLoaded('journalEntry')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
            'created_by' => $this->created_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
