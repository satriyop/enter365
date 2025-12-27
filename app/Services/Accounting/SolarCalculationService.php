<?php

namespace App\Services\Accounting;

use App\Models\Accounting\IndonesiaSolarData;
use App\Models\Accounting\PlnTariff;

class SolarCalculationService
{
    /**
     * Indonesia's grid emission factor (kg CO2/kWh).
     * Source: ESDM/PLN 2023 report.
     */
    public const GRID_EMISSION_FACTOR = 0.709;

    /**
     * Average CO2 absorbed by a tree per year (kg).
     */
    public const CO2_PER_TREE_PER_YEAR = 21.77;

    /**
     * Average CO2 emitted by a car per year (kg).
     */
    public const CO2_PER_CAR_PER_YEAR = 4600;

    /**
     * Default discount rate for NPV calculation.
     */
    public const DEFAULT_DISCOUNT_RATE = 0.08;

    /**
     * Default panel degradation rate per year.
     */
    public const DEFAULT_DEGRADATION_RATE = 0.005;

    /**
     * System lifetime in years.
     */
    public const SYSTEM_LIFETIME_YEARS = 25;

    // ========================================
    // Energy Production Calculations
    // ========================================

    /**
     * Calculate annual energy production.
     *
     * Formula: Capacity (kWp) × Peak Sun Hours × 365 × Performance Ratio
     *
     * @param  float  $capacityKwp  System capacity in kWp
     * @param  float  $peakSunHours  Peak sun hours per day (average)
     * @param  float  $performanceRatio  System performance ratio (0.70-0.85 typical)
     * @return float Annual production in kWh
     */
    public function calculateAnnualProduction(
        float $capacityKwp,
        float $peakSunHours,
        float $performanceRatio = 0.80
    ): float {
        return round($capacityKwp * $peakSunHours * 365 * $performanceRatio, 2);
    }

    /**
     * Calculate monthly energy production.
     */
    public function calculateMonthlyProduction(
        float $capacityKwp,
        float $peakSunHours,
        float $performanceRatio = 0.80
    ): float {
        return round($this->calculateAnnualProduction($capacityKwp, $peakSunHours, $performanceRatio) / 12, 2);
    }

    /**
     * Calculate production with degradation for a specific year.
     *
     * @param  float  $initialAnnualProduction  First year production
     * @param  int  $year  Year number (1-25)
     * @param  float  $degradationRate  Annual degradation rate (default 0.5%)
     */
    public function calculateDegradedProduction(
        float $initialAnnualProduction,
        int $year,
        float $degradationRate = self::DEFAULT_DEGRADATION_RATE
    ): float {
        // Year 1 = no degradation, Year 2 = 1 year of degradation, etc.
        $degradationFactor = pow(1 - $degradationRate, $year - 1);

        return round($initialAnnualProduction * $degradationFactor, 2);
    }

    // ========================================
    // System Sizing
    // ========================================

    /**
     * Recommend system size based on consumption and solar offset target.
     *
     * @param  float  $monthlyConsumptionKwh  Monthly electricity consumption
     * @param  float  $peakSunHours  Peak sun hours per day
     * @param  float  $targetOffset  Target solar offset (1.0 = 100%)
     * @param  float  $performanceRatio  System performance ratio
     * @return float Recommended system capacity in kWp
     */
    public function recommendSystemSize(
        float $monthlyConsumptionKwh,
        float $peakSunHours,
        float $targetOffset = 1.0,
        float $performanceRatio = 0.80
    ): float {
        // Target annual production
        $targetAnnualProduction = $monthlyConsumptionKwh * 12 * $targetOffset;

        // Required capacity = Annual Production / (PSH × 365 × PR)
        $requiredCapacity = $targetAnnualProduction / ($peakSunHours * 365 * $performanceRatio);

        // Round up to nearest 0.5 kWp
        return ceil($requiredCapacity * 2) / 2;
    }

    /**
     * Calculate solar offset percentage.
     *
     * @param  float  $annualProduction  Annual solar production in kWh
     * @param  float  $monthlyConsumption  Monthly consumption in kWh
     */
    public function calculateSolarOffset(float $annualProduction, float $monthlyConsumption): float
    {
        $annualConsumption = $monthlyConsumption * 12;
        if ($annualConsumption <= 0) {
            return 0;
        }

        return round(($annualProduction / $annualConsumption) * 100, 1);
    }

    // ========================================
    // Financial Calculations
    // ========================================

