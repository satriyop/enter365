<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Manufacturing\BomTemplateItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BomTemplateItem
 */
class BomTemplateItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'type' => $this->type,
            'type_label' => BomTemplateItem::getTypes()[$this->type] ?? $this->type,
            'component_standard_id' => $this->component_standard_id,
            'component_standard' => $this->whenLoaded('componentStandard', fn () => [
                'id' => $this->componentStandard->id,
                'code' => $this->componentStandard->code,
                'name' => $this->componentStandard->name,
                'category' => $this->componentStandard->category,
            ]),
            'product_id' => $this->product_id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'purchase_price' => $this->product->purchase_price,
            ]),
            'description' => $this->description,
            'default_quantity' => $this->default_quantity,
            'unit' => $this->unit,
            'is_required' => $this->is_required,
            'is_quantity_variable' => $this->is_quantity_variable,
            'sort_order' => $this->sort_order,
            'notes' => $this->notes,
            'has_component_standard' => $this->component_standard_id !== null,
            'has_product' => $this->product_id !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
