<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialRequisitionResource extends JsonResource
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
            'requisition_number' => $this->requisition_number,
            'status' => $this->status,
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
