<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequisition extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'requisition_number',
        'work_order_id',
        'warehouse_id',
        'status',
        'requested_date',
        'required_date',
        'total_items',
        'total_quantity',
        'notes',
        'requested_by',
        'approved_by',
        'approved_at',
        'issued_by',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'required_date' => 'date',
            'total_quantity' => 'decimal:4',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkOrder, $this>
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<MaterialRequisitionItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequisitionItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /**
     * Check if MR can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if MR can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_DRAFT && $this->items()->exists();
    }

    /**
     * Check if MR can be issued.
     */
    public function canBeIssued(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    /**
     * Check if MR can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_APPROVED]);
    }

    /**
     * Check if MR is fully issued.
     */
    public function isFullyIssued(): bool
    {
        return $this->items()->where('quantity_pending', '>', 0)->count() === 0;
    }

    /**
     * Update totals from items.
     */
    public function updateTotals(): void
    {
        $this->total_items = $this->items()->count();
        $this->total_quantity = (float) $this->items()->sum('quantity_requested');
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
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_ISSUED => 'Dikeluarkan',
            self::STATUS_PARTIAL => 'Sebagian',
            self::STATUS_CANCELLED => 'Dibatalkan',
        ];
    }

    /**
     * Generate requisition number.
     */
    public static function generateRequisitionNumber(): string
    {
        $prefix = 'MR-'.now()->format('Ym').'-';
        $lastMr = static::query()
            ->where('requisition_number', 'like', $prefix.'%')
            ->orderByDesc('requisition_number')
            ->first();

        if ($lastMr) {
            $lastNumber = (int) substr($lastMr->requisition_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
