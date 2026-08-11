<?php

namespace App\Http\Resources\Api\V1\Solar;

use App\Http\Resources\Api\V1\BomResource;
use App\Http\Resources\Api\V1\BomVariantGroupResource;
use App\Http\Resources\Api\V1\ContactResource;
use App\Http\Resources\Api\V1\QuotationResource;
use App\Http\Resources\Api\V1\StatusResource;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Solar\SolarProposal
 */
class SolarProposalResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array{
     *   id: int,
     *   proposal_number: string,
     *   status: StatusResource,
     *   status_label: string,
     *   contact_id: int,
     *   contact?: ContactResource,
     *   site_name: string|null,
     *   site_address: string|null,
     *   province: string|null,
     *   city: string|null,
     *   latitude: float|null,
     *   longitude: float|null,
     *   roof_area_m2: float|null,
     *   roof_polygon: array<mixed>|null,
     *   roof_type: string|null,
     *   roof_type_label: string|null,
     *   roof_orientation: string|null,
     *   roof_orientation_label: string|null,
     *   roof_tilt_degrees: float|null,
     *   shading_percentage: float|null,
     *   monthly_consumption_kwh: float|null,
     *   pln_tariff_category: string|null,
     *   electricity_rate: int|null,
     *   tariff_escalation_percent: float|null,
     *   peak_sun_hours: float|null,
     *   solar_irradiance: float|null,
     *   performance_ratio: float|null,
     *   variant_group_id: int|null,
     *   variant_group?: BomVariantGroupResource,
     *   selected_bom_id: int|null,
     *   selected_bom?: BomResource,
     *   system_capacity_kwp: float|null,
     *   annual_production_kwh: float|null,
     *   monthly_production_kwh: float|null,
     *   solar_offset_percent: float|null,
     *   system_cost: int|null,
     *   financial_analysis: array<string, mixed>|null,
     *   payback_years: float|null,
     *   roi_percent: float|null,
     *   npv: int|null,
     *   irr_percent: float|null,
     *   first_year_savings: int|null,
     *   total_lifetime_savings: int|null,
     *   environmental_impact: array<string, mixed>|null,
     *   co2_offset_tons: float|null,
     *   trees_equivalent: int|null,
     *   cars_equivalent: float|null,
     *   sections_config: array<string, mixed>|null,
     *   custom_content: array<string, mixed>|null,
     *   valid_until: string|null,
     *   days_until_expiry: int,
     *   is_expired: bool,
     *   notes: string|null,
     *   created_by: int|null,
     *   creator?: UserResource,
     *   sent_at: string|null,
     *   accepted_at: string|null,
     *   rejected_at: string|null,
     *   rejection_reason: string|null,
     *   converted_quotation_id: int|null,
     *   converted_quotation?: QuotationResource,
     *   public_url: string,
     *   has_valid_public_token: bool,
     *   can_edit: bool,
     *   can_send: bool,
     *   can_accept: bool,
     *   can_reject: bool,
     *   can_convert: bool,
     *   created_at: string,
     *   updated_at: string
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'proposal_number' => $this->proposal_number,
            'status' => new StatusResource($this->status),
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
            'roof_polygon' => $this->roof_polygon,
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

            // Public Access
            'public_url' => $this->getPublicUrl(),
            'has_valid_public_token' => $this->hasValidPublicToken(),

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
