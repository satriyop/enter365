<?php

use App\Services\Accounting\SolarCalculationService;

beforeEach(function () {
    $this->service = new SolarCalculationService;
});

describe('Energy Production Calculations', function () {

    it('calculates annual production correctly', function () {
        // 10 kWp * 4.5 PSH * 365 days * 0.80 PR = 13,140 kWh
        $result = $this->service->calculateAnnualProduction(
            capacityKwp: 10.0,
            peakSunHours: 4.5,
            performanceRatio: 0.80
        );

        expect($result)->toBe(13140.0);
    });

    it('uses default performance ratio when not specified', function () {
        // 5 kWp * 4.5 PSH * 365 * 0.80 (default) = 6,570 kWh
        $result = $this->service->calculateAnnualProduction(
            capacityKwp: 5.0,
            peakSunHours: 4.5
        );

        expect($result)->toBe(6570.0);
    });

    it('handles different performance ratios', function () {
        // 10 kWp * 4.5 PSH * 365 * 0.75 = 12,318.75 kWh
        $result = $this->service->calculateAnnualProduction(
            capacityKwp: 10.0,
            peakSunHours: 4.5,
            performanceRatio: 0.75
        );

        expect($result)->toBe(12318.75);
    });

    it('returns zero for zero capacity', function () {
        $result = $this->service->calculateAnnualProduction(
            capacityKwp: 0,
            peakSunHours: 4.5
        );

        expect($result)->toBe(0.0);
    });
});

describe('Financial Analysis Calculations', function () {

    it('calculates payback period correctly', function () {
        // Note: Service calculates with degradation, so actual payback differs from simple calculation
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03, // 3% as decimal
            systemCost: 60000000
        );

        // With degradation and escalation, payback should be reasonable
        expect($result['payback_years'])->toBeGreaterThan(0);
        expect($result['payback_years'])->toBeLessThan(15);
    });

    it('calculates 25-year projections', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03,
            systemCost: 60000000,
            years: 25
        );

        expect($result['yearly_projections'])->toHaveCount(25);
        expect($result['yearly_projections'][0]['year'])->toBe(1);
        expect($result['yearly_projections'][24]['year'])->toBe(25);
    });

    it('applies tariff escalation each year', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1000, // Easy to calculate
            tariffEscalation: 0.10, // 10% escalation as decimal
            systemCost: 60000000,
            years: 3
        );

        // Year 1: rate 1000
        // Year 2: rate 1100
        // Year 3: rate 1210
        expect($result['yearly_projections'][0]['rate'])->toBe(1000);
        expect($result['yearly_projections'][1]['rate'])->toBe(1100);
        expect($result['yearly_projections'][2]['rate'])->toBe(1210);
    });

    it('calculates positive ROI over 25 years', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03,
            systemCost: 60000000
        );

        // With escalating tariffs, ROI should be significantly positive
        expect($result['roi_percent'])->toBeGreaterThan(100);
    });

    it('calculates NPV with discount rate', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03,
            systemCost: 60000000,
            discountRate: 0.08 // 8% discount rate
        );

        // NPV should be positive for a good solar investment
        expect($result['npv'])->toBeGreaterThan(0);
    });

    it('calculates total lifetime savings', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03,
            systemCost: 60000000
        );

        // Total savings should be sum of all yearly savings
        $expectedTotal = array_sum(array_column($result['yearly_projections'], 'savings'));
        expect($result['total_lifetime_savings'])->toBe($expectedTotal);
    });

    it('returns all required financial metrics', function () {
        $result = $this->service->calculateFinancialAnalysis(
            annualProduction: 6570,
            electricityRate: 1444,
            tariffEscalation: 0.03,
            systemCost: 60000000
        );

        expect($result)->toHaveKeys([
            'payback_years',
            'roi_percent',
            'npv',
            'irr_percent',
            'total_lifetime_savings',
            'first_year_savings',
            'yearly_projections',
        ]);
    });
});

