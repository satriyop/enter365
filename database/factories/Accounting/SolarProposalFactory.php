<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Contact;
use App\Models\Accounting\SolarProposal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SolarProposal>
 */
class SolarProposalFactory extends Factory
{
    protected $model = SolarProposal::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'SPR-'.now()->format('Ym').'-';
        $proposalNumber = $prefix.str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        // Default to Jakarta location
        $solarData = [
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'latitude' => -6.2615,
            'longitude' => 106.8106,
            'peak_sun_hours' => 4.5,
            'solar_irradiance' => 4.8,
        ];

        $monthlyConsumption = $this->faker->randomElement([500, 800, 1000, 1500, 2000, 3000]);
        $electricityRate = $this->faker->randomElement([1444, 1699, 1444]); // Common PLN rates

        return [
            'proposal_number' => $proposalNumber,
            'contact_id' => Contact::factory()->customer(),
            'status' => SolarProposal::STATUS_DRAFT,

            // Site Information
            'site_name' => $this->faker->company(),
            'site_address' => $this->faker->streetAddress(),
            'province' => $solarData['province'],
            'city' => $solarData['city'],
            'latitude' => $solarData['latitude'],
            'longitude' => $solarData['longitude'],
            'roof_area_m2' => $this->faker->randomFloat(2, 50, 500),
            'roof_type' => $this->faker->randomElement([
                SolarProposal::ROOF_TYPE_FLAT,
                SolarProposal::ROOF_TYPE_SLOPED,
                SolarProposal::ROOF_TYPE_CARPORT,
            ]),
            'roof_orientation' => $this->faker->randomElement([
                SolarProposal::ORIENTATION_NORTH,
                SolarProposal::ORIENTATION_SOUTH,
                SolarProposal::ORIENTATION_EAST,
                SolarProposal::ORIENTATION_WEST,
            ]),
            'roof_tilt_degrees' => $this->faker->randomFloat(2, 0, 30),
            'shading_percentage' => $this->faker->randomFloat(2, 0, 20),

            // Electricity Profile
            'monthly_consumption_kwh' => $monthlyConsumption,
            'pln_tariff_category' => 'R-1/TR',
            'electricity_rate' => $electricityRate,
            'tariff_escalation_percent' => 3.00,

            // Solar Data
            'peak_sun_hours' => $solarData['peak_sun_hours'],
            'solar_irradiance' => $solarData['solar_irradiance'],
            'performance_ratio' => 0.80,

            // System Selection (null by default - set via factory states)
            'variant_group_id' => null,
            'selected_bom_id' => null,
            'system_capacity_kwp' => null,
            'annual_production_kwh' => null,

            // Calculated Results (null by default - populated after calculation)
            'financial_analysis' => null,
            'environmental_impact' => null,

            // Proposal Settings
            'sections_config' => null,
            'custom_content' => null,
            'valid_until' => now()->addDays(30),
            'notes' => $this->faker->optional()->paragraph(),

            // Metadata
            'created_by' => User::factory(),
            'sent_at' => null,
            'accepted_at' => null,
            'rejected_at' => null,
            'rejection_reason' => null,
            'converted_quotation_id' => null,
        ];
    }

    // ========================================
    // Status States
    // ========================================

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SolarProposal::STATUS_DRAFT,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SolarProposal::STATUS_SENT,
            'sent_at' => now(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SolarProposal::STATUS_ACCEPTED,
            'sent_at' => now()->subDays(3),
            'accepted_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SolarProposal::STATUS_REJECTED,
            'sent_at' => now()->subDays(3),
            'rejected_at' => now(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SolarProposal::STATUS_EXPIRED,
            'valid_until' => now()->subDays(7),
        ]);
    }

    // ========================================
    // Relationship Helpers
    // ========================================

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withVariantGroup(BomVariantGroup $variantGroup): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_group_id' => $variantGroup->id,
        ]);
    }

    public function withSelectedBom(Bom $bom): static
    {
        return $this->state(fn (array $attributes) => [
            'selected_bom_id' => $bom->id,
        ]);
    }

    // ========================================
    // System Configuration States
    // ========================================

    /**
     * Create a proposal with calculated values for a specific capacity.
     */
    public function withSystemCapacity(float $capacityKwp): static
    {
        return $this->state(function (array $attributes) use ($capacityKwp) {
            $peakSunHours = $attributes['peak_sun_hours'] ?? 4.5;
            $performanceRatio = $attributes['performance_ratio'] ?? 0.80;
            $annualProduction = $capacityKwp * $peakSunHours * 365 * $performanceRatio;

            return [
                'system_capacity_kwp' => $capacityKwp,
                'annual_production_kwh' => $annualProduction,
            ];
        });
    }

    /**
     * Create a proposal with full financial and environmental calculations.
     */
    public function calculated(): static
    {
        return $this->state(function (array $attributes) {
            $capacityKwp = $attributes['system_capacity_kwp'] ?? 5.0;
            $peakSunHours = $attributes['peak_sun_hours'] ?? 4.5;
            $performanceRatio = $attributes['performance_ratio'] ?? 0.80;
            $annualProduction = $capacityKwp * $peakSunHours * 365 * $performanceRatio;
            $electricityRate = $attributes['electricity_rate'] ?? 1444;
            $tariffEscalation = $attributes['tariff_escalation_percent'] ?? 3.0;

            // Simulate a system cost (Rp 12M per kWp average)
            $systemCost = (int) ($capacityKwp * 12000000);

            // Calculate financial metrics
            $annualSavings = (int) ($annualProduction * $electricityRate);
            $paybackYears = $systemCost / $annualSavings;
            $totalSavings25Years = 0;
            $yearlyProjections = [];

            $currentRate = $electricityRate;
            for ($year = 1; $year <= 25; $year++) {
                $yearlySavings = (int) ($annualProduction * $currentRate);
                $totalSavings25Years += $yearlySavings;
                $yearlyProjections[] = [
                    'year' => $year,
                    'rate' => (int) $currentRate,
                    'production' => (int) $annualProduction,
                    'savings' => $yearlySavings,
                ];
                $currentRate *= (1 + $tariffEscalation / 100);
            }

            $roi = (($totalSavings25Years - $systemCost) / $systemCost) * 100;

            // Environmental calculations
            $co2Factor = 0.709; // kg CO2 per kWh in Indonesia
            $co2OffsetTons = ($annualProduction * $co2Factor) / 1000;
            $treesEquivalent = (int) ($co2OffsetTons * 50);
            $carsEquivalent = round($co2OffsetTons / 4.6, 1);

            return [
                'system_capacity_kwp' => $capacityKwp,
                'annual_production_kwh' => $annualProduction,
                'financial_analysis' => [
                    'system_cost' => $systemCost,
                    'annual_savings_year1' => $annualSavings,
                    'payback_years' => round($paybackYears, 2),
                    'roi_percent' => round($roi, 2),
                    'total_lifetime_savings' => $totalSavings25Years,
                    'yearly_projections' => $yearlyProjections,
                ],
                'environmental_impact' => [
                    'co2_offset_tons_per_year' => round($co2OffsetTons, 2),
                    'trees_equivalent' => $treesEquivalent,
                    'cars_equivalent' => $carsEquivalent,
                ],
            ];
        });
    }

    // ========================================
    // Location States
    // ========================================

    /**
     * Set proposal location to Jakarta.
     */
    public function inJakarta(): static
    {
        return $this->state(fn (array $attributes) => [
            'province' => 'DKI Jakarta',
            'city' => 'Jakarta Selatan',
            'latitude' => -6.2615,
            'longitude' => 106.8106,
            'peak_sun_hours' => 4.5,
            'solar_irradiance' => 4.8,
        ]);
    }

    /**
     * Set proposal location to Surabaya.
     */
    public function inSurabaya(): static
    {
        return $this->state(fn (array $attributes) => [
            'province' => 'Jawa Timur',
            'city' => 'Surabaya',
            'latitude' => -7.2575,
            'longitude' => 112.7521,
            'peak_sun_hours' => 4.8,
            'solar_irradiance' => 5.1,
        ]);
    }

    /**
     * Set proposal location to Bali.
     */
    public function inBali(): static
    {
        return $this->state(fn (array $attributes) => [
            'province' => 'Bali',
            'city' => 'Denpasar',
            'latitude' => -8.6705,
            'longitude' => 115.2126,
            'peak_sun_hours' => 5.2,
            'solar_irradiance' => 5.5,
        ]);
    }

    // ========================================
    // Validity States
    // ========================================

    public function validFor(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->addDays($days),
        ]);
    }

    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->addDays(3),
        ]);
    }

    // ========================================
    // PLN Tariff States
    // ========================================

    /**
     * Set as residential customer (R-1/TR).
     */
    public function residential(): static
    {
        return $this->state(fn (array $attributes) => [
            'pln_tariff_category' => 'R-1/TR',
            'electricity_rate' => 1444,
        ]);
    }

    /**
     * Set as industrial customer (I-3/TM).
     */
    public function industrial(): static
    {
        return $this->state(fn (array $attributes) => [
            'pln_tariff_category' => 'I-3/TM',
            'electricity_rate' => 1115,
            'monthly_consumption_kwh' => $this->faker->randomElement([5000, 10000, 20000, 50000]),
        ]);
    }

    /**
     * Set as commercial customer (B-2/TR).
     */
    public function commercial(): static
    {
        return $this->state(fn (array $attributes) => [
            'pln_tariff_category' => 'B-2/TR',
            'electricity_rate' => 1444,
            'monthly_consumption_kwh' => $this->faker->randomElement([2000, 5000, 8000]),
        ]);
    }

    // ========================================
    // Ready-to-Send State
    // ========================================

    /**
     * Create a fully configured proposal ready to be sent.
     * Includes calculations and system configuration.
     */
    public function readyToSend(): static
    {
        return $this->withSystemCapacity(5.0)->calculated();
    }
}
