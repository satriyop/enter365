<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PlnTariff extends Model
{
    // Customer type constants
    public const TYPE_RESIDENTIAL = 'residential';

    public const TYPE_INDUSTRIAL = 'industrial';

    public const TYPE_BUSINESS = 'business';

    public const TYPE_SOCIAL = 'social';

    public const TYPE_GOVERNMENT = 'government';

    protected $fillable = [
        'category_code',
        'category_name',
        'customer_type',
        'power_va_min',
        'power_va_max',
        'rate_per_kwh',
        'capacity_charge',
        'minimum_charge',
        'peak_rate_per_kwh',
        'off_peak_rate_per_kwh',
        'peak_hours',
        'is_active',
        'effective_from',
        'effective_until',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'power_va_min' => 'integer',
            'power_va_max' => 'integer',
            'rate_per_kwh' => 'integer',
            'capacity_charge' => 'integer',
            'minimum_charge' => 'integer',
            'peak_rate_per_kwh' => 'integer',
            'off_peak_rate_per_kwh' => 'integer',
            'is_active' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    // ========================================
    // Scopes
    // ========================================

    /**
     * Scope for active tariffs.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for currently effective tariffs.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeEffective(Builder $query): Builder
    {
        $today = now()->toDateString();

        return $query
            ->where('is_active', true)
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_from')
                    ->orWhere('effective_from', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            });
    }

    /**
     * Scope by customer type.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeOfType(Builder $query, string $customerType): Builder
    {
        return $query->where('customer_type', $customerType);
    }

    /**
     * Scope for residential tariffs.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeResidential(Builder $query): Builder
    {
        return $query->where('customer_type', self::TYPE_RESIDENTIAL);
    }

    /**
     * Scope for industrial tariffs.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeIndustrial(Builder $query): Builder
    {
        return $query->where('customer_type', self::TYPE_INDUSTRIAL);
    }

    /**
     * Scope for business tariffs.
     *
     * @param  Builder<PlnTariff>  $query
     * @return Builder<PlnTariff>
     */
    public function scopeBusiness(Builder $query): Builder
    {
        return $query->where('customer_type', self::TYPE_BUSINESS);
    }

    // ========================================
    // Lookup Methods
    // ========================================

    /**
     * Find tariff by category code.
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()
            ->where('category_code', $code)
            ->active()
            ->first();
    }

    /**
     * Find tariff by power capacity (VA).
     */
    public static function findByPower(int $powerVa, string $customerType = self::TYPE_RESIDENTIAL): ?self
    {
        return static::query()
            ->where('customer_type', $customerType)
            ->where('power_va_min', '<=', $powerVa)
            ->where(function ($q) use ($powerVa) {
                $q->whereNull('power_va_max')
                    ->orWhere('power_va_max', '>=', $powerVa);
            })
            ->active()
            ->first();
    }

    /**
     * Get all active tariffs grouped by customer type.
     *
     * @return \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, PlnTariff>>
     */
    public static function getGroupedByType(): \Illuminate\Support\Collection
    {
        return static::query()
            ->active()
            ->orderBy('customer_type')
            ->orderBy('power_va_min')
            ->get()
            ->groupBy('customer_type');
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Check if this is a Time-of-Use (TOU) tariff.
     */
    public function isTouTariff(): bool
    {
        return $this->peak_rate_per_kwh !== null
            && $this->off_peak_rate_per_kwh !== null;
    }

    /**
     * Get power range display string.
     */
    public function getPowerRangeLabel(): string
    {
        if ($this->power_va_max === null) {
            return '>= '.number_format($this->power_va_min).' VA';
        }

        return number_format($this->power_va_min).' - '.number_format($this->power_va_max).' VA';
    }

    /**
     * Get customer type label in Indonesian.
     */
    public function getCustomerTypeLabel(): string
    {
        return match ($this->customer_type) {
            self::TYPE_RESIDENTIAL => 'Rumah Tangga',
            self::TYPE_INDUSTRIAL => 'Industri',
            self::TYPE_BUSINESS => 'Bisnis',
            self::TYPE_SOCIAL => 'Sosial',
            self::TYPE_GOVERNMENT => 'Pemerintah',
            default => $this->customer_type,
        };
    }

    /**
     * Calculate monthly electricity cost.
     */
    public function calculateMonthlyCost(float $consumptionKwh): int
    {
        $energyCost = (int) round($consumptionKwh * $this->rate_per_kwh);

        // Add capacity charge if applicable
        if ($this->capacity_charge !== null) {
            $energyCost += $this->capacity_charge;
        }

        // Apply minimum charge if applicable
        if ($this->minimum_charge !== null && $energyCost < $this->minimum_charge) {
            return $this->minimum_charge;
        }

        return $energyCost;
    }

    /**
     * Calculate annual electricity cost.
     */
    public function calculateAnnualCost(float $monthlyConsumptionKwh): int
    {
        return $this->calculateMonthlyCost($monthlyConsumptionKwh) * 12;
    }

    /**
     * Get formatted rate per kWh for display.
     */
    public function getFormattedRate(): string
    {
        return 'Rp '.number_format($this->rate_per_kwh).'/kWh';
    }
}
