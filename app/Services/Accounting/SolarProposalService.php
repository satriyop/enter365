<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\IndonesiaSolarData;
use App\Models\Accounting\Quotation;
use App\Models\Accounting\SolarProposal;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SolarProposalService
{
    public function __construct(
        protected SolarCalculationService $calculator,
        protected QuotationService $quotationService
    ) {}

    /**
     * Create a new solar proposal.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SolarProposal
    {
        return DB::transaction(function () use ($data) {
            // Set defaults
            $data['proposal_number'] = SolarProposal::generateProposalNumber();
            $data['status'] = SolarProposal::STATUS_DRAFT;
            $data['created_by'] = auth()->id();
            $data['performance_ratio'] = $data['performance_ratio'] ?? 0.80;

            // Set default validity (30 days)
            if (empty($data['valid_until'])) {
                $data['valid_until'] = now()->addDays(30);
            }

            // Lookup solar data if province/city provided but not peak_sun_hours
            if (empty($data['peak_sun_hours']) && ! empty($data['province']) && ! empty($data['city'])) {
                $solarData = $this->calculator->getSolarDataByLocation($data['province'], $data['city']);
                if ($solarData) {
                    $data['peak_sun_hours'] = $solarData->peak_sun_hours;
                    $data['solar_irradiance'] = $solarData->solar_irradiance_kwh_m2_day;
                }
            }

            // Create proposal
            $proposal = SolarProposal::create($data);

            return $proposal->load(['contact', 'creator']);
        });
    }

    /**
     * Update a solar proposal.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SolarProposal $proposal, array $data): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw new InvalidArgumentException('Proposal hanya dapat diedit dalam status draft.');
        }

        return DB::transaction(function () use ($proposal, $data) {
            // Update solar data if location changed
            if (
                (isset($data['province']) || isset($data['city'])) &&
                empty($data['peak_sun_hours'])
            ) {
                $province = $data['province'] ?? $proposal->province;
                $city = $data['city'] ?? $proposal->city;

                if ($province && $city) {
                    $solarData = $this->calculator->getSolarDataByLocation($province, $city);
                    if ($solarData) {
                        $data['peak_sun_hours'] = $solarData->peak_sun_hours;
                        $data['solar_irradiance'] = $solarData->solar_irradiance_kwh_m2_day;
                    }
                }
            }

            $proposal->fill($data);
            $proposal->save();

            return $proposal->fresh(['contact', 'creator', 'variantGroup', 'selectedBom']);
        });
    }

    /**
     * Delete a solar proposal.
     */
    public function delete(SolarProposal $proposal): bool
    {
        if ($proposal->status !== SolarProposal::STATUS_DRAFT) {
            throw new InvalidArgumentException('Hanya proposal draft yang dapat dihapus.');
        }

        return $proposal->delete();
    }

    /**
     * Calculate and update all proposal values.
     *
     * This should be called after updating site info, consumption, or system selection.
     */
    public function calculateProposal(SolarProposal $proposal): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw new InvalidArgumentException('Proposal hanya dapat dihitung ulang dalam status draft.');
        }

        return DB::transaction(function () use ($proposal) {
            // Get system capacity from selected BOM or calculate recommended size
            $capacityKwp = $proposal->system_capacity_kwp;

            if ($capacityKwp === null && $proposal->monthly_consumption_kwh && $proposal->peak_sun_hours) {
                $capacityKwp = $this->calculator->recommendSystemSize(
                    (float) $proposal->monthly_consumption_kwh,
                    (float) $proposal->peak_sun_hours,
                    1.0, // 100% offset target
                    (float) $proposal->performance_ratio
                );
                $proposal->system_capacity_kwp = $capacityKwp;
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

                $proposal->annual_production_kwh = $baseProduction;
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
        });
    }

    /**
     * Attach a BOM variant group to the proposal.
     *
     * This links the proposal to a set of Budget/Standard/Premium options.
     */
    public function attachVariantGroup(SolarProposal $proposal, int $variantGroupId): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw new InvalidArgumentException('Proposal hanya dapat diedit dalam status draft.');
        }

        $variantGroup = BomVariantGroup::with('activeBoms')->find($variantGroupId);
        if (! $variantGroup) {
            throw new InvalidArgumentException('Variant group tidak ditemukan.');
        }

        return DB::transaction(function () use ($proposal, $variantGroup) {
            $proposal->variant_group_id = $variantGroup->id;

            // Auto-select the primary (recommended) BOM if available
            $primaryBom = $variantGroup->primaryBom();
            if ($primaryBom) {
                $proposal->selected_bom_id = $primaryBom->id;
                $proposal->system_capacity_kwp = $this->extractCapacityFromBom($primaryBom);
            }

            $proposal->save();

            // Recalculate with new system
            return $this->calculateProposal($proposal);
        });
    }

    /**
     * Select a specific BOM from the variant group.
     */
    public function selectBom(SolarProposal $proposal, int $bomId): SolarProposal
    {
        if (! $proposal->isEditable()) {
            throw new InvalidArgumentException('Proposal hanya dapat diedit dalam status draft.');
        }

        $bom = Bom::find($bomId);
        if (! $bom) {
            throw new InvalidArgumentException('BOM tidak ditemukan.');
        }

        // Verify BOM belongs to the attached variant group (if any)
        if ($proposal->variant_group_id && $bom->variant_group_id !== $proposal->variant_group_id) {
            throw new InvalidArgumentException('BOM tidak termasuk dalam variant group yang dipilih.');
        }

        return DB::transaction(function () use ($proposal, $bom) {
            $proposal->selected_bom_id = $bom->id;
            $proposal->system_capacity_kwp = $this->extractCapacityFromBom($bom);

            // If no variant group attached, attach it now
            if (! $proposal->variant_group_id && $bom->variant_group_id) {
                $proposal->variant_group_id = $bom->variant_group_id;
            }

            $proposal->save();

            return $this->calculateProposal($proposal);
        });
    }

    /**
     * Mark proposal as sent to customer.
     */
    public function send(SolarProposal $proposal): SolarProposal
    {
        if (! $proposal->canSend()) {
            throw new InvalidArgumentException(
                'Proposal tidak dapat dikirim. Pastikan variant group sudah dipilih dan kalkulasi sudah selesai.'
            );
        }

        return DB::transaction(function () use ($proposal) {
            $proposal->status = SolarProposal::STATUS_SENT;
            $proposal->sent_at = now();
            $proposal->save();

            return $proposal->fresh();
        });
    }

    /**
     * Mark proposal as accepted by customer.
     *
     * @param  int|null  $selectedBomId  The BOM variant customer selected (if multi-option)
     */
    public function accept(SolarProposal $proposal, ?int $selectedBomId = null): SolarProposal
    {
        if (! $proposal->canAccept()) {
            throw new InvalidArgumentException('Proposal tidak dapat diterima dalam status saat ini.');
        }

        return DB::transaction(function () use ($proposal, $selectedBomId) {
            // Update selected BOM if customer chose a different variant
            if ($selectedBomId && $selectedBomId !== $proposal->selected_bom_id) {
                $bom = Bom::find($selectedBomId);
                if ($bom && $bom->variant_group_id === $proposal->variant_group_id) {
                    $proposal->selected_bom_id = $selectedBomId;
                }
            }

            $proposal->status = SolarProposal::STATUS_ACCEPTED;
            $proposal->accepted_at = now();
            $proposal->save();

            return $proposal->fresh(['contact', 'selectedBom']);
        });
    }

    /**
     * Mark proposal as rejected by customer.
     */
    public function reject(SolarProposal $proposal, ?string $reason = null): SolarProposal
    {
        if (! $proposal->canReject()) {
            throw new InvalidArgumentException('Proposal tidak dapat ditolak dalam status saat ini.');
        }

        return DB::transaction(function () use ($proposal, $reason) {
            $proposal->status = SolarProposal::STATUS_REJECTED;
            $proposal->rejected_at = now();
            $proposal->rejection_reason = $reason;
            $proposal->save();

            return $proposal->fresh();
        });
    }

    /**
     * Convert accepted proposal to a quotation.
     *
     * Creates a quotation from the selected BOM with the proposal's pricing.
     */
    public function convertToQuotation(SolarProposal $proposal): Quotation
    {
        if (! $proposal->canConvert()) {
            throw new InvalidArgumentException(
                'Proposal tidak dapat dikonversi. Pastikan sudah diterima dan memiliki BOM yang dipilih.'
            );
        }

        return DB::transaction(function () use ($proposal) {
            $bom = $proposal->selectedBom;

            // Create quotation from the selected BOM
            $quotation = $this->quotationService->createFromBom([
                'bom_id' => $bom->id,
                'contact_id' => $proposal->contact_id,
                'expand_items' => false, // Keep as single line for solar systems
                'subject' => 'Sistem Panel Surya - '.$proposal->site_name,
                'notes' => $this->buildQuotationNotes($proposal),
                'reference' => $proposal->proposal_number,
            ]);

            // Link proposal to quotation
            $proposal->converted_quotation_id = $quotation->id;
            $proposal->save();

            return $quotation;
        });
    }

    /**
     * Get solar data lookup for location.
     */
    public function lookupSolarData(string $province, string $city): ?IndonesiaSolarData
    {
        return $this->calculator->getSolarDataByLocation($province, $city);
    }

    /**
     * Get solar data by coordinates.
     */
    public function lookupSolarDataByCoordinates(float $latitude, float $longitude): ?IndonesiaSolarData
    {
        return $this->calculator->getSolarDataByCoordinates($latitude, $longitude);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Extract system capacity from BOM.
     *
     * Looks for kWp in the BOM name or calculates from panel count.
     */
    protected function extractCapacityFromBom(Bom $bom): ?float
    {
        // Try to extract from BOM name (e.g., "10 kWp Solar System")
        if (preg_match('/(\d+(?:\.\d+)?)\s*k[Ww][Pp]/i', $bom->name, $matches)) {
            return (float) $matches[1];
        }

        // Try variant name
        if ($bom->variant_name && preg_match('/(\d+(?:\.\d+)?)\s*k[Ww][Pp]/i', $bom->variant_name, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Build notes for quotation from proposal data.
     */
    protected function buildQuotationNotes(SolarProposal $proposal): string
    {
        $notes = [];

        $notes[] = "Dibuat dari Solar Proposal: {$proposal->proposal_number}";
        $notes[] = '';

        if ($proposal->site_address) {
            $notes[] = "Lokasi: {$proposal->site_address}";
        }

        if ($proposal->system_capacity_kwp) {
            $notes[] = "Kapasitas Sistem: {$proposal->system_capacity_kwp} kWp";
        }

        if ($proposal->annual_production_kwh) {
            $notes[] = 'Estimasi Produksi: '.number_format((float) $proposal->annual_production_kwh).' kWh/tahun';
        }

        if ($proposal->getPaybackPeriod()) {
            $notes[] = "Payback Period: {$proposal->getPaybackPeriod()} tahun";
        }

        if ($proposal->getRoi()) {
            $notes[] = "ROI (25 tahun): {$proposal->getRoi()}%";
        }

        return implode("\n", $notes);
    }
}
