<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\Account
 */
class AccountResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   code: string,
     *   name: string,
     *   type: string,
     *   subtype: string|null,
     *   description: string|null,
     *   parent_id: int|null,
     *   is_active: bool,
     *   is_system: bool,
     *   opening_balance: int,
     *   current_balance?: int,
     *   parent?: array{id: int, name: string, code: string},
     *   children?: \Illuminate\Http\Resources\Json\AnonymousResourceCollection,
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
            'type' => $this->type,
            'subtype' => $this->subtype,
            'description' => $this->description,
            'parent_id' => $this->parent_id,
            'is_active' => $this->is_active,
            'is_system' => $this->is_system,
            'opening_balance' => $this->opening_balance,
            'current_balance' => $this->whenAppended('current_balance'),
            'parent' => new AccountResource($this->whenLoaded('parent')),
            'children' => AccountResource::collection($this->whenLoaded('children')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
