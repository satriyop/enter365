<?php

namespace App\Models\Inventory;

use App\Enums\DocumentStatus;
use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockOpname extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    // Status constants mapped to DocumentStatus
    public const STATUS_DRAFT = 'draft';

    public const STATUS_COUNTING = 'counting';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'opname_number',
        'warehouse_id',
        'opname_date',
        'status',
        'name',
        'notes',
        'counted_by',
        'reviewed_by',
        'approved_by',
        'counting_started_at',
        'reviewed_at',
        'approved_at',
        'completed_at',
        'cancelled_at',
        'total_items',
        'total_counted',
        'total_variance_qty',
        'total_variance_value',
        'created_by',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (StockOpname $opname) {
            if (empty($opname->opname_number)) {
                $opname->opname_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'OPN-'.now()->format('Ym').'-',
                    'stock_opnames',
                    'opname_number'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'opname_date' => 'date',
            'counting_started_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_items' => 'integer',
            'total_counted' => 'integer',
            'total_variance_qty' => 'integer',
            'total_variance_value' => 'integer',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<StockOpnameItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StockOpnameItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function countedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if opname is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if opname is counting.
     */
    public function isCounting(): bool
    {
        return $this->status === DocumentStatus::Counting;
    }

    /**
     * Check if opname is reviewed.
     */
    public function isReviewed(): bool
    {
        return $this->status === DocumentStatus::Reviewed;
    }

    /**
     * Check if opname is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    /**
     * Check if opname is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === DocumentStatus::Completed;
    }

    /**
     * Check if opname is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::Cancelled;
    }

    /**
     * Check if opname can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Counting], true);
    }

    /**
     * Check if opname can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if opname can start counting.
     */
    public function canStartCounting(): bool
    {
        return $this->status === DocumentStatus::Draft && $this->items()->exists();
    }

    /**
     * Check if opname can submit for review.
     */
    public function canSubmitForReview(): bool
    {
        if ($this->status !== DocumentStatus::Counting) {
            return false;
        }

        // All items must be counted
        return $this->items()->whereNull('counted_quantity')->doesntExist();
    }

    /**
     * Check if opname can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === DocumentStatus::Reviewed;
    }

    /**
     * Check if opname can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === DocumentStatus::Reviewed;
    }

    /**
     * Check if opname can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Counting, DocumentStatus::Reviewed], true);
    }

    /**
     * Update summary totals from items.
     */
    public function updateTotals(): void
    {
        $items = $this->items()->get();

        $this->total_items = $items->count();
        $this->total_counted = $items->whereNotNull('counted_quantity')->count();
        $this->total_variance_qty = $items->sum('variance_quantity');
        $this->total_variance_value = $items->sum('variance_value');
        $this->save();
    }

    /**
     * Get counting progress percentage.
     */
    public function getCountingProgress(): float
    {
        if (empty($this->total_items) || $this->total_items === 0) {
            return 0;
        }

        return round((($this->total_counted ?? 0) / $this->total_items) * 100, 2);
    }

    /**
     * Scope for filtering by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StockOpname>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StockOpname>
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by warehouse.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<StockOpname>  $query
     * @return \Illuminate\Database\Eloquent\Builder<StockOpname>
     */
    public function scopeForWarehouse($query, int $warehouseId)
    {
        return $query->where('warehouse_id', $warehouseId);
    }

    /**
     * Get the stock opname state machine instance.
     */
    public function stateMachine(): \App\Domain\Inventory\StockOpname\StockOpnameStateMachine
    {
        return \App\Domain\Inventory\StockOpname\StockOpnameStateMachine::fromStockOpname($this);
    }

    /**
     * Transition the stock opname to a new status.
     */
    public function transitionTo(DocumentStatus|string $status, ?int $userId = null): self
    {
        $this->stateMachine()->transitionTo($status, ['user_id' => $userId]);

        return $this->refresh();
    }
}
