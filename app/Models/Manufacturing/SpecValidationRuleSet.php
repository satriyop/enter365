<?php

namespace App\Models\Manufacturing;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecValidationRuleSet extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SpecValidationRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(SpecValidationRule::class, 'rule_set_id')->orderBy('sort_order');
    }

    /**
     * @return HasMany<Bom, $this>
     */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class, 'spec_rule_set_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get rules for a specific category.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, SpecValidationRule>
     */
    public function getRulesForCategory(string $category): \Illuminate\Database\Eloquent\Collection
    {
        return $this->rules()->where('category', $category)->get();
    }

    /**
     * Check if this rule set has rules for a category.
     */
    public function hasRulesForCategory(string $category): bool
    {
        return $this->rules()->where('category', $category)->exists();
    }

    /**
     * Scope for active rule sets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<SpecValidationRuleSet>  $query
     * @return \Illuminate\Database\Eloquent\Builder<SpecValidationRuleSet>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the default rule set.
     */
    public static function getDefault(): ?self
    {
        return self::where('is_default', true)->where('is_active', true)->first();
    }

    /**
     * Set this rule set as default, unsetting others.
     */
    public function setAsDefault(): void
    {
        self::where('id', '!=', $this->id)->update(['is_default' => false]);
        $this->update(['is_default' => true]);
    }
}
