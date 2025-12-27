<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubcontractorWorkOrderResource extends JsonResource
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
            'sc_wo_number' => $this->sc_wo_number,
            'name' => $this->name,
            'description' => $this->description,
            'scope_of_work' => $this->scope_of_work,
            'status' => $this->status,

            // Subcontractor
            'subcontractor_id' => $this->subcontractor_id,
            'subcontractor' => $this->when($this->relationLoaded('subcontractor'), function () {
                return $this->subcontractor ? [
                    'id' => $this->subcontractor->id,
                    'name' => $this->subcontractor->name,
                    'phone' => $this->subcontractor->phone,
                    'email' => $this->subcontractor->email,
                ] : null;
            }),

            // Related work order
            'work_order_id' => $this->work_order_id,
            'work_order' => $this->when($this->relationLoaded('workOrder'), function () {
                return $this->workOrder ? [
                    'id' => $this->workOrder->id,
                    'wo_number' => $this->workOrder->wo_number,
                    'name' => $this->workOrder->name,
                ] : null;
            }),

            // Project
            'project_id' => $this->project_id,
            'project' => $this->when($this->relationLoaded('project'), function () {
                return $this->project ? [
                    'id' => $this->project->id,
                    'project_number' => $this->project->project_number,
                    'name' => $this->project->name,
                ] : null;
            }),

            // Financials
            'agreed_amount' => $this->agreed_amount,
            'actual_amount' => $this->actual_amount,
            'retention_percent' => (float) $this->retention_percent,
            'retention_amount' => $this->retention_amount,
            'amount_invoiced' => $this->amount_invoiced,
            'amount_paid' => $this->amount_paid,
            'amount_due' => $this->amount_due,
            'remaining_invoiceable' => $this->getRemainingInvoiceableAmount(),

            // Schedule
            'scheduled_start_date' => $this->scheduled_start_date?->toDateString(),
            'scheduled_end_date' => $this->scheduled_end_date?->toDateString(),
            'actual_start_date' => $this->actual_start_date?->toDateString(),
            'actual_end_date' => $this->actual_end_date?->toDateString(),
            'completion_percentage' => $this->completion_percentage,

            // Location
            'work_location' => $this->work_location,
            'location_address' => $this->location_address,

            'notes' => $this->notes,

            // Invoices
            'invoices' => SubcontractorInvoiceResource::collection($this->whenLoaded('invoices')),

            // Workflow flags
            'can_be_edited' => $this->canBeEdited(),
            'can_be_assigned' => $this->canBeAssigned(),
            'can_be_started' => $this->canBeStarted(),
            'can_update_progress' => $this->canUpdateProgress(),
            'can_be_completed' => $this->canBeCompleted(),
            'can_be_cancelled' => $this->canBeCancelled(),
            'can_create_invoice' => $this->canCreateInvoice(),
            'is_fully_invoiced' => $this->isFullyInvoiced(),

            // Workflow timestamps
            'assigned_at' => $this->assigned_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
