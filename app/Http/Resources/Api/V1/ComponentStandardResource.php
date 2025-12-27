<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\ComponentStandard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ComponentStandard
 */
class ComponentStandardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'category' => $this->category,
            'category_label' => ComponentStandard::getCategories()[$this->category] ?? $this->category,
            'subcategory' => $this->subcategory,
            'specifications' => $this->specifications,
            'standard' => $this->standard,
            'description' => $this->description,
            'unit' => $this->unit,
            'is_active' => $this->is_active,
            'brand_mappings' => ComponentBrandMappingResource::collection(
                $this->whenLoaded('brandMappings')
            ),
            'available_brands' => $this->when(
                $this->relationLoaded('brandMappings'),
                fn () => $this->brandMappings->pluck('brand')->unique()->values()
            ),
            'brand_count' => $this->when(
                $this->relationLoaded('brandMappings'),
                fn () => $this->brandMappings->pluck('brand')->unique()->count()
            ),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
