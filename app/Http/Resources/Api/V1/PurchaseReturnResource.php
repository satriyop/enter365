<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Purchasing\PurchaseReturn
 */
class PurchaseReturnResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   return_number: string,
     *   bill_id: int|null,
     *   bill?: array{id: int, bill_number: string, total_amount: int},
     *   contact_id: int,
     *   contact?: array{id: int, name: string, address: string|null, phone: string|null},
     *   warehouse_id: int,
     *   warehouse?: array{id: int, name: string},
     *   return_date: string,
     *   reason: string|null,
     *   notes: string|null,
     *   subtotal: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   total_amount: int,
     *   status: StatusResource,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   items_count?: int,
     *   journal_entry_id: int|null,
     *   journal_entry?: array{id: int, entry_number: string},
     *   debit_note_id: int|null,
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   submitted_by: int|null,
     *   submitted_at: string|null,
     *   approved_by: int|null,
     *   approved_at: string|null,
     *   rejected_by: int|null,
     *   rejected_at: string|null,
     *   rejection_reason: string|null,
     *   completed_by: int|null,
     *   completed_at: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_number' => $this->return_number,
            'bill_id' => $this->bill_id,
            'bill' => $this->whenLoaded('bill', fn () => [
                'id' => $this->bill->id,
                'bill_number' => $this->bill->bill_number,
                'total_amount' => $this->bill->total_amount,
            ]),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'address' => $this->contact->address,
                'phone' => $this->contact->phone,
            ]),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'return_date' => $this->return_date instanceof \Carbon\Carbon ? $this->return_date->toDateString() : $this->return_date,
            'reason' => $this->reason,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => new StatusResource($this->status),
            'items' => PurchaseReturnItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'journal_entry_id' => $this->journal_entry_id,
            'journal_entry' => $this->whenLoaded('journalEntry', fn () => [
                'id' => $this->journalEntry->id,
                'entry_number' => $this->journalEntry->entry_number,
            ]),
            'debit_note_id' => $this->debit_note_id,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'submitted_by' => $this->submitted_by,
            'submitted_at' => $this->submitted_at instanceof \Carbon\Carbon ? $this->submitted_at->toIso8601String() : null,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at instanceof \Carbon\Carbon ? $this->approved_at->toIso8601String() : null,
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at instanceof \Carbon\Carbon ? $this->rejected_at->toIso8601String() : null,
            'rejection_reason' => $this->rejection_reason,
            'completed_by' => $this->completed_by,
            'completed_at' => $this->completed_at instanceof \Carbon\Carbon ? $this->completed_at->toIso8601String() : null,
            'created_at' => $this->created_at instanceof \Carbon\Carbon ? $this->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->updated_at instanceof \Carbon\Carbon ? $this->updated_at->toIso8601String() : $this->updated_at,
        ];
    }
}
