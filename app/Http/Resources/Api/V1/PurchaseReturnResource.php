<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseReturnResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
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
            'return_date' => $this->return_date->format('Y-m-d'),
            'reason' => $this->reason,
            'notes' => $this->notes,
            'subtotal' => $this->subtotal,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
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
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'rejected_by' => $this->rejected_by,
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'rejection_reason' => $this->rejection_reason,
            'completed_by' => $this->completed_by,
            'completed_at' => $this->completed_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
