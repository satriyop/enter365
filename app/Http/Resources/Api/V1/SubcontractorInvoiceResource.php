<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubcontractorInvoiceResource extends JsonResource
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
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,

            // Work Order
            'subcontractor_work_order_id' => $this->subcontractor_work_order_id,
            'subcontractor_work_order' => $this->when(
                $this->relationLoaded('subcontractorWorkOrder'),
                function () {
                    return $this->subcontractorWorkOrder ? [
                        'id' => $this->subcontractorWorkOrder->id,
                        'sc_wo_number' => $this->subcontractorWorkOrder->sc_wo_number,
                        'name' => $this->subcontractorWorkOrder->name,
                    ] : null;
                }
            ),

            // Subcontractor
            'subcontractor_id' => $this->subcontractor_id,
            'subcontractor' => $this->when($this->relationLoaded('subcontractor'), function () {
                return $this->subcontractor ? [
                    'id' => $this->subcontractor->id,
                    'name' => $this->subcontractor->name,
                ] : null;
            }),

            // Dates
            'invoice_date' => $this->invoice_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),

            // Amounts
            'gross_amount' => $this->gross_amount,
            'retention_held' => $this->retention_held,
            'other_deductions' => $this->other_deductions,
            'net_amount' => $this->net_amount,

            'description' => $this->description,
            'notes' => $this->notes,

            // Bill link
            'bill_id' => $this->bill_id,
            'bill' => $this->when($this->relationLoaded('bill'), function () {
                return $this->bill ? [
                    'id' => $this->bill->id,
                    'bill_number' => $this->bill->bill_number,
                ] : null;
            }),
            'is_converted_to_bill' => $this->isConvertedToBill(),
            'converted_to_bill_at' => $this->converted_to_bill_at?->toIso8601String(),

            // Workflow flags
            'can_be_approved' => $this->canBeApproved(),
            'can_be_rejected' => $this->canBeRejected(),
            'can_be_converted_to_bill' => $this->canBeConvertedToBill(),

            // Workflow timestamps
            'approved_at' => $this->approved_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
