<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Pos;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Pos\PosSale
 */
class PosSaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_number' => $this->sale_number,
            'pos_session_id' => $this->pos_session_id,
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'subtotal_amount' => $this->subtotal_amount,
            'service_amount' => $this->service_amount,
            'tax_amount' => $this->tax_amount,
            'payable_amount' => $this->payable_amount,
            'cash_received_amount' => $this->cash_received_amount,
            'change_amount' => $this->change_amount,
            'sold_at' => $this->sold_at instanceof \DateTimeInterface ? $this->sold_at->toIso8601String() : $this->sold_at,
            'void_reason' => $this->void_reason,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'payable_amount' => $item->payable_amount,
            ])),
            'tenders' => $this->whenLoaded('tenders', fn () => $this->tenders->map(fn ($tender) => [
                'type' => $tender->type instanceof \BackedEnum ? $tender->type->value : $tender->type,
                'amount' => $tender->amount,
            ])),
        ];
    }
}
