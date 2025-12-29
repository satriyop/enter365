<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\CompanyProfile
 */
class CompanyProfileResource extends JsonResource
{
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

            // Branding
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_url,
            'cover_image_path' => $this->cover_image_path,
            'cover_image_url' => $this->cover_image_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,

            // Content arrays
            'services' => $this->services ?? [],
            'portfolio' => $this->portfolio ?? [],
            'team' => $this->team ?? [],
            'certifications' => $this->certifications ?? [],
            'social_links' => $this->social_links ?? [],

            // Contact
            'email' => $this->email,
            'phone' => $this->phone,
            'address' => $this->address,
            'website' => $this->website,

            // Custom domain
            'custom_domain' => $this->custom_domain,
            'public_url' => $this->public_url,

            // Status
            'is_active' => $this->is_active,

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
