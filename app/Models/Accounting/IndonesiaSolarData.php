<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class IndonesiaSolarData extends Model
{
    protected $table = 'indonesia_solar_data';

    protected $fillable = [
        'province',
        'city',
        'latitude',
        'longitude',
        'peak_sun_hours',
        'solar_irradiance_kwh_m2_day',
        'optimal_tilt_angle',
        'ghi_annual',
        'dni_annual',
        'dhi_annual',
        'temperature_avg',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'peak_sun_hours' => 'decimal:2',
            'solar_irradiance_kwh_m2_day' => 'decimal:3',
            'optimal_tilt_angle' => 'decimal:2',
            'ghi_annual' => 'decimal:2',
            'dni_annual' => 'decimal:2',
            'dhi_annual' => 'decimal:2',
            'temperature_avg' => 'decimal:2',
        ];
    }

    // ========================================
    // Scopes
    // ========================================

    /**
     * Scope by province.
     *
     * @param  Builder<IndonesiaSolarData>  $query
     * @return Builder<IndonesiaSolarData>
     */
    public function scopeInProvince(Builder $query, string $province): Builder
    {
        return $query->where('province', $province);
    }

    /**
     * Scope by city.
     *
     * @param  Builder<IndonesiaSolarData>  $query
     * @return Builder<IndonesiaSolarData>
     */
    public function scopeInCity(Builder $query, string $city): Builder
    {
        return $query->where('city', $city);
    }

    // ========================================
    // Lookup Methods
    // ========================================

    /**
     * Find solar data by province and city.
     */
    public static function findByLocation(string $province, string $city): ?self
    {
        return static::query()
            ->where('province', $province)
            ->where('city', $city)
            ->first();
    }

    /**
     * Find nearest solar data by coordinates using Haversine formula.
     */
    public static function findNearest(float $latitude, float $longitude, float $maxDistanceKm = 100): ?self
    {
        // Use subquery for SQLite compatibility (HAVING requires GROUP BY in SQLite)
        $distanceFormula = '6371 * acos(
            cos(radians('.$latitude.')) * cos(radians(latitude)) * cos(radians(longitude) - radians('.$longitude.'))
            + sin(radians('.$latitude.')) * sin(radians(latitude))
        )';

        return static::query()
            ->selectRaw('*, ('.$distanceFormula.') AS distance')
            ->whereRaw('('.$distanceFormula.') <= ?', [$maxDistanceKm])
            ->orderBy('distance')
            ->first();
    }

    /**
     * Get all cities in a province.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function getCitiesInProvince(string $province): \Illuminate\Support\Collection
    {
        return static::query()
            ->where('province', $province)
            ->orderBy('city')
            ->pluck('city');
    }

    /**
     * Get all unique provinces.
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    public static function getProvinces(): \Illuminate\Support\Collection
    {
        return static::query()
            ->distinct()
            ->orderBy('province')
            ->pluck('province');
    }

    // ========================================
    // Calculation Helpers
    // ========================================

    /**
     * Calculate estimated annual production for a given capacity.
     */
    public function estimateAnnualProduction(float $capacityKwp, float $performanceRatio = 0.80): float
    {
        // Annual Production = Capacity (kWp) × Peak Sun Hours × 365 × Performance Ratio
        return round($capacityKwp * (float) $this->peak_sun_hours * 365 * $performanceRatio, 2);
    }

    /**
     * Get solar irradiance quality rating.
     */
    public function getIrradianceRating(): string
    {
        $irradiance = (float) $this->solar_irradiance_kwh_m2_day;

        return match (true) {
            $irradiance >= 5.0 => 'Excellent',
            $irradiance >= 4.5 => 'Very Good',
            $irradiance >= 4.0 => 'Good',
            $irradiance >= 3.5 => 'Fair',
            default => 'Low',
        };
    }

    /**
     * Get Indonesian irradiance rating label.
     */
    public function getIrradianceRatingLabel(): string
    {
        $irradiance = (float) $this->solar_irradiance_kwh_m2_day;

        return match (true) {
            $irradiance >= 5.0 => 'Sangat Baik',
            $irradiance >= 4.5 => 'Baik Sekali',
            $irradiance >= 4.0 => 'Baik',
            $irradiance >= 3.5 => 'Cukup',
            default => 'Rendah',
        };
    }
}
