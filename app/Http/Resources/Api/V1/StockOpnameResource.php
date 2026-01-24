<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Inventory\StockOpname
 */
class StockOpnameResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   opname_number: string,
     *   warehouse_id: int,
     *   warehouse?: array{id: int, code: string, name: string},
     *   opname_date: string,
     *   status: StatusResource,
     *   name: string|null,
     *   notes: string|null,
     *   counted_by: int|null,
     *   counted_by_user?: array{id: int, name: string},
     *   counting_started_at: string|null,
     *   reviewed_by: int|null,
     *   reviewed_by_user?: array{id: int, name: string},
     *   reviewed_at: string|null,
     *   approved_by: int|null,
     *   approved_by_user?: array{id: int, name: string},
     *   approved_at: string|null,
     *   completed_at: string|null,
     *   cancelled_at: string|null,
     *   total_items: int,
     *   total_counted: int,
     *   counting_progress: float,
     *   total_variance_qty: int,
     *   total_variance_value: int,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   items_count?: int,
     *   can_edit: bool,
     *   can_delete: bool,
     *   can_start_counting: bool,
     *   can_submit_for_review: bool,
     *   can_approve: bool,
     *   can_reject: bool,
     *   can_cancel: bool,
     *   created_by: int|null,
     *   created_at: string,
     *   updated_at: string
     * }
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
            'status' => new StatusResource($this->status),
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
