<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\Bill
 */
class BillResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   bill_number: string,
     *   vendor_invoice_number: string|null,
     *   contact_id: int,
     *   bill_date: string|null,
     *   due_date: string|null,
     *   description: string|null,
     *   reference: string|null,
     *   subtotal: int,
     *   tax_amount: int,
     *   tax_rate: float,
     *   discount_amount: int,
     *   total_amount: int,
     *   paid_amount: int,
     *   outstanding_amount: int,
     *   status: string,
     *   journal_entry_id: int|null,
     *   payable_account_id: int|null,
     *   contact?: array{id: int, name: string},
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   journal_entry?: array{id: int, entry_number: string},
     *   payments?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   created_by: int|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
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
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_terminal' => $this->status->isTerminal(),
                'is_editable' => $this->status->isEditable(),
            ],
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
