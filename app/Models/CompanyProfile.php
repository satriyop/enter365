<?php

namespace App\Models;

use App\Traits\Filterable;
use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CompanyProfile extends Model
{
    use Filterable, HasActiveStatus;

    protected $fillable = [
        'slug',
        'name',
        'tagline',
        'description',
        'logo_path',
        'cover_image_path',
        'founded_year',
        'employees_count',
        'primary_color',
        'secondary_color',
        'services',
        'portfolio',
        'certifications',
        'social_links',
        'email',
        'phone',
        'address',
        'website',
        'custom_domain',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'founded_year' => 'integer',
            'services' => 'array',
            'portfolio' => 'array',
            'certifications' => 'array',
            'social_links' => 'array',
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