    /**
     * Calculate comprehensive financial analysis.
     *
     * @param  float  $annualProduction  First year annual production in kWh
     * @param  float  $electricityRate  Electricity rate in Rp/kWh
     * @param  float  $tariffEscalation  Annual electricity price increase rate (e.g., 0.05 = 5%)
     * @param  int  $systemCost  Total system cost in Rp
     * @param  int  $years  Analysis period (default 25 years)
     * @param  float  $discountRate  Discount rate for NPV (default 8%)
     * @return array{
     *     payback_years: float,
     *     roi_percent: float,
     *     npv: int,
     *     irr_percent: float|null,
     *     total_lifetime_savings: int,
     *     first_year_savings: int,
     *     yearly_projections: array<int, array{year: int, production: float, rate: int, savings: int, cumulative_savings: int}>
     * }
     */
    public function calculateFinancialAnalysis(
        float $annualProduction,
        float $electricityRate,
        float $tariffEscalation,
        int $systemCost,
        int $years = self::SYSTEM_LIFETIME_YEARS,
        float $discountRate = self::DEFAULT_DISCOUNT_RATE
    ): array {
        $yearlyProjections = [];
        $cumulativeSavings = 0;
        $totalSavings = 0;
        $npvSum = 0;
        $paybackYear = null;
        $cashFlows = [-$systemCost]; // Initial investment as negative

        for ($year = 1; $year <= $years; $year++) {
            // Production with degradation
            $production = $this->calculateDegradedProduction($annualProduction, $year);

            // Electricity rate with escalation
            $rate = (int) round($electricityRate * pow(1 + $tariffEscalation, $year - 1));

            // Annual savings
            $savings = (int) round($production * $rate);

            // Cumulative
            $cumulativeSavings += $savings;
            $totalSavings += $savings;

            // NPV contribution
            $npvSum += $savings / pow(1 + $discountRate, $year);

            // Cash flow for IRR
            $cashFlows[] = $savings;

            // Check payback
            if ($paybackYear === null && $cumulativeSavings >= $systemCost) {
                // Calculate exact payback year with interpolation
                $previousCumulative = $cumulativeSavings - $savings;
                $remainingToPayback = $systemCost - $previousCumulative;
                $fractionOfYear = $remainingToPayback / $savings;
                $paybackYear = ($year - 1) + $fractionOfYear;
            }

            $yearlyProjections[] = [
                'year' => $year,
                'production' => $production,
                'rate' => $rate,
                'savings' => $savings,
                'cumulative_savings' => $cumulativeSavings,
            ];
        }

        // NPV = Sum of discounted cash flows - initial investment
        $npv = (int) round($npvSum - $systemCost);

        // ROI = (Total Savings - System Cost) / System Cost × 100
        $roi = $systemCost > 0 ? round((($totalSavings - $systemCost) / $systemCost) * 100, 1) : 0;

        // IRR calculation
        $irr = $this->calculateIrr($cashFlows);

        return [
            'payback_years' => $paybackYear !== null ? round($paybackYear, 1) : null,
            'roi_percent' => $roi,
            'npv' => $npv,
            'irr_percent' => $irr !== null ? round($irr * 100, 1) : null,
            'total_lifetime_savings' => $totalSavings,
            'first_year_savings' => $yearlyProjections[0]['savings'] ?? 0,
            'yearly_projections' => $yearlyProjections,
        ];
    }

    /**
     * Calculate Internal Rate of Return using Newton-Raphson method.
     *
     * @param  array<int, float|int>  $cashFlows  Array of cash flows (first is negative investment)
     * @return float|null IRR as decimal (0.15 = 15%), null if not converging
     */
    public function calculateIrr(array $cashFlows, float $guess = 0.1, int $maxIterations = 100): ?float
    {
        $rate = $guess;
        $tolerance = 0.0001;

        for ($i = 0; $i < $maxIterations; $i++) {
            $npv = 0;
            $derivativeNpv = 0;

            foreach ($cashFlows as $period => $cashFlow) {
                $npv += $cashFlow / pow(1 + $rate, $period);
                if ($period > 0) {
                    $derivativeNpv -= $period * $cashFlow / pow(1 + $rate, $period + 1);
                }
            }

            if (abs($derivativeNpv) < $tolerance) {
                // Derivative too small, can't continue
                return null;
            }

            $newRate = $rate - ($npv / $derivativeNpv);

            if (abs($newRate - $rate) < $tolerance) {
                return $newRate;
            }

            $rate = $newRate;

            // Prevent rate from going too negative or too high
            if ($rate < -0.99 || $rate > 10) {
                return null;
            }
        }

        return null; // Did not converge
    }

    /**
     * Calculate simple payback period.
     */
    public function calculateSimplePayback(int $systemCost, int $firstYearSavings): ?float
    {
        if ($firstYearSavings <= 0) {
            return null;
        }

        return round($systemCost / $firstYearSavings, 1);
    }

    // ========================================
    // Environmental Impact Calculations
    // ========================================

