<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\MaterialRequisition
 */
class MaterialRequisitionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   requisition_number: string,
     *   status: string,
     *   requested_date: string|null,
     *   required_date: string|null,
     *   total_items: int,
     *   total_quantity: float,
     *   notes: string|null,
     *   work_order_id: int,
     *   work_order?: array{id: int, wo_number: string, name: string}|null,
     *   warehouse_id: int,
     *   warehouse?: array{id: int, name: string}|null,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   approved_at: string|null,
     *   issued_at: string|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requisition_number' => $this->requisition_number,
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
                'is_terminal' => $this->status->isTerminal(),
                'is_editable' => $this->status->isEditable(),
            ],
            'requested_date' => $this->requested_date?->toDateString(),
            'required_date' => $this->required_date?->toDateString(),
            'total_items' => $this->total_items,
            'total_quantity' => (float) $this->total_quantity,
            'notes' => $this->notes,

            // Relationships
            'work_order_id' => $this->work_order_id,
            'work_order' => $this->when($this->relationLoaded('workOrder'), function () {
                return $this->workOrder ? [
                    'id' => $this->workOrder->id,
                    'wo_number' => $this->workOrder->wo_number,
                    'name' => $this->workOrder->name,
                ] : null;
            }),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->when($this->relationLoaded('warehouse'), function () {
                return $this->warehouse ? [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ] : null;
            }),

            'items' => MaterialRequisitionItemResource::collection($this->whenLoaded('items')),

            // Timestamps
            'approved_at' => $this->approved_at?->toIso8601String(),
            'issued_at' => $this->issued_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
