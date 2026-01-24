<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Sales\QuotationActivity
 */
class QuotationActivityResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   quotation_id: int,
     *   user_id: int|null,
     *   user?: array{id: int, name: string},
     *   activity_type: string,
     *   description: string,
     *   created_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var \Carbon\Carbon|null $createdAt */
        $createdAt = $this->created_at;

        return [
            'id' => $this->id,
            'quotation_id' => $this->quotation_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'activity_type' => $this->type,
            'description' => $this->description,
            'outcome' => $this->outcome,
            'duration_minutes' => $this->duration_minutes,
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}