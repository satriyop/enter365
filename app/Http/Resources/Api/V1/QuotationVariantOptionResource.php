<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\QuotationVariantOption
 */
class QuotationVariantOptionResource extends JsonResource
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
            'quotation_id' => $this->quotation_id,
            'bom_id' => $this->bom_id,
            'bom' => $this->whenLoaded('bom', fn () => [
                'id' => $this->bom->id,
                'bom_number' => $this->bom->bom_number,
                'name' => $this->bom->name,
                'variant_name' => $this->bom->variant_name,
                'variant_label' => $this->bom->variant_label,
                'total_cost' => $this->bom->total_cost,
                'unit_cost' => $this->bom->unit_cost,
            ]),
            'display_name' => $this->display_name,
            'tagline' => $this->tagline,
            'is_recommended' => $this->is_recommended,
            'selling_price' => $this->selling_price,
            'features' => $this->features,
            'specifications' => $this->specifications,
            'warranty_terms' => $this->warranty_terms,
            'sort_order' => $this->sort_order,

            // Calculated fields (from BOM relationship)
            'profit_margin' => $this->whenLoaded('bom', fn () => $this->getProfitMargin()),
            'profit_amount' => $this->whenLoaded('bom', fn () => $this->getProfitAmount()),
            'cost_breakdown' => $this->whenLoaded('bom', fn () => $this->getCostBreakdown()),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
