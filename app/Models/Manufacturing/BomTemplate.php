<?php

namespace App\Models\Manufacturing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BomTemplate extends Model
{
    use HasFactory, SoftDeletes;

    // Template Categories
    public const CATEGORY_DISTRIBUTION = 'distribution';

    public const CATEGORY_MOTOR_CONTROL = 'motor_control';

    public const CATEGORY_SOLAR = 'solar';

    public const CATEGORY_LIGHTING = 'lighting';

    public const CATEGORY_OTHER = 'other';

    protected $fillable = [
        'code',
        'name',
        'description',
        'category',
        'thumbnail_path',
        'default_rule_set_id',
        'is_active',
        'usage_count',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<BomTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class, 'template_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomTemplateItem, $this>
     */
    public function materialItems(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class, 'template_id')
            ->where('type', BomTemplateItem::TYPE_MATERIAL)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomTemplateItem, $this>
     */
    public function laborItems(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class, 'template_id')
            ->where('type', BomTemplateItem::TYPE_LABOR)
            ->orderBy('sort_order');
    }

    /**
     * @return HasMany<BomTemplateItem, $this>
     */
    public function overheadItems(): HasMany
    {
        return $this->hasMany(BomTemplateItem::class, 'template_id')
            ->where('type', BomTemplateItem::TYPE_OVERHEAD)
            ->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<SpecValidationRuleSet, $this>
     */
    public function defaultRuleSet(): BelongsTo
    {
        return $this->belongsTo(SpecValidationRuleSet::class, 'default_rule_set_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Increment usage count.
     */
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    /**
     * Scope for active templates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BomTemplate>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BomTemplate>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope by category.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<BomTemplate>  $query
     * @return \Illuminate\Database\Eloquent\Builder<BomTemplate>
     */
    public function scopeInCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Get categories list.
     *
     * @return array<string, string>
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_DISTRIBUTION => 'Distribution Panel',
            self::CATEGORY_MOTOR_CONTROL => 'Motor Control Center',
            self::CATEGORY_SOLAR => 'Solar Panel',
            self::CATEGORY_LIGHTING => 'Lighting Panel',
            self::CATEGORY_OTHER => 'Other',
        ];
    }

    /**
     * Get summary statistics for this template.
     *
     * @return array{item_count: int, material_count: int, labor_count: int, overhead_count: int}
     */
    public function getSummary(): array
    {
        return [
            'item_count' => $this->items()->count(),
            'material_count' => $this->materialItems()->count(),
            'labor_count' => $this->laborItems()->count(),
            'overhead_count' => $this->overheadItems()->count(),
        ];
    }
}
