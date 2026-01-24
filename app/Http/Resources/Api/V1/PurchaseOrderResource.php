<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\PurchaseOrder
 */
class PurchaseOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   po_number: string,
     *   revision: int,
     *   full_number: string,
     *   contact_id: int,
     *   contact?: array{id: int, name: string},
     *   po_date: string,
     *   expected_date: string|null,
     *   reference: string|null,
     *   subject: string|null,
     *   status: string,
     *   status_label: string,
     *   currency: string,
     *   exchange_rate: float,
     *   subtotal: int,
     *   discount_type: string,
     *   discount_value: float,
     *   discount_amount: int,
     *   tax_rate: float,
     *   tax_amount: int,
     *   total: int,
     *   base_currency_total: int,
     *   notes: string|null,
     *   terms_conditions: string|null,
     *   shipping_address: string|null,
     *   receiving_progress: float,
     *   is_fully_received: bool,
     *   has_received_items: bool,
     *   first_received_at: string|null,
     *   fully_received_at: string|null,
     *   submitted_at: string|null,
     *   submitted_by: int|null,
     *   approved_at: string|null,
     *   approved_by: int|null,
     *   rejected_at: string|null,
     *   rejected_by: int|null,
     *   rejection_reason: string|null,
     *   cancelled_at: string|null,
     *   cancelled_by: int|null,
     *   cancellation_reason: string|null,
     *   converted_to_bill_id: int|null,
     *   converted_at: string|null,
     *   original_po_id: int|null,
     *   can_edit: bool,
     *   can_submit: bool,
     *   can_approve: bool,
     *   can_reject: bool,
     *   can_cancel: bool,
     *   can_receive: bool,
     *   can_convert: bool,
     *   can_revise: bool,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   revisions?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   converted_bill?: array{id: int, bill_number: string},
     *   created_by: int|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'po_number' => $this->po_number,
            'revision' => $this->revision,
            'full_number' => $this->getFullNumber(),

            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            'po_date' => $this->po_date->toDateString(),
            'expected_date' => $this->expected_date?->toDateString(),

            'reference' => $this->reference,
            'subject' => $this->subject,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_terminal' => $this->status->isTerminal(),
                'is_editable' => $this->status->isEditable(),
            ],
            'status_label' => $this->status->label(),
            'status_label' => $this->getStatusLabel(),

            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total_amount,
            'base_currency_total' => $this->base_currency_total,

            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,
            'shipping_address' => $this->shipping_address,

            // Receiving info
            'receiving_progress' => $this->getReceivingProgress(),
            'is_fully_received' => $this->isFullyReceived(),
            'has_received_items' => $this->hasReceivedItems(),
            'first_received_at' => $this->first_received_at?->toIso8601String(),
            'fully_received_at' => $this->fully_received_at?->toIso8601String(),

            // Workflow info
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by' => $this->submitted_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejection_reason' => $this->rejection_reason,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by' => $this->cancelled_by,
            'cancellation_reason' => $this->cancellation_reason,

            'converted_to_bill_id' => $this->converted_to_bill_id,
            'converted_at' => $this->converted_at?->toIso8601String(),

            'original_po_id' => $this->original_po_id,

            // Permissions
            'can_edit' => $this->isEditable(),
            'can_submit' => $this->canSubmit(),
            'can_approve' => $this->canApprove(),
            'can_reject' => $this->canReject(),
            'can_cancel' => $this->canCancel(),
            'can_receive' => $this->canReceive(),
            'can_convert' => $this->canConvert(),
            'can_revise' => $this->canRevise(),

            // Relations
            'items' => PurchaseOrderItemResource::collection($this->whenLoaded('items')),
            'revisions' => PurchaseOrderResource::collection($this->whenLoaded('revisions')),
            'converted_bill' => new BillResource($this->whenLoaded('convertedBill')),

            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
