<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Manufacturing\BomTemplate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BomTemplate
 */
class BomTemplateResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   description: string|null,
     *   category: string,
     *   category_label: string,
     *   thumbnail_path: string|null,
     *   thumbnail_url: string|null,
     *   is_active: bool,
     *   usage_count: int,
     *   default_rule_set?: array{id: int, name: string, code: string},
     *   items?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   items_count?: int,
     *   summary?: array{material_count: int, labor_count: int, overhead_count: int},
     *   creator?: array{id: int, name: string},
     *   created_at: string|null,
     *   updated_at: string|null
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => BomTemplate::getCategories()[$this->category] ?? $this->category,
            'thumbnail_path' => $this->thumbnail_path,
            'thumbnail_url' => $this->thumbnail_path ? asset('storage/'.$this->thumbnail_path) : null,
            'is_active' => $this->is_active,
            'usage_count' => $this->usage_count,
            'default_rule_set' => $this->whenLoaded('defaultRuleSet', fn () => [
                'id' => $this->defaultRuleSet->id,
                'name' => $this->defaultRuleSet->name,
                'code' => $this->defaultRuleSet->code,
            ]),
            'items' => BomTemplateItemResource::collection(
                $this->whenLoaded('items')
            ),
            'items_count' => $this->when(
                $this->relationLoaded('items'),
                fn () => $this->items->count()
            ),
            'summary' => $this->when(
                $this->relationLoaded('items'),
                fn () => [
                    'material_count' => $this->items->where('type', 'material')->count(),
                    'labor_count' => $this->items->where('type', 'labor')->count(),
                    'overhead_count' => $this->items->where('type', 'overhead')->count(),
                ]
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
