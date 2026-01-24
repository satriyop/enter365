<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Generic Label-Value API Resource.
 * 
 * Used for standardizing enums like Priority, Type, etc.
 * 
 * @property mixed $resource
 */
class LabelValueResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   value: string,
     *   label: string
     * }
     */
    public function toArray(Request $request): array
    {
        // Handle BackedEnums with label() method
        if ($this->resource instanceof \BackedEnum && method_exists($this->resource, 'label')) {
            return [
                'value' => (string) $this->resource->value,
                'label' => $this->resource->label(),
            ];
        }

        // Fallback for raw strings or other objects
        return [
            'value' => (string) ($this->resource->value ?? $this->resource),
            'label' => method_exists($this->resource, 'label') ? $this->resource->label() : (string) ($this->resource->label ?? $this->resource),
        ];
    }
}