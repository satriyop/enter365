<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AnalyticPlan extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AnalyticPlanFactory> */
    use HasFactory;

    public const APPLICABILITY_OPTIONAL = 'optional';

    public const APPLICABILITY_MANDATORY = 'mandatory';

    public const APPLICABILITY_UNAVAILABLE = 'unavailable';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'default_applicability' => self::APPLICABILITY_OPTIONAL,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'parent_id',
        'default_applicability',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function applicabilities(): array
    {
        return [
            self::APPLICABILITY_OPTIONAL,
            self::APPLICABILITY_MANDATORY,
            self::APPLICABILITY_UNAVAILABLE,
        ];
    }

    /**
     * @return BelongsTo<AnalyticPlan, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AnalyticPlan, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('code');
    }

    /**
     * @return HasMany<AnalyticAccount, $this>
     */
    public function analyticAccounts(): HasMany
    {
        return $this->hasMany(AnalyticAccount::class);
    }
}
