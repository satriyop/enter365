<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Solar\IndonesiaSolarData;
use App\Models\Solar\PlnTariff;

/**
 * Interface for Solar Calculation service operations.
 */
interface SolarCalculationServiceInterface
{
    /**
     * Calculate annual energy production.
     *
     * @param  float  $capacityKwp  System capacity in kWp
     * @param  float  $peakSunHours  Peak sun hours per day
     * @param  float  $performanceRatio  System performance ratio
     */
    public function calculateAnnualProduction(
        float $capacityKwp,
        float $peakSunHours,
        float $performanceRatio = 0.80
    ): float;

    /**
     * Calculate monthly energy production.
     */
    public function calculateMonthlyProduction(
        float $capacityKwp,
        float $peakSunHours,
        float $performanceRatio = 0.80
    ): float;

    /**
     * Calculate production with degradation for a specific year.
     */
    public function calculateDegradedProduction(
        float $initialAnnualProduction,
        int $year,
        float $degradationRate = 0.005
    ): float;

    /**
     * Recommend system size based on consumption.
     */
    public function recommendSystemSize(
        float $monthlyConsumptionKwh,
        float $peakSunHours,
        float $targetOffset = 1.0,
        float $performanceRatio = 0.80
    ): float;

    /**
     * Calculate solar offset percentage.
     */
    public function calculateSolarOffset(float $annualProduction, float $monthlyConsumption): float;

    /**
     * Calculate comprehensive financial analysis.
     *
     * @return array<string, mixed>
     */
    public function calculateFinancialAnalysis(
        float $annualProduction,
        float $electricityRate,
        float $tariffEscalation,
        int $systemCost,
        int $years = 25,
        float $discountRate = 0.06
    ): array;

    /**
     * Calculate environmental impact.
     *
     * @return array<string, mixed>
     */
    public function calculateEnvironmentalImpact(float $annualProductionKwh): array;

    /**
     * Get solar data by location.
     */
    public function getSolarDataByLocation(string $province, string $city): ?IndonesiaSolarData;

    /**
     * Get solar data by coordinates.
     */
    public function getSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData;

    /**
     * Get PLN tariff by category code.
     */
    public function getPlnTariff(string $categoryCode): ?PlnTariff;

    /**
     * Get PLN tariff by power VA.
     */
    public function getPlnTariffByPower(int $powerVa, string $customerType = 'residential'): ?PlnTariff;
}
