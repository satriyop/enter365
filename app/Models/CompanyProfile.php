<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'tagline',
        'description',
        'founded_year',
        'employees_count',
        'logo_path',
        'cover_image_path',
        'primary_color',
        'secondary_color',
        'services',
        'portfolio',
        'team',
        'certifications',
        'social_links',
        'email',
        'phone',
        'address',
        'website',
        'custom_domain',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'portfolio' => 'array',
            'team' => 'array',
            'certifications' => 'array',
            'social_links' => 'array',
            'founded_year' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the public URL for the profile.
     */
    public function getPublicUrlAttribute(): string
    {
        if ($this->custom_domain) {
            return "https://{$this->custom_domain}";
        }

        return url("/profile/{$this->slug}");
    }

    /**
     * Get the full logo URL.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Get the full cover image URL.
     */
    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_image_path) {
            return null;
        }

        return Storage::disk('public')->url($this->cover_image_path);
    }

    /**
     * Scope to find by slug.
     */
    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    /**
     * Scope to find by custom domain.
     */
    public function scopeByDomain($query, string $domain)
    {
        return $query->where('custom_domain', $domain);
    }

    /**
     * Scope for active profiles.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Find profile by slug or custom domain.
     */
    public static function findBySlugOrDomain(string $identifier): ?self
    {
        return self::active()
            ->where(function ($query) use ($identifier) {
                $query->where('slug', $identifier)
                    ->orWhere('custom_domain', $identifier);
            })
            ->first();
    }
}
