<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Tax\TaxRecord
 */
class TaxRecordResource extends JsonResource
{
    /**
     * @return array{id: int, code: string, name: string, rate: float, applicability: string, is_active: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'rate' => (float) $this->rate,
            'applicability' => $this->applicability,
            'is_active' => $this->is_active,
        ];
    }
}
