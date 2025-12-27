<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockOpnameResource extends JsonResource
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
            'opname_number' => $this->opname_number,
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'code' => $this->warehouse->code,
                'name' => $this->warehouse->name,
            ]),
            'opname_date' => $this->opname_date->toDateString(),
            'status' => $this->status,
            'name' => $this->name,
            'notes' => $this->notes,

            // Workflow tracking
            'counted_by' => $this->counted_by,
            'counted_by_user' => $this->whenLoaded('countedByUser', fn () => [
                'id' => $this->countedByUser->id,
                'name' => $this->countedByUser->name,
            ]),
            'counting_started_at' => $this->counting_started_at?->toIso8601String(),

            'reviewed_by' => $this->reviewed_by,
            'reviewed_by_user' => $this->whenLoaded('reviewedByUser', fn () => [
                'id' => $this->reviewedByUser->id,
                'name' => $this->reviewedByUser->name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),

            'approved_by' => $this->approved_by,
            'approved_by_user' => $this->whenLoaded('approvedByUser', fn () => [
                'id' => $this->approvedByUser->id,
                'name' => $this->approvedByUser->name,
            ]),
            'approved_at' => $this->approved_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),

            // Summary
            'total_items' => $this->total_items,
            'total_counted' => $this->total_counted,
            'counting_progress' => $this->getCountingProgress(),
            'total_variance_qty' => $this->total_variance_qty,
            'total_variance_value' => $this->total_variance_value,

            // Items
            'items' => StockOpnameItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->when(! $this->relationLoaded('items'), $this->items_count ?? $this->total_items),

            // Workflow permissions
            'can_edit' => $this->canEdit(),
            'can_delete' => $this->canDelete(),
            'can_start_counting' => $this->canStartCounting(),
            'can_submit_for_review' => $this->canSubmitForReview(),
            'can_approve' => $this->canApprove(),
            'can_reject' => $this->canReject(),
            'can_cancel' => $this->canCancel(),

            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
