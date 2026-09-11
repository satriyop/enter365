<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Accounting\PaymentTerm */
class PaymentTermResource extends JsonResource
{
    /** @return array{id: int, code: string, name: string, is_active: bool, note: string|null, lines: list<array{type: string, value: int|float, days: int, due_type: string}>} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'note' => $this->note,
            'lines' => $this->lines,
        ];
    }
}
