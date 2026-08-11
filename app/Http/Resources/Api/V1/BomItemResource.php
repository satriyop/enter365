<?php

namespace App\Http\Resources\Api\V1;

use App\Support\AddonExtensions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Manufacturing\BomItem
 */
class BomItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge([
            'id' => $this->id,
            'bom_id' => $this->bom_id,
            'type' => $this->type,
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'waste_percentage' => (float) $this->waste_percentage,
            'effective_quantity' => $this->getEffectiveQuantity(),
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ], AddonExtensions::resourceAttributes('bom_item', $this));
    }
}
