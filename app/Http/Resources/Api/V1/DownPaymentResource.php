<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\DownPayment
 */
/**
 * @mixin \App\Models\Sales\DownPayment
 */
class DownPaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *   id: int,
     *   dp_number: string,
     *   type: string,
     *   type_label: string,
     *   contact_id: int,
     *   contact?: array{id: int, name: string, email: string|null},
     *   dp_date: string,
     *   amount: int,
     *   applied_amount: int,
     *   remaining_amount: int,
     *   payment_method: string,
     *   cash_account_id: int,
     *   cash_account?: array{id: int, code: string, name: string},
     *   reference: string|null,
     *   description: string|null,
     *   notes: string|null,
     *   status: StatusResource,
     *   journal_entry_id: int|null,
     *   refund_payment_id: int|null,
     *   refunded_at: string|null,
     *   applications?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   applications_count?: int,
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   created_at: string,
     *   updated_at: string
     * }
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
            'dp_date' => $this->dp_date instanceof \Carbon\Carbon ? $this->dp_date->toDateString() : $this->dp_date,
            'amount' => $this->amount,
            'applied_amount' => $this->applied_amount,
            'remaining_amount' => $this->remaining_amount,
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
            'status' => new StatusResource($this->status),
            'journal_entry_id' => $this->journal_entry_id,
            'refund_payment_id' => $this->refund_payment_id,
            'refunded_at' => $this->refunded_at instanceof \Carbon\Carbon ? $this->refunded_at->toIso8601String() : null,
            'applications' => DownPaymentApplicationResource::collection($this->whenLoaded('applications')),
            'applications_count' => $this->whenCounted('applications'),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at instanceof \Carbon\Carbon ? $this->created_at->toIso8601String() : $this->created_at,
            'updated_at' => $this->updated_at instanceof \Carbon\Carbon ? $this->updated_at->toIso8601String() : $this->updated_at,
        ];
    }
}
