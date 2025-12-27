<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\IndonesiaSolarData
 */
class IndonesiaSolarDataResource extends JsonResource
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
            'province' => $this->province,
            'city' => $this->city,

            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,

            'peak_sun_hours' => (float) $this->peak_sun_hours,
            'solar_irradiance_kwh_m2_day' => (float) $this->solar_irradiance_kwh_m2_day,
            'optimal_tilt_angle' => (float) $this->optimal_tilt_angle,

            'irradiance_rating' => $this->getIrradianceRating(),
            'irradiance_rating_label' => $this->getIrradianceRatingLabel(),

            // Extended data
            'ghi_annual' => $this->ghi_annual ? (float) $this->ghi_annual : null,
            'dni_annual' => $this->dni_annual ? (float) $this->dni_annual : null,
            'dhi_annual' => $this->dhi_annual ? (float) $this->dhi_annual : null,
            'temperature_avg' => $this->temperature_avg ? (float) $this->temperature_avg : null,
        ];
    }
}
