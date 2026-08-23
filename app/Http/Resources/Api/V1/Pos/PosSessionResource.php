<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Pos\PosSession
 */
class PosSessionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_number' => $this->session_number,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'warehouse_id' => $this->warehouse_id,
            'warehouse_name' => $this->whenLoaded('warehouse', fn () => $this->warehouse?->name),
            'cash_account_id' => $this->cash_account_id,
            'qris_account_id' => $this->qris_account_id,
            'opening_cash_amount' => $this->opening_cash_amount,
            'expected_cash_amount' => $this->expected_cash_amount,
            'counted_cash_amount' => $this->counted_cash_amount,
            'cash_difference_amount' => $this->cash_difference_amount,
            'opened_by' => $this->opened_by,
            'opened_at' => $this->opened_at instanceof \DateTimeInterface ? $this->opened_at->toIso8601String() : $this->opened_at,
            'closed_at' => $this->closed_at instanceof \DateTimeInterface ? $this->closed_at->toIso8601String() : $this->closed_at,
            'holds' => $this->whenLoaded('holds', fn () => PosSessionHoldResource::collection($this->holds)),
            'sales' => $this->whenLoaded('sales', fn () => PosSaleResource::collection($this->sales)),
        ];
    }
}
