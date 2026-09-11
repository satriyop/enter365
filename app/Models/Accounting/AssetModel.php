<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetModel extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AssetModelFactory> */
    use HasFactory;

    public const METHOD_LINEAR = 'linear';

    public const METHOD_DEGRESSIVE = 'degressive';

    public const PERIOD_MONTH = 'month';

    public const PERIOD_YEAR = 'year';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'method' => self::METHOD_LINEAR,
        'method_period' => self::PERIOD_MONTH,
        'salvage_value_percent' => 0,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'method',
        'method_number',
        'method_period',
        'method_progress_factor',
        'salvage_value_percent',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
        'journal_id',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'method_number' => 'integer',
            'method_progress_factor' => 'decimal:4',
            'salvage_value_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function methods(): array
    {
        return [self::METHOD_LINEAR, self::METHOD_DEGRESSIVE];
    }

    /**
     * @return list<string>
     */
    public static function periods(): array
    {
        return [self::PERIOD_MONTH, self::PERIOD_YEAR];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'asset_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function depreciationAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'depreciation_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return HasMany<FixedAsset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(FixedAsset::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