    /**
     * Calculate environmental impact.
     *
     * @param  float  $annualProductionKwh  Annual energy production in kWh
     * @return array{
     *     co2_offset_tons_per_year: float,
     *     co2_offset_kg_per_year: float,
     *     trees_equivalent: int,
     *     cars_equivalent: float,
     *     co2_lifetime_tons: float
     * }
     */
    public function calculateEnvironmentalImpact(float $annualProductionKwh): array
    {
        // CO2 offset = Production × Grid Emission Factor
        $co2OffsetKgPerYear = $annualProductionKwh * self::GRID_EMISSION_FACTOR;
        $co2OffsetTonsPerYear = $co2OffsetKgPerYear / 1000;

        // Trees equivalent = CO2 offset / CO2 absorbed per tree
        $treesEquivalent = (int) round($co2OffsetKgPerYear / self::CO2_PER_TREE_PER_YEAR);

        // Cars off road equivalent
        $carsEquivalent = round($co2OffsetKgPerYear / self::CO2_PER_CAR_PER_YEAR, 1);

        // Lifetime impact (25 years with degradation)
        $lifetimeCo2Tons = 0;
        for ($year = 1; $year <= self::SYSTEM_LIFETIME_YEARS; $year++) {
            $yearProduction = $this->calculateDegradedProduction($annualProductionKwh, $year);
            $lifetimeCo2Tons += ($yearProduction * self::GRID_EMISSION_FACTOR) / 1000;
        }

        return [
            'co2_offset_tons_per_year' => round($co2OffsetTonsPerYear, 2),
            'co2_offset_kg_per_year' => round($co2OffsetKgPerYear, 1),
            'trees_equivalent' => $treesEquivalent,
            'cars_equivalent' => $carsEquivalent,
            'co2_lifetime_tons' => round($lifetimeCo2Tons, 1),
        ];
    }

    // ========================================
    // Location Lookup Helpers
    // ========================================

    /**
     * Get solar data for a location by province and city.
     */
    public function getSolarDataByLocation(string $province, string $city): ?IndonesiaSolarData
    {
        return IndonesiaSolarData::findByLocation($province, $city);
    }

    /**
     * Get solar data for nearest location by coordinates.
     */
    public function getSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData
    {
        return IndonesiaSolarData::findNearest($latitude, $longitude);
    }

    /**
     * Get PLN tariff by category code.
     */
    public function getPlnTariff(string $categoryCode): ?PlnTariff
    {
        return PlnTariff::findByCode($categoryCode);
    }

    /**
     * Get PLN tariff by power capacity.
     */
    public function getPlnTariffByPower(int $powerVa, string $customerType = 'residential'): ?PlnTariff
    {
        return PlnTariff::findByPower($powerVa, $customerType);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Calculate roof area required for a given capacity.
     *
     * Assumes approximately 5-7 m² per kWp for typical panels.
     *
     * @param  float  $capacityKwp  System capacity
     * @param  float  $m2PerKwp  Square meters per kWp (default 6)
     */
    public function calculateRequiredRoofArea(float $capacityKwp, float $m2PerKwp = 6.0): float
    {
        return round($capacityKwp * $m2PerKwp, 1);
    }

    /**
     * Calculate maximum capacity for a given roof area.
     *
     * @param  float  $roofAreaM2  Available roof area in m²
     * @param  float  $usablePercent  Usable percentage of roof (default 70%)
     * @param  float  $m2PerKwp  Square meters per kWp (default 6)
     */
    public function calculateMaxCapacityForRoof(
        float $roofAreaM2,
        float $usablePercent = 0.70,
        float $m2PerKwp = 6.0
    ): float {
        $usableArea = $roofAreaM2 * $usablePercent;

        return round($usableArea / $m2PerKwp, 1);
    }

    /**
     * Apply orientation factor to production.
     *
     * North-facing roofs in Indonesia (southern hemisphere) get optimal production.
     * South-facing gets reduced production.
     *
     * @param  float  $production  Base production
     * @param  string  $orientation  Roof orientation
     */
    public function applyOrientationFactor(float $production, string $orientation): float
    {
        $factors = [
            'north' => 1.00,      // Optimal for Indonesia (near equator, slightly south)
            'south' => 0.95,      // Slightly reduced
            'east' => 0.97,       // Morning sun
            'west' => 0.97,       // Afternoon sun
            'northeast' => 0.98,
            'northwest' => 0.98,
            'southeast' => 0.96,
            'southwest' => 0.96,
        ];

        $factor = $factors[$orientation] ?? 1.0;

        return round($production * $factor, 2);
    }

    /**
     * Apply shading factor to production.
     *
     * @param  float  $production  Base production
     * @param  float  $shadingPercent  Shading percentage (0-100)
     */
    public function applyShadingFactor(float $production, float $shadingPercent): float
    {
        $factor = 1 - ($shadingPercent / 100);

        return round($production * $factor, 2);
    }
}
