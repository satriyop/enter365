<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ElectricalPanel\SpecValidationRuleSet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SpecValidationRuleSet
 */
class SpecValidationRuleSetResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   description: string|null,
     *   is_default: bool,
     *   is_active: bool,
     *   rules?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
     *   rules_count?: int,
     *   boms_count?: int,
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
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,
            'rules' => SpecValidationRuleResource::collection(
                $this->whenLoaded('rules')
            ),
            'rules_count' => $this->when(
                $this->relationLoaded('rules'),
                fn () => $this->rules->count()
            ),
            'boms_count' => $this->when(
                $this->relationLoaded('boms'),
                fn () => $this->boms->count()
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
