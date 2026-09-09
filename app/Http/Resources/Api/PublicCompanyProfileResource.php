<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Marketing-safe company profile. No storage paths, team, or admin flags.
 *
 * @mixin \App\Models\CompanyProfile
 */
class PublicCompanyProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'founded_year' => $this->founded_year,
            'employees_count' => $this->employees_count,
            'logo_url' => $this->logo_url,
            'cover_image_url' => $this->cover_image_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'services' => $this->services ?? [],
            'portfolio' => $this->portfolio ?? [],
            'certifications' => $this->certifications ?? [],
            'social_links' => $this->social_links ?? [],
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'website' => $this->website,
            'public_url' => $this->public_url,
        ];
    }
}
