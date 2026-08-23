<?php

namespace App\Models\ElectricalPanel;

use App\Models\Manufacturing\Bom;
use App\Models\User;
use App\Traits\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class SpecValidationRuleSet extends Model
{
    use HasActiveStatus, HasFactory, SoftDeletes;

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
     * Panel metadata rows pointing at this rule set.
     *
     * @return HasMany<BomPanelMeta, $this>
     */
    public function panelMetas(): HasMany
    {
        return $this->hasMany(BomPanelMeta::class, 'spec_rule_set_id');
    }

    /**
     * BOMs that use this rule set.
     *
     * The link lives on electrical_panel_bom_meta, not on boms itself.
     *
     * @return HasManyThrough<Bom, BomPanelMeta, $this>
     */
    public function boms(): HasManyThrough
    {
        return $this->hasManyThrough(
            Bom::class,
            BomPanelMeta::class,
            'spec_rule_set_id',
            'id',
            'id',
            'bom_id'
        );
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
