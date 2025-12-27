<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\PlnTariff
 */
class PlnTariffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_code' => $this->category_code,
            'category_name' => $this->category_name,
            'customer_type' => $this->customer_type,
            'customer_type_label' => $this->getCustomerTypeLabel(),

            'power_va_min' => $this->power_va_min,
            'power_va_max' => $this->power_va_max,
            'power_range_label' => $this->getPowerRangeLabel(),

            'rate_per_kwh' => $this->rate_per_kwh,
            'formatted_rate' => $this->getFormattedRate(),
            'capacity_charge' => $this->capacity_charge,
            'minimum_charge' => $this->minimum_charge,

            // Time-of-Use rates
            'is_tou_tariff' => $this->isTouTariff(),
            'peak_rate_per_kwh' => $this->peak_rate_per_kwh,
            'off_peak_rate_per_kwh' => $this->off_peak_rate_per_kwh,
            'peak_hours' => $this->peak_hours,

            'is_active' => $this->is_active,
            'effective_from' => $this->effective_from?->toDateString(),
            'effective_until' => $this->effective_until?->toDateString(),
            'notes' => $this->notes,
        ];
    }
}
