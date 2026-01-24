<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\Bom
 */
class BomResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   bom_number: string,
     *   name: string,
     *   description: string|null,
     *   product_id: int,
     *   product?: array{id: int, name: string, sku: string},
     *   output_quantity: float,
     *   output_unit: string,
     *   total_material_cost: int,
     *   total_labor_cost: int,
     *   total_overhead_cost: int,
     *   total_cost: int,
     *   unit_cost: int,
     *   status: string,
     *   version: int,
     *   parent_bom_id: int|null,
     *   variant_group_id: int|null,
     *   variant_name: string|null,
     *   variant_label: string|null,
     *   is_primary_variant: bool,
     *   variant_sort_order: int,
     *   notes: string|null,
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   items_count?: int,
     *   cost_breakdown?: array<string, mixed>,
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   approved_by: int|null,
     *   approved_at: string|null,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bom_number' => $this->bom_number,
            'name' => $this->name,
            'description' => $this->description,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'output_quantity' => (float) $this->output_quantity,
            'output_unit' => $this->output_unit,
            'total_material_cost' => $this->total_material_cost,
            'total_labor_cost' => $this->total_labor_cost,
            'total_overhead_cost' => $this->total_overhead_cost,
            'total_cost' => $this->total_cost,
            'unit_cost' => $this->unit_cost,
            'status' => $this->status,
            'version' => $this->version,
            'parent_bom_id' => $this->parent_bom_id,
            'variant_group_id' => $this->variant_group_id,
            'variant_name' => $this->variant_name,
            'variant_label' => $this->variant_label,
            'is_primary_variant' => $this->is_primary_variant,
            'variant_sort_order' => $this->variant_sort_order,
            'notes' => $this->notes,
            'items' => BomItemResource::collection($this->whenLoaded('items')),
            'items_count' => $this->whenCounted('items'),
            'cost_breakdown' => $this->when($this->total_cost > 0, fn () => $this->getCostBreakdown()),
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }
}
