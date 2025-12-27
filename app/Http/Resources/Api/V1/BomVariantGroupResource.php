<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\BomVariantGroup
 */
class BomVariantGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'name' => $this->name,
            'description' => $this->description,
            'comparison_notes' => $this->comparison_notes,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'boms' => $this->whenLoaded('boms', fn () => $this->boms->map(fn ($bom) => [
                'id' => $bom->id,
                'bom_number' => $bom->bom_number,
                'name' => $bom->name,
                'variant_name' => $bom->variant_name,
                'variant_label' => $bom->variant_label,
                'is_primary_variant' => $bom->is_primary_variant,
                'variant_sort_order' => $bom->variant_sort_order,
                'status' => $bom->status,
                'total_cost' => $bom->total_cost,
                'unit_cost' => $bom->unit_cost,
                'cost_breakdown' => $bom->getCostBreakdown(),
            ])),
            'variants_count' => $this->whenLoaded('boms', fn () => $this->boms->count()),
            'cost_summary' => $this->whenLoaded('boms', function () {
                if ($this->boms->isEmpty()) {
                    return null;
                }
                $costs = $this->boms->pluck('total_cost');

                return [
                    'min' => $costs->min(),
                    'max' => $costs->max(),
                    'difference' => $costs->max() - $costs->min(),
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
