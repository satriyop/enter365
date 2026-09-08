<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\TaxTag
 */
class TaxTagResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, applicability: string, is_active: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'applicability' => $this->applicability,
            'is_active' => $this->is_active,
        ];
    }
}
