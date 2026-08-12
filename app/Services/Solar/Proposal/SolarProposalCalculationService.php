<?php

declare(strict_types=1);

namespace App\Services\Solar\Proposal;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Models\Solar\SolarProposal;
use App\Services\Base\Traits\WithEventDispatching;
use App\Services\Base\Traits\WithOperationContext;
use App\Services\Base\Traits\WithTransaction;
use App\Services\Solar\SolarCalculationService;

/**
 * Orchestrates proposal-level recalculation (capacity, production, financials, environment).
 *
 * Uses SolarCalculationService for pure calculation formulas.
 *
 * @see \App\Services\Solar\SolarProposalService The coordinator service
 * @see \App\Services\Solar\SolarCalculationService Pure calculation formulas
 */
class SolarProposalCalculationService
{
    use WithEventDispatching;
    use WithOperationContext;
    use WithTransaction;

    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        protected SolarCalculationService $calculator,
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Calculate and update all proposal values.
     *
     * This should be called after updating site info, consumption, or system selection.
     */
    public function calculateProposal(SolarProposal $proposal): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($proposal, 'Proposal hanya dapat dihitung ulang dalam status draft.');
        }

        return $this->executeInTransaction('calculate_proposal', function () use ($proposal) {
            // Get system capacity from selected BOM or calculate recommended size
            $capacityKwp = $proposal->system_capacity_kwp;

            if ($capacityKwp === null && $proposal->monthly_consumption_kwh && $proposal->peak_sun_hours) {
                $capacityKwp = $this->calculator->recommendSystemSize(
                    (float) $proposal->monthly_consumption_kwh,
                    (float) $proposal->peak_sun_hours,
                    1.0, // 100% offset target
                    (float) $proposal->performance_ratio
                );
                $proposal->system_capacity_kwp = (string) $capacityKwp;
            }

            // Calculate annual production
            if ($capacityKwp && $proposal->peak_sun_hours) {
                $baseProduction = $this->calculator->calculateAnnualProduction(
                    (float) $capacityKwp,
                    (float) $proposal->peak_sun_hours,
                    (float) $proposal->performance_ratio
                );

                // Apply orientation factor
                if ($proposal->roof_orientation) {
                    $baseProduction = $this->calculator->applyOrientationFactor(
                        $baseProduction,
                        $proposal->roof_orientation
                    );
                }

                // Apply shading factor
                if ($proposal->shading_percentage > 0) {
                    $baseProduction = $this->calculator->applyShadingFactor(
                        $baseProduction,
                        (float) $proposal->shading_percentage
                    );
                }

                $proposal->annual_production_kwh = (string) $baseProduction;
            }

            // Calculate financial analysis if we have all required data
            if (
                $proposal->annual_production_kwh &&
                $proposal->electricity_rate &&
                $proposal->selected_bom_id
            ) {
                $systemCost = $proposal->getSystemCost();

                if ($systemCost) {
                    $financialAnalysis = $this->calculator->calculateFinancialAnalysis(
                        (float) $proposal->annual_production_kwh,
                        (float) $proposal->electricity_rate,
                        (float) ($proposal->tariff_escalation_percent ?? 5) / 100,
                        $systemCost
                    );

                    $proposal->financial_analysis = $financialAnalysis;
                }
            }

            // Calculate environmental impact
            if ($proposal->annual_production_kwh) {
                $proposal->environmental_impact = $this->calculator->calculateEnvironmentalImpact(
                    (float) $proposal->annual_production_kwh
                );
            }

            $proposal->save();

            return $proposal->fresh(['contact', 'variantGroup', 'selectedBom']);
        }, ['proposal_id' => $proposal->id]);
    }
}
