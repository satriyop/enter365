<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Accounting\CheckSetting */
class CheckSettingResource extends JsonResource
{
    /** @return array{id: int, code: string, name: string, is_active: bool, journal_id: int, next_number: int, layout: string, manual_numbering: bool} */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'journal_id' => $this->journal_id,
            'next_number' => $this->next_number,
            'layout' => $this->layout,
            'manual_numbering' => $this->manual_numbering,
        ];
    }
}
