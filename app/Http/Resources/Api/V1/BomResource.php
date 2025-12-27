<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Bom
 */
class BomResource extends JsonResource
{
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
