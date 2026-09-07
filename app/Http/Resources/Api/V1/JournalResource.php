<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Journal
 */
class JournalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'sequence_prefix' => $this->sequence_prefix,
            'default_account_id' => $this->default_account_id,
            'currency' => $this->currency,
            'is_active' => $this->is_active,
            'default_account' => new AccountResource($this->whenLoaded('defaultAccount')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
