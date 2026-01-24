<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\BomVariantGroup
 */
class BomVariantGroupResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   product_id: int,
     *   product?: array{id: int, name: string, sku: string},
     *   name: string,
     *   description: string|null,
     *   comparison_notes: string|null,
     *   status: string,
     *   created_by: int|null,
     *   creator?: array{id: int, name: string},
     *   boms?: array<array{id: int, bom_number: string, name: string, variant_name: string|null, variant_label: string|null, is_primary_variant: bool, variant_sort_order: int, status: string, total_cost: int, unit_cost: int, cost_breakdown: array<string, mixed>}>,
     *   active_boms?: array<array{id: int, bom_number: string, name: string, variant_name: string|null, variant_label: string|null, is_primary_variant: bool, total_cost: int}>,
     *   variants_count?: int,
     *   cost_summary?: array{min: int, max: int, difference: int}|null,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
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
            ])->all()),
            'active_boms' => $this->whenLoaded('activeBoms', fn () => $this->activeBoms->map(fn ($bom) => [
                'id' => $bom->id,
                'bom_number' => $bom->bom_number,
                'name' => $bom->name,
                'variant_name' => $bom->variant_name,
                'variant_label' => $bom->variant_label,
                'is_primary_variant' => $bom->is_primary_variant,
                'total_cost' => $bom->total_cost,
            ])->all()),
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
