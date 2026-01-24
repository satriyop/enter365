<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\WorkOrderItem
 */
class WorkOrderItemResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   work_order_id: int,
     *   bom_item_id: int|null,
     *   parent_item_id: int|null,
     *   type: string,
     *   description: string,
     *   level: int,
     *   sort_order: int,
     *   quantity_required: float,
     *   quantity_reserved: float,
     *   quantity_consumed: float,
     *   quantity_scrapped: float,
     *   quantity_remaining: float,
     *   unit: string,
     *   unit_cost: int,
     *   actual_unit_cost: int,
     *   total_estimated_cost: int,
     *   total_actual_cost: int,
     *   notes: string|null,
     *   product_id: int|null,
     *   product?: array{id: int, sku: string, name: string, unit: string}|null,
     *   child_items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
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
