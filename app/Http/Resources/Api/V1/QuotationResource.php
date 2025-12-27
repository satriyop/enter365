<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Quotation
 */
class QuotationResource extends JsonResource
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
            'quotation_number' => $this->quotation_number,
            'revision' => $this->revision,
            'full_number' => $this->getFullNumber(),

            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            'quotation_date' => $this->quotation_date->toDateString(),
            'valid_until' => $this->valid_until->toDateString(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'is_expired' => $this->isExpired(),

            'reference' => $this->reference,
            'subject' => $this->subject,
            'quotation_type' => $this->quotation_type,
            'is_multi_option' => $this->isMultiOption(),
            'variant_group_id' => $this->variant_group_id,
            'variant_group' => new BomVariantGroupResource($this->whenLoaded('variantGroup')),
            'selected_variant_id' => $this->selected_variant_id,
            'selected_variant' => new BomResource($this->whenLoaded('selectedVariant')),
            'has_selected_variant' => $this->hasSelectedVariant(),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),

            'currency' => $this->currency,
            'exchange_rate' => (float) $this->exchange_rate,

            'subtotal' => $this->subtotal,
            'discount_type' => $this->discount_type,
            'discount_value' => (float) $this->discount_value,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => (float) $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total' => $this->total,
            'base_currency_total' => $this->base_currency_total,

            'notes' => $this->notes,
            'terms_conditions' => $this->terms_conditions,

            // Workflow info
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'submitted_by' => $this->submitted_by,
            'approved_at' => $this->approved_at?->toIso8601String(),
            'approved_by' => $this->approved_by,
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejected_by' => $this->rejected_by,
            'rejection_reason' => $this->rejection_reason,

            // Follow-up tracking
            'next_follow_up_at' => $this->next_follow_up_at?->toIso8601String(),
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'assigned_to' => $this->assigned_to,
            'assigned_user' => new UserResource($this->whenLoaded('assignedTo')),
            'follow_up_count' => $this->follow_up_count,
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'needs_follow_up' => $this->needsFollowUp(),
            'days_since_last_contact' => $this->getDaysSinceLastContact(),

            // Win/Loss outcome
            'outcome' => $this->outcome,
            'outcome_label' => $this->getOutcomeLabel(),
            'won_reason' => $this->won_reason,
            'lost_reason' => $this->lost_reason,
            'lost_to_competitor' => $this->lost_to_competitor,
            'outcome_notes' => $this->outcome_notes,
            'outcome_at' => $this->outcome_at?->toIso8601String(),

            'converted_to_invoice_id' => $this->converted_to_invoice_id,
            'converted_at' => $this->converted_at?->toIso8601String(),

            'original_quotation_id' => $this->original_quotation_id,

            // Permissions
            'can_edit' => $this->isEditable(),
            'can_submit' => $this->canSubmit(),
            'can_approve' => $this->canApprove(),
            'can_reject' => $this->canReject(),
            'can_convert' => $this->canConvert(),
            'can_revise' => $this->canRevise(),

            // Relations
            'items' => QuotationItemResource::collection($this->whenLoaded('items')),
            'revisions' => QuotationResource::collection($this->whenLoaded('revisions')),
            'converted_invoice' => new InvoiceResource($this->whenLoaded('convertedInvoice')),
            'activities' => QuotationActivityResource::collection($this->whenLoaded('activities')),
            'variant_options' => QuotationVariantOptionResource::collection($this->whenLoaded('variantOptions')),
            'variant_comparison' => $this->when($this->isMultiOption(), fn () => $this->getVariantComparison()),

            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
