<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderItemResource extends JsonResource
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
            'work_order_id' => $this->work_order_id,
            'bom_item_id' => $this->bom_item_id,
            'parent_item_id' => $this->parent_item_id,
            'type' => $this->type,
            'description' => $this->description,
            'level' => $this->level,
            'sort_order' => $this->sort_order,

            // Quantities
            'quantity_required' => (float) $this->quantity_required,
            'quantity_reserved' => (float) $this->quantity_reserved,
            'quantity_consumed' => (float) $this->quantity_consumed,
            'quantity_scrapped' => (float) $this->quantity_scrapped,
            'quantity_remaining' => $this->getRemainingQuantity(),
            'unit' => $this->unit,

            // Costs
            'unit_cost' => $this->unit_cost,
            'actual_unit_cost' => $this->actual_unit_cost,
            'total_estimated_cost' => $this->total_estimated_cost,
            'total_actual_cost' => $this->total_actual_cost,

            'notes' => $this->notes,

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

            'child_items' => WorkOrderItemResource::collection($this->whenLoaded('childItems')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
