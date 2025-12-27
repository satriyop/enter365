<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComponentBrandMapping extends Model
{
    use HasFactory;

    // Common electrical component brands
    public const BRAND_SCHNEIDER = 'schneider';

    public const BRAND_ABB = 'abb';

    public const BRAND_SIEMENS = 'siemens';

    public const BRAND_CHINT = 'chint';

    public const BRAND_LS = 'ls';

    public const BRAND_LEGRAND = 'legrand';

    public const BRAND_EATON = 'eaton';

    public const BRAND_HAGER = 'hager';

    public const BRAND_MITSUBISHI = 'mitsubishi';

    protected $fillable = [
        'component_standard_id',
        'brand',
        'product_id',
        'brand_sku',
        'is_preferred',
        'is_verified',
        'price_factor',
        'variant_specs',
        'notes',
        'verified_by',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'is_preferred' => 'boolean',
            'is_verified' => 'boolean',
            'price_factor' => 'decimal:2',
            'variant_specs' => 'array',
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ComponentStandard, $this>
     */
    public function componentStandard(): BelongsTo
    {
        return $this->belongsTo(ComponentStandard::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Verify this mapping.
     */
    public function verify(int $userId): void
    {
        $this->is_verified = true;
        $this->verified_by = $userId;
        $this->verified_at = now();
        $this->save();
    }

    /**
     * Set as preferred for this brand within the same component standard.
     */
    public function setAsPreferred(): void
    {
        // Unset other preferred mappings for same standard and brand
        self::query()
            ->where('component_standard_id', $this->component_standard_id)
            ->where('brand', $this->brand)
            ->where('id', '!=', $this->id)
            ->update(['is_preferred' => false]);

        $this->is_preferred = true;
        $this->save();
    }

    /**
     * Get brands list.
     *
     * @return array<string, string>
     */
    public static function getBrands(): array
    {
        return [
            self::BRAND_SCHNEIDER => 'Schneider Electric',
            self::BRAND_ABB => 'ABB',
            self::BRAND_SIEMENS => 'Siemens',
            self::BRAND_CHINT => 'CHINT',
            self::BRAND_LS => 'LS Electric',
            self::BRAND_LEGRAND => 'Legrand',
            self::BRAND_EATON => 'Eaton',
            self::BRAND_HAGER => 'Hager',
            self::BRAND_MITSUBISHI => 'Mitsubishi Electric',
        ];
    }

    /**
     * Scope for preferred mappings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>
     */
    public function scopePreferred($query)
    {
        return $query->where('is_preferred', true);
    }

    /**
     * Scope for verified mappings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope by brand.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentBrandMapping>
     */
    public function scopeForBrand($query, string $brand)
    {
        return $query->where('brand', $brand);
    }
}
