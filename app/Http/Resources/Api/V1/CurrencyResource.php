<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Accounting\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'symbol' => $this->symbol,
            'decimal_places' => $this->decimal_places,
            'is_base_currency' => $this->is_base_currency,
            'is_active' => $this->is_active,
            'exchange_rates' => $this->whenLoaded('exchangeRates', fn () => $this->exchangeRates->map(fn ($rate) => [
                'id' => $rate->id,
                'from_currency' => $rate->from_currency,
                'to_currency' => $rate->to_currency,
                'rate' => (float) $rate->rate,
                'effective_date' => $rate->effective_date?->toDateString(),
            ])->values()->all()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
