<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $profileId = $this->route('company_profile')?->id ?? $this->route('company_profile');

        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'string', 'max:100', Rule::unique('company_profiles', 'slug')->ignore($profileId), 'regex:/^[a-z0-9-]+$/'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'founded_year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],
            'employees_count' => ['nullable', 'string', 'max:20'],

            // Branding - restrict to safe image formats (no SVG)
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            'primary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],

            // JSON arrays
            'services' => ['nullable', 'array'],
            'services.*.title' => ['required_with:services', 'string', 'max:100'],
            'services.*.description' => ['nullable', 'string', 'max:500'],
            'services.*.icon' => ['nullable', 'string', 'max:50'],

            'portfolio' => ['nullable', 'array'],
            'portfolio.*.title' => ['required_with:portfolio', 'string', 'max:100'],
            'portfolio.*.description' => ['nullable', 'string', 'max:500'],
            'portfolio.*.year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],

            'team' => ['nullable', 'array'],
            'team.*.name' => ['required_with:team', 'string', 'max:100'],
            'team.*.role' => ['nullable', 'string', 'max:100'],
            'team.*.bio' => ['nullable', 'string', 'max:500'],

            'certifications' => ['nullable', 'array'],
            'certifications.*.name' => ['required_with:certifications', 'string', 'max:100'],
            'certifications.*.issuer' => ['nullable', 'string', 'max:100'],
            'certifications.*.year' => ['nullable', 'integer', 'min:1900', 'max:'.date('Y')],

            'social_links' => ['nullable', 'array'],
            'social_links.instagram' => ['nullable', 'url', 'max:255'],
            'social_links.linkedin' => ['nullable', 'url', 'max:255'],
            'social_links.facebook' => ['nullable', 'url', 'max:255'],
            'social_links.youtube' => ['nullable', 'url', 'max:255'],

            // Contact
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],

            // Custom domain
            'custom_domain' => ['nullable', 'string', 'max:100', Rule::unique('company_profiles', 'custom_domain')->ignore($profileId)],

            // Status
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'URL slug sudah digunakan.',
            'slug.regex' => 'URL slug hanya boleh mengandung huruf kecil, angka, dan tanda hubung.',
            'primary_color.regex' => 'Format warna tidak valid. Gunakan format hex (contoh: #FF7A3D).',
            'secondary_color.regex' => 'Format warna tidak valid. Gunakan format hex (contoh: #FF5100).',
            'logo.image' => 'Logo harus berupa file gambar.',
            'logo.max' => 'Ukuran logo maksimal 2MB.',
            'cover_image.image' => 'Cover image harus berupa file gambar.',
            'cover_image.max' => 'Ukuran cover image maksimal 5MB.',
            'custom_domain.unique' => 'Domain kustom sudah digunakan.',
        ];
    }
}
