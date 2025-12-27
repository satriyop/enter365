<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaterialRequisitionItemResource extends JsonResource
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
            'material_requisition_id' => $this->material_requisition_id,
            'work_order_item_id' => $this->work_order_item_id,
            'quantity_requested' => (float) $this->quantity_requested,
            'quantity_approved' => (float) $this->quantity_approved,
            'quantity_issued' => (float) $this->quantity_issued,
            'quantity_pending' => (float) $this->quantity_pending,
            'unit' => $this->unit,
            'warehouse_location' => $this->warehouse_location,
            'notes' => $this->notes,
            'is_fully_issued' => $this->isFullyIssued(),

            // Relationships
            'product_id' => $this->product_id,
            'product' => $this->when($this->relationLoaded('product'), function () {
                return $this->product ? [
                    'id' => $this->product->id,
                    'sku' => $this->product->sku,
                    'name' => $this->product->name,
                    'unit' => $this->product->unit,
                ] : null;
            }),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
