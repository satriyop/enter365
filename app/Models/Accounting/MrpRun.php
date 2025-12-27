<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MrpRun extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_APPLIED = 'applied';

    protected $fillable = [
        'run_number',
        'name',
        'planning_horizon_start',
        'planning_horizon_end',
        'status',
        'parameters',
        'warehouse_id',
        'total_products_analyzed',
        'total_demands',
        'total_shortages',
        'total_purchase_suggestions',
        'total_work_order_suggestions',
        'total_subcontract_suggestions',
        'created_by',
        'completed_at',
        'applied_at',
        'applied_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'planning_horizon_start' => 'date',
            'planning_horizon_end' => 'date',
            'parameters' => 'array',
            'completed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MrpDemand, $this>
     */
    public function demands(): HasMany
    {
        return $this->hasMany(MrpDemand::class);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function suggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function purchaseSuggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class)
            ->where('suggestion_type', MrpSuggestion::TYPE_PURCHASE);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function workOrderSuggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class)
            ->where('suggestion_type', MrpSuggestion::TYPE_WORK_ORDER);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function subcontractSuggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class)
            ->where('suggestion_type', MrpSuggestion::TYPE_SUBCONTRACT);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function pendingSuggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class)
            ->where('status', MrpSuggestion::STATUS_PENDING);
    }

    /**
     * @return HasMany<MrpSuggestion, $this>
     */
    public function acceptedSuggestions(): HasMany
    {
        return $this->hasMany(MrpSuggestion::class)
            ->where('status', MrpSuggestion::STATUS_ACCEPTED);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function applier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    /**
     * Check if MRP run is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if MRP run is applied.
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * Check if MRP run can be deleted.
     */
    public function canBeDeleted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_COMPLETED]);
    }

    /**
     * Check if demand has changed since run was completed.
     */
    public function isOutdated(): bool
    {
        if (! $this->completed_at) {
            return false;
        }

        return WorkOrder::where('updated_at', '>', $this->completed_at)
            ->whereIn('status', [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])
            ->exists();
    }

    /**
     * Get outdated reason.
     */
    public function getOutdatedReason(): ?string
    {
        if (! $this->isOutdated()) {
            return null;
        }

        $count = WorkOrder::where('updated_at', '>', $this->completed_at)
            ->whereIn('status', [WorkOrder::STATUS_CONFIRMED, WorkOrder::STATUS_IN_PROGRESS])
            ->count();

        return "{$count} work orders diubah sejak run ini diselesaikan.";
    }

    /**
     * Update summary counts.
     */
    public function updateSummaryCounts(): void
    {
        $this->total_products_analyzed = $this->demands()
            ->distinct('product_id')
            ->count('product_id');

        $this->total_demands = $this->demands()->count();

        $this->total_shortages = $this->demands()
            ->where('quantity_short', '>', 0)
            ->count();

        $this->total_purchase_suggestions = $this->purchaseSuggestions()->count();
        $this->total_work_order_suggestions = $this->workOrderSuggestions()->count();
        $this->total_subcontract_suggestions = $this->subcontractSuggestions()->count();

        $this->save();
    }

    /**
     * Generate run number.
     */
    public static function generateRunNumber(): string
    {
        $prefix = 'MRP-'.now()->format('Ym').'-';
        $lastRun = static::query()
            ->where('run_number', 'like', $prefix.'%')
            ->orderByDesc('run_number')
            ->first();

        if ($lastRun) {
            $lastNumber = (int) substr($lastRun->run_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get available statuses.
     *
     * @return array<string, string>
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PROCESSING => 'Sedang Diproses',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_APPLIED => 'Diterapkan',
        ];
    }
}