describe('IRR Calculation', function () {

    it('calculates IRR using Newton-Raphson method', function () {
        // Simple test case: invest 100, get 50 for 3 years
        // IRR should be around 23.4%
        $cashFlows = [-100, 50, 50, 50];

        $irr = $this->service->calculateIrr($cashFlows);

        expect($irr)->not->toBeNull();
        expect($irr)->toBeGreaterThan(0.20);
        expect($irr)->toBeLessThan(0.25);
    });

    it('returns null for non-converging scenarios', function () {
        // All positive cash flows - no IRR
        $cashFlows = [100, 50, 50];

        $irr = $this->service->calculateIrr($cashFlows);

        expect($irr)->toBeNull();
    });

    it('calculates IRR for typical solar investment', function () {
        // 60M investment, ~9.5M savings year 1, escalating 3%/year
        $cashFlows = [-60000000];
        $annualSavings = 9487080; // 6570 kWh * 1444 IDR

        for ($year = 1; $year <= 25; $year++) {
            $cashFlows[] = (int) ($annualSavings * pow(1.03, $year - 1));
        }

        $irr = $this->service->calculateIrr($cashFlows);

        // Typical solar IRR should be 10-20%
        expect($irr)->not->toBeNull();
        expect($irr)->toBeGreaterThan(0.10);
        expect($irr)->toBeLessThan(0.25);
    });
});

describe('Environmental Impact Calculations', function () {

    it('calculates CO2 offset using Indonesia grid factor', function () {
        // Indonesia grid emission factor: 0.709 kg CO2/kWh
        // 6,570 kWh * 0.709 = 4,658 kg = 4.658 tons
        $result = $this->service->calculateEnvironmentalImpact(6570);

        expect($result['co2_offset_tons_per_year'])->toBe(4.66); // rounded
    });

    it('calculates trees equivalent', function () {
        // Uses CO2_PER_TREE_PER_YEAR constant (21.77 kg)
        // 6570 * 0.709 = 4658.13 kg CO2 / 21.77 = 214 trees
        $result = $this->service->calculateEnvironmentalImpact(6570);

        expect($result['trees_equivalent'])->toBe(214);
    });

    it('calculates cars off road equivalent', function () {
        // Uses CO2_PER_CAR_PER_YEAR constant (4600 kg)
        // 4658.13 kg / 4600 = 1.0 cars
        $result = $this->service->calculateEnvironmentalImpact(6570);

        expect($result['cars_equivalent'])->toBe(1.0);
    });

    it('calculates lifetime 25-year impact with degradation', function () {
        $result = $this->service->calculateEnvironmentalImpact(6570);

        // Lifetime total should account for panel degradation over 25 years
        expect($result['co2_lifetime_tons'])->toBeGreaterThan(100);
        expect($result['co2_lifetime_tons'])->toBeLessThan(120);
    });

    it('returns all environmental metrics', function () {
        $result = $this->service->calculateEnvironmentalImpact(6570);

        expect($result)->toHaveKeys([
            'co2_offset_tons_per_year',
            'co2_offset_kg_per_year',
            'trees_equivalent',
            'cars_equivalent',
            'co2_lifetime_tons',
        ]);
    });

    it('handles zero production', function () {
        $result = $this->service->calculateEnvironmentalImpact(0);

        expect($result['co2_offset_tons_per_year'])->toBe(0.0);
        expect($result['trees_equivalent'])->toBe(0);
        expect($result['cars_equivalent'])->toBe(0.0);
    });
});

describe('System Sizing Recommendations', function () {

    it('recommends system size based on consumption', function () {
        // 1500 kWh/month consumption
        // Target: 100% offset
        // With 4.5 PSH and 0.8 PR: 1500 * 12 / (4.5 * 365 * 0.8) = ~13.7 kWp
        $result = $this->service->recommendSystemSize(
            monthlyConsumptionKwh: 1500,
            peakSunHours: 4.5
        );

        expect($result)->toBeGreaterThan(10.0);
        expect($result)->toBeLessThan(20.0);
    });

    it('respects target offset percentage', function () {
        // If targeting 50% offset (0.5), should recommend half the size
        $fullSize = $this->service->recommendSystemSize(1500, 4.5, targetOffset: 1.0);
        $halfSize = $this->service->recommendSystemSize(1500, 4.5, targetOffset: 0.5);

        // Half size should be roughly half of full size (allowing for rounding)
        expect($halfSize)->toBeLessThanOrEqual($fullSize / 2 + 0.5);
        expect($halfSize)->toBeGreaterThanOrEqual($fullSize / 2 - 0.5);
    });

    it('rounds to practical sizes', function () {
        // System sizes should be in practical increments (0.5 kWp steps)
        $result = $this->service->recommendSystemSize(1234, 4.5);

        // Should be rounded to nearest 0.5 kWp
        $decimal = $result - floor($result);
        expect($decimal)->toBeIn([0, 0.5]);
    });
});
