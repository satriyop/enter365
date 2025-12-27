<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounting\SolarProposal
 */
class SolarProposalResource extends JsonResource
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
            'proposal_number' => $this->proposal_number,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),

            // Contact
            'contact_id' => $this->contact_id,
            'contact' => new ContactResource($this->whenLoaded('contact')),

            // Site Information
            'site_name' => $this->site_name,
            'site_address' => $this->site_address,
            'province' => $this->province,
            'city' => $this->city,
            'latitude' => $this->latitude ? (float) $this->latitude : null,
            'longitude' => $this->longitude ? (float) $this->longitude : null,
            'roof_area_m2' => $this->roof_area_m2 ? (float) $this->roof_area_m2 : null,
            'roof_type' => $this->roof_type,
            'roof_type_label' => $this->getRoofTypeLabel(),
            'roof_orientation' => $this->roof_orientation,
            'roof_orientation_label' => $this->getOrientationLabel(),
            'roof_tilt_degrees' => $this->roof_tilt_degrees ? (float) $this->roof_tilt_degrees : null,
            'shading_percentage' => $this->shading_percentage ? (float) $this->shading_percentage : null,

            // Electricity Profile
            'monthly_consumption_kwh' => $this->monthly_consumption_kwh ? (float) $this->monthly_consumption_kwh : null,
            'pln_tariff_category' => $this->pln_tariff_category,
            'electricity_rate' => $this->electricity_rate,
            'tariff_escalation_percent' => $this->tariff_escalation_percent ? (float) $this->tariff_escalation_percent : null,

            // Solar Data
            'peak_sun_hours' => $this->peak_sun_hours ? (float) $this->peak_sun_hours : null,
            'solar_irradiance' => $this->solar_irradiance ? (float) $this->solar_irradiance : null,
            'performance_ratio' => $this->performance_ratio ? (float) $this->performance_ratio : null,

            // System Selection
            'variant_group_id' => $this->variant_group_id,
            'variant_group' => new BomVariantGroupResource($this->whenLoaded('variantGroup')),
            'selected_bom_id' => $this->selected_bom_id,
            'selected_bom' => new BomResource($this->whenLoaded('selectedBom')),
            'system_capacity_kwp' => $this->system_capacity_kwp ? (float) $this->system_capacity_kwp : null,
            'annual_production_kwh' => $this->annual_production_kwh ? (float) $this->annual_production_kwh : null,
            'monthly_production_kwh' => $this->getMonthlyProduction(),
            'solar_offset_percent' => $this->getSolarOffsetPercent(),
            'system_cost' => $this->getSystemCost(),

            // Financial Analysis
            'financial_analysis' => $this->financial_analysis,
            'payback_years' => $this->getPaybackPeriod(),
            'roi_percent' => $this->getRoi(),
            'npv' => $this->getNpv(),
            'irr_percent' => $this->getIrr(),
            'first_year_savings' => $this->getFirstYearSavings(),
            'total_lifetime_savings' => $this->getTotalLifetimeSavings(),

            // Environmental Impact
            'environmental_impact' => $this->environmental_impact,
            'co2_offset_tons' => $this->getCo2OffsetTons(),
            'trees_equivalent' => $this->getTreesEquivalent(),
            'cars_equivalent' => $this->getCarsEquivalent(),

            // Proposal Settings
            'sections_config' => $this->sections_config,
            'custom_content' => $this->custom_content,
            'valid_until' => $this->valid_until?->toDateString(),
            'days_until_expiry' => $this->getDaysUntilExpiry(),
            'is_expired' => $this->isExpired(),
            'notes' => $this->notes,

            // Metadata
            'created_by' => $this->created_by,
            'creator' => new UserResource($this->whenLoaded('creator')),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'rejection_reason' => $this->rejection_reason,
            'converted_quotation_id' => $this->converted_quotation_id,
            'converted_quotation' => new QuotationResource($this->whenLoaded('convertedQuotation')),

            // Permissions
            'can_edit' => $this->isEditable(),
            'can_send' => $this->canSend(),
            'can_accept' => $this->canAccept(),
            'can_reject' => $this->canReject(),
            'can_convert' => $this->canConvert(),

            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
