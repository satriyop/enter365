<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Solar\PlnTariff;
use App\Services\Solar\SolarCalculationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicSolarCalculatorController extends Controller
{
    /**
     * Default price per kWp for investment estimation (Rp).
     * This is a rough market estimate for residential solar systems.
     */
    private const DEFAULT_PRICE_PER_KWP = 12_000_000;

    /**
     * Default tariff escalation rate (annual).
     */
    private const DEFAULT_TARIFF_ESCALATION = 0.03;

    /**
     * Indonesia average peak sun hours (conservative estimate).
     */
    private const DEFAULT_PEAK_SUN_HOURS = 4.5;

    public function __construct(
        private SolarCalculationService $calculationService
    ) {}

    /**
     * Calculate solar system recommendation based on savings goal.
     *
     * @operationId publicSolarCalculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_bill' => ['required', 'numeric', 'min:5000000'], // Min Rp 5 juta for B2B
            'pln_power_va' => ['nullable', 'integer', 'min:5500'], // Min 5.5 kVA for B2B
            'pln_category' => ['nullable', 'string'],
            'target_savings' => ['nullable', 'numeric', 'min:0'],
            'price_per_kwp' => ['nullable', 'numeric', 'min:1000000'],
        ]);

        // Get PLN tariff
        $tariff = $this->resolveTariff($validated);

        if (! $tariff) {
            return response()->json([
                'message' => 'Tarif PLN tidak ditemukan.',
                'error' => 'tariff_not_found',
            ], 404);
        }

        $monthlyBill = (float) $validated['monthly_bill'];
        $electricityRate = $tariff->rate_per_kwh;
        $peakSunHours = self::DEFAULT_PEAK_SUN_HOURS;
        $pricePerKwp = (int) ($validated['price_per_kwp'] ?? self::DEFAULT_PRICE_PER_KWP);

        // Calculate consumption from bill
        $monthlyConsumptionKwh = $monthlyBill / $electricityRate;

        // Determine target savings
        $targetSavings = $validated['target_savings'] ?? $monthlyBill;
        // Cap target savings at monthly bill (can't save more than you spend)
        $targetSavings = min($targetSavings, $monthlyBill);

        // Calculate required production to achieve target savings
        $requiredMonthlyProductionKwh = $targetSavings / $electricityRate;

        // Calculate required capacity
        // Formula: capacity = (monthly_production × 12) / (PSH × 365 × PR)
        $performanceRatio = 0.80;
        $requiredAnnualProductionKwh = $requiredMonthlyProductionKwh * 12;
        $requiredCapacityKwp = $requiredAnnualProductionKwh / ($peakSunHours * 365 * $performanceRatio);

        // Round up to nearest 0.5 kWp
        $recommendedCapacityKwp = ceil($requiredCapacityKwp * 2) / 2;

        // Calculate actual production with recommended capacity
        $annualProductionKwh = $this->calculationService->calculateAnnualProduction(
            $recommendedCapacityKwp,
            $peakSunHours,
            $performanceRatio
        );
        $monthlyProductionKwh = $annualProductionKwh / 12;

        // Calculate bill after solar (remaining PLN bill)
        $remainingConsumptionKwh = max(0, $monthlyConsumptionKwh - $monthlyProductionKwh);
        $monthlyBillAfter = (int) round($remainingConsumptionKwh * $electricityRate);

        // Calculate actual savings
        $monthlySavings = $monthlyBill - $monthlyBillAfter;
        $annualSavings = $monthlySavings * 12;

        // Calculate solar offset percentage
        $solarOffset = $monthlyConsumptionKwh > 0
            ? min(100, round(($monthlyProductionKwh / $monthlyConsumptionKwh) * 100, 1))
            : 0;

        // Calculate investment and payback
        $investmentCost = (int) round($recommendedCapacityKwp * $pricePerKwp);

        // Financial analysis with tariff escalation
        $financialAnalysis = $this->calculationService->calculateFinancialAnalysis(
            $annualProductionKwh,
            $electricityRate,
            self::DEFAULT_TARIFF_ESCALATION,
            $investmentCost
        );

        // Environmental impact
        $environmentalImpact = $this->calculationService->calculateEnvironmentalImpact($annualProductionKwh);

        return response()->json([
            'data' => [
                // Input summary
                'input' => [
                    'monthly_bill' => (int) $monthlyBill,
                    'target_savings' => (int) $targetSavings,
                    'pln_tariff' => [
                        'code' => $tariff->category_code,
                        'name' => $tariff->category_name,
                        'rate_per_kwh' => $electricityRate,
                    ],
                    'peak_sun_hours' => $peakSunHours,
                ],

                // Recommendation
                'recommendation' => [
                    'capacity_kwp' => $recommendedCapacityKwp,
                    'estimated_panels' => (int) ceil($recommendedCapacityKwp * 2.5), // ~400W panels
                    'roof_area_m2' => round($recommendedCapacityKwp * 6, 1),
                ],

                // Production
                'production' => [
                    'monthly_kwh' => round($monthlyProductionKwh, 1),
                    'annual_kwh' => round($annualProductionKwh, 1),
                ],

                // Savings summary (most important for user)
                'savings' => [
                    'monthly_bill_before' => (int) $monthlyBill,
                    'monthly_bill_after' => $monthlyBillAfter,
                    'monthly_savings' => (int) $monthlySavings,
                    'annual_savings' => (int) $annualSavings,
                    'solar_offset_percent' => $solarOffset,
                ],

                // Financial analysis
                'financial' => [
                    'investment_cost' => $investmentCost,
                    'price_per_kwp' => $pricePerKwp,
                    'payback_years' => $financialAnalysis['payback_years'],
                    'roi_percent' => $financialAnalysis['roi_percent'],
                    'lifetime_savings' => $financialAnalysis['total_lifetime_savings'],
                    'npv' => $financialAnalysis['npv'],
                ],

                // Environmental impact
                'environmental' => [
                    'co2_offset_tons_per_year' => $environmentalImpact['co2_offset_tons_per_year'],
                    'trees_equivalent' => $environmentalImpact['trees_equivalent'],
                    'co2_lifetime_tons' => $environmentalImpact['co2_lifetime_tons'],
                ],
            ],
        ]);
    }

    /**
     * Get available PLN tariffs grouped by customer type.
     *
     * @operationId publicSolarTariffs
     */
    public function tariffs(): JsonResponse
    {
        $tariffs = PlnTariff::query()
            ->active()
            ->orderBy('customer_type')
            ->orderBy('power_va_min')
            ->get([
                'category_code',
                'category_name',
                'customer_type',
                'power_va_min',
                'power_va_max',
                'rate_per_kwh',
            ]);

        $grouped = $tariffs->groupBy('customer_type');

        return response()->json([
            'data' => [
                'tariffs' => $tariffs,
                'grouped' => $grouped,
            ],
        ]);
    }

    /**
     * Resolve PLN tariff from request parameters.
     * Prioritizes business/industrial tariffs for B2B customers.
     */
    private function resolveTariff(array $validated): ?PlnTariff
    {
        // First try by category code
        if (! empty($validated['pln_category'])) {
            return PlnTariff::findByCode($validated['pln_category']);
        }

        // Then try by power VA (for business/industrial)
        if (! empty($validated['pln_power_va'])) {
            return PlnTariff::findByPower($validated['pln_power_va'], 'business')
                ?? PlnTariff::findByPower($validated['pln_power_va'], 'industrial')
                ?? PlnTariff::findByPower($validated['pln_power_va']);
        }

        // Default to B-2/TR (business tariff) for B2B calculator
        return PlnTariff::findByCode('B-2/TR')
            ?? PlnTariff::query()->where('customer_type', 'business')->first();
    }
}
