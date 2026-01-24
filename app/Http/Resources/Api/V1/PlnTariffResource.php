<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Solar\PlnTariff
 */
class PlnTariffResource extends JsonResource
{
    /**
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   category_code: string,
     *   category_name: string,
     *   customer_type: string,
     *   customer_type_label: string,
     *   power_va_min: int,
     *   power_va_max: int|null,
     *   power_range_label: string,
     *   rate_per_kwh: int,
     *   formatted_rate: string,
     *   capacity_charge: int|null,
     *   minimum_charge: int|null,
     *   is_tou_tariff: bool,
     *   peak_rate_per_kwh: int|null,
     *   off_peak_rate_per_kwh: int|null,
     *   peak_hours: string|null,
     *   is_active: bool,
     *   effective_from: string|null,
     *   effective_until: string|null,
     *   notes: string|null
     * }
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
            'effective_from' => $this->effective_from instanceof \Carbon\Carbon ? $this->effective_from->toDateString() : null,
            'effective_until' => $this->effective_until instanceof \Carbon\Carbon ? $this->effective_until->toDateString() : null,
            'notes' => $this->notes,
        ];
    }
}
