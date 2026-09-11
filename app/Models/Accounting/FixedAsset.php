<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FixedAsset extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\FixedAssetFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'method' => AssetModel::METHOD_LINEAR,
        'method_period' => AssetModel::PERIOD_MONTH,
        'status' => self::STATUS_DRAFT,
        'salvage_value' => 0,
        'accumulated_depreciation' => 0,
    ];

    protected $fillable = [
        'code',
        'name',
        'asset_model_id',
        'original_value',
        'salvage_value',
        'acquisition_date',
        'method',
        'method_number',
        'method_period',
        'method_progress_factor',
        'asset_account_id',
        'depreciation_account_id',
        'expense_account_id',
        'journal_id',
        'status',
        'accumulated_depreciation',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'original_value' => 'integer',
            'salvage_value' => 'integer',
            'acquisition_date' => 'date',
            'method_number' => 'integer',
            'method_progress_factor' => 'decimal:4',
            'accumulated_depreciation' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_RUNNING, self::STATUS_CLOSED];
    }

    public function bookValue(): int
    {
        return max(0, (int) $this->original_value - (int) $this->accumulated_depreciation);
    }

    public function depreciableValue(): int
    {
        return max(0, (int) $this->original_value - (int) $this->salvage_value);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    /**
     * @return BelongsTo<AssetModel, $this>
     */
    public function assetModel(): BelongsTo
    {
        return $this->belongsTo(AssetModel::class);
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssetDepreciationLine, $this>
     */
    public function depreciationLines(): HasMany
    {
        return $this->hasMany(AssetDepreciationLine::class)->orderBy('sequence');
    }
}
