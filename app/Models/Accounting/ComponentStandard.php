<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ComponentStandard extends Model
{
    use HasFactory, SoftDeletes;

    // Component Categories
    public const CATEGORY_CIRCUIT_BREAKER = 'circuit_breaker';

    public const CATEGORY_CONTACTOR = 'contactor';

    public const CATEGORY_RELAY = 'relay';

    public const CATEGORY_CABLE = 'cable';

    public const CATEGORY_BUSBAR = 'busbar';

    public const CATEGORY_ENCLOSURE = 'enclosure';

    public const CATEGORY_TERMINAL = 'terminal';

    public const CATEGORY_METER = 'meter';

    public const CATEGORY_TRANSFORMER = 'transformer';

    public const CATEGORY_CAPACITOR = 'capacitor';

    // Subcategories for circuit breakers
    public const SUBCATEGORY_MCB = 'mcb';

    public const SUBCATEGORY_MCCB = 'mccb';

    public const SUBCATEGORY_ACB = 'acb';

    public const SUBCATEGORY_RCCB = 'rccb';

    public const SUBCATEGORY_RCBO = 'rcbo';

    protected $fillable = [
        'code',
        'name',
        'category',
        'subcategory',
        'specifications',
        'standard',
        'description',
        'unit',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'specifications' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ComponentBrandMapping, $this>
     */
    public function brandMappings(): HasMany
    {
        return $this->hasMany(ComponentBrandMapping::class);
    }

    /**
     * @return HasMany<BomItem, $this>
     */
    public function bomItems(): HasMany
    {
        return $this->hasMany(BomItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get mappings for a specific brand.
     *
     * @return HasMany<ComponentBrandMapping, $this>
     */
    public function getMappingsForBrand(string $brand): HasMany
    {
        return $this->brandMappings()->where('brand', $brand);
    }

    /**
     * Get preferred mapping for a brand.
     */
    public function getPreferredMapping(string $brand): ?ComponentBrandMapping
    {
        return $this->brandMappings()
            ->where('brand', $brand)
            ->where('is_preferred', true)
            ->first()
            ?? $this->brandMappings()
                ->where('brand', $brand)
                ->first();
    }

    /**
     * Get all available brands for this standard.
     *
     * @return array<string>
     */
    public function getAvailableBrands(): array
    {
        return $this->brandMappings()
            ->distinct()
            ->pluck('brand')
            ->toArray();
    }

    /**
     * Get specification value by key.
     */
    public function getSpec(string $key, mixed $default = null): mixed
    {
        return $this->specifications[$key] ?? $default;
    }

    /**
     * Scope for active standards.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentStandard>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentStandard>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentStandard>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentStandard>
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope by subcategory.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentStandard>  $query
     * @return \Illuminate\Database\Eloquent\Builder<ComponentStandard>
     */
    public function scopeInSubcategory($query, string $subcategory)
    {
        return $query->where('subcategory', $subcategory);
    }

    /**
     * Search by specifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<ComponentStandard>  $query
     * @param  array<string, mixed>  $specs
     * @return \Illuminate\Database\Eloquent\Builder<ComponentStandard>
     */
    public function scopeWithSpecs($query, array $specs)
    {
        foreach ($specs as $key => $value) {
            $query->whereJsonContains("specifications->{$key}", $value);
        }

        return $query;
    }

    /**
     * Get categories list.
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_CIRCUIT_BREAKER => 'Circuit Breaker',
            self::CATEGORY_CONTACTOR => 'Kontaktor',
            self::CATEGORY_RELAY => 'Relay',
            self::CATEGORY_CABLE => 'Kabel',
            self::CATEGORY_BUSBAR => 'Busbar',
            self::CATEGORY_ENCLOSURE => 'Panel Enclosure',
            self::CATEGORY_TERMINAL => 'Terminal Block',
            self::CATEGORY_METER => 'Meter',
            self::CATEGORY_TRANSFORMER => 'Trafo',
            self::CATEGORY_CAPACITOR => 'Kapasitor',
        ];
    }

    /**
     * Get subcategories for circuit breakers.
     *
     * @return array<string, string>
     */
    public static function getCircuitBreakerSubcategories(): array
    {
        return [
            self::SUBCATEGORY_MCB => 'MCB (Miniature)',
            self::SUBCATEGORY_MCCB => 'MCCB (Molded Case)',
            self::SUBCATEGORY_ACB => 'ACB (Air)',
            self::SUBCATEGORY_RCCB => 'RCCB (Residual Current)',
            self::SUBCATEGORY_RCBO => 'RCBO (Combined)',
        ];
    }
}
