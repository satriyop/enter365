<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DownPaymentResource extends JsonResource
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
            'dp_number' => $this->dp_number,
            'type' => $this->type,
            'type_label' => $this->type === 'receivable' ? 'Uang Muka Penjualan' : 'Uang Muka Pembelian',
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'email' => $this->contact->email,
            ]),
            'dp_date' => $this->dp_date->format('Y-m-d'),
            'amount' => $this->amount,
            'applied_amount' => $this->applied_amount,
            'remaining_amount' => $this->getRemainingAmount(),
            'payment_method' => $this->payment_method,
            'cash_account_id' => $this->cash_account_id,
            'cash_account' => $this->whenLoaded('cashAccount', fn () => [
                'id' => $this->cashAccount->id,
                'code' => $this->cashAccount->code,
                'name' => $this->cashAccount->name,
            ]),
            'reference' => $this->reference,
            'description' => $this->description,
            'notes' => $this->notes,
            'status' => $this->status,
            'journal_entry_id' => $this->journal_entry_id,
            'refund_payment_id' => $this->refund_payment_id,
            'refunded_at' => $this->refunded_at?->format('Y-m-d H:i:s'),
            'applications' => DownPaymentApplicationResource::collection($this->whenLoaded('applications')),
            'applications_count' => $this->whenCounted('applications'),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
