<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Manufacturing\BomTemplateItem;
use App\Support\Features;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BomTemplateItem
 */
class BomTemplateItemResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   template_id: int,
     *   type: string,
     *   type_label: string,
     *   component_standard_id: int|null,
     *   component_standard?: array{id: int, code: string, name: string, category: string},
     *   product_id: int|null,
     *   product?: array{id: int, name: string, sku: string, purchase_price: int},
     *   description: string,
     *   default_quantity: float,
     *   unit: string,
     *   is_required: bool,
     *   is_quantity_variable: bool,
     *   sort_order: int,
     *   notes: string|null,
     *   has_component_standard: bool,
     *   has_product: bool,
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'type' => $this->type,
            'type_label' => BomTemplateItem::getTypes()[$this->type] ?? $this->type,
            'component_standard_id' => $this->when(
                Features::enabled('electrical_panel'),
                $this->panelMeta?->component_standard_id
            ),
            'component_standard' => $this->when(
                Features::enabled('electrical_panel') && ($this->relationLoaded('panelMeta') || $this->relationLoaded('componentStandard')),
                function () {
                    $standard = $this->panelMeta?->componentStandard ?? $this->componentStandard;

                    return $standard ? [
                        'id' => $standard->id,
                        'code' => $standard->code,
                        'name' => $standard->name,
                        'category' => $standard->category,
                    ] : null;
                }
            ),
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
            'has_component_standard' => Features::enabled('electrical_panel')
                && $this->panelMeta?->component_standard_id !== null,
            'has_product' => $this->product_id !== null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
