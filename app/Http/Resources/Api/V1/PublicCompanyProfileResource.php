<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Marketing-only public company profile. Do not reuse {@see CompanyProfileResource}.
 *
 * @mixin \App\Models\CompanyProfile
 */
class PublicCompanyProfileResource extends JsonResource
{
    /**
     * @return array{
     *   id: int,
     *   name: string,
     *   slug: string,
     *   tagline: string|null,
     *   description: string|null,
     *   founded_year: int|null,
     *   employees_count: string|int|null,
     *   logo_url: string|null,
     *   cover_image_url: string|null,
     *   primary_color: string|null,
     *   secondary_color: string|null,
     *   services: array<mixed>,
     *   portfolio: array<mixed>,
     *   certifications: array<mixed>,
     *   social_links: array<mixed>,
     *   email: string|null,
     *   phone: string|null,
     *   address: string|null,
     *   website: string|null,
     *   public_url: string|null
     * }
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
