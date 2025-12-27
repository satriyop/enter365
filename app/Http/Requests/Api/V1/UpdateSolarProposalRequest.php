<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Accounting\SolarProposal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSolarProposalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Contact
            'contact_id' => ['sometimes', 'exists:contacts,id'],

            // Site Information
            'site_name' => ['nullable', 'string', 'max:255'],
            'site_address' => ['nullable', 'string', 'max:1000'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'roof_area_m2' => ['nullable', 'numeric', 'min:0'],
            'roof_type' => ['nullable', Rule::in([
                SolarProposal::ROOF_TYPE_FLAT,
                SolarProposal::ROOF_TYPE_SLOPED,
                SolarProposal::ROOF_TYPE_CARPORT,
                SolarProposal::ROOF_TYPE_GROUND,
            ])],
            'roof_orientation' => ['nullable', Rule::in([
                SolarProposal::ORIENTATION_NORTH,
                SolarProposal::ORIENTATION_SOUTH,
                SolarProposal::ORIENTATION_EAST,
                SolarProposal::ORIENTATION_WEST,
                SolarProposal::ORIENTATION_NORTHEAST,
                SolarProposal::ORIENTATION_NORTHWEST,
                SolarProposal::ORIENTATION_SOUTHEAST,
                SolarProposal::ORIENTATION_SOUTHWEST,
            ])],
            'roof_tilt_degrees' => ['nullable', 'numeric', 'min:0', 'max:90'],
            'shading_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Electricity Profile
            'monthly_consumption_kwh' => ['nullable', 'numeric', 'min:0'],
            'pln_tariff_category' => ['nullable', 'string', 'max:20'],
            'electricity_rate' => ['nullable', 'integer', 'min:0'],
            'tariff_escalation_percent' => ['nullable', 'numeric', 'min:0', 'max:50'],

            // Solar Data
            'peak_sun_hours' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'solar_irradiance' => ['nullable', 'numeric', 'min:0'],
            'performance_ratio' => ['nullable', 'numeric', 'min:0.5', 'max:1.0'],

            // System Selection
            'system_capacity_kwp' => ['nullable', 'numeric', 'min:0'],

            // Settings
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],

            // Proposal customization
            'sections_config' => ['nullable', 'array'],
            'custom_content' => ['nullable', 'array'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_id.exists' => 'Pelanggan tidak ditemukan.',
            'latitude.between' => 'Latitude harus antara -90 dan 90.',
            'longitude.between' => 'Longitude harus antara -180 dan 180.',
            'performance_ratio.min' => 'Performance ratio minimal 0.5.',
            'performance_ratio.max' => 'Performance ratio maksimal 1.0.',
        ];
    }
}
