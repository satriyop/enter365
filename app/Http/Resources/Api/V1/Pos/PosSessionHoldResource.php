<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Pos\PosSessionHold
 */
class PosSessionHoldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'pos_session_id' => $this->pos_session_id,
            'lines' => $this->lines,
            'created_at' => $this->created_at instanceof \DateTimeInterface ? $this->created_at->toIso8601String() : $this->created_at,
        ];
    }
}
