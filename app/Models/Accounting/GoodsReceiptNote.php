<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceiptNote extends Model
{
    use HasFactory;

    // Status constants
    public const STATUS_DRAFT = 'draft';

    public const STATUS_RECEIVING = 'receiving';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'grn_number',
        'purchase_order_id',
        'warehouse_id',
        'receipt_date',
        'status',
        'supplier_do_number',
        'supplier_invoice_number',
        'vehicle_number',
        'driver_name',
        'received_by',
        'checked_by',
        'notes',
        'total_items',
        'total_quantity_ordered',
        'total_quantity_received',
        'total_quantity_rejected',
        'completed_at',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'receipt_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'total_items' => 'integer',
            'total_quantity_ordered' => 'integer',
            'total_quantity_received' => 'integer',
            'total_quantity_rejected' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<GoodsReceiptNoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function checkedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Generate the next GRN number.
     */
    public static function generateGrnNumber(): string
    {
        $date = now()->format('Ymd');
        $prefix = "GRN-{$date}-";

        $lastGrn = static::where('grn_number', 'like', "{$prefix}%")
            ->orderByDesc('grn_number')
            ->first();

        if ($lastGrn && preg_match('/(\d{4})$/', $lastGrn->grn_number, $matches)) {
            $nextNum = (int) $matches[1] + 1;
        } else {
            $nextNum = 1;
        }

        return $prefix.str_pad($nextNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if GRN is in draft status.
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if GRN is receiving.
     */
    public function isReceiving(): bool
    {
        return $this->status === self::STATUS_RECEIVING;
    }

    /**
     * Check if GRN is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if GRN is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Check if GRN can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RECEIVING]);
    }

    /**
     * Check if GRN can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if GRN can be completed.
     */
    public function canComplete(): bool
    {
        if (! in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RECEIVING])) {
            return false;
        }

        // At least one item must have received quantity
        return $this->items()->where('quantity_received', '>', 0)->exists();
    }

    /**
     * Check if GRN can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_RECEIVING]);
    }

    /**
     * Update summary totals from items.
     */
    public function updateTotals(): void
    {
        $items = $this->items()->get();

        $this->total_items = $items->count();
        $this->total_quantity_ordered = $items->sum('quantity_ordered');
        $this->total_quantity_received = $items->sum('quantity_received');
        $this->total_quantity_rejected = $items->sum('quantity_rejected');
        $this->save();
    }

    /**
     * Get receiving progress percentage.
     */
    public function getReceivingProgress(): float
    {
        if (empty($this->total_quantity_ordered) || $this->total_quantity_ordered === 0) {
            return 0;
        }

        return round(($this->total_quantity_received / $this->total_quantity_ordered) * 100, 2);
    }

    /**
     * Scope for filtering by status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<GoodsReceiptNote>  $query
     * @return \Illuminate\Database\Eloquent\Builder<GoodsReceiptNote>
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for filtering by purchase order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<GoodsReceiptNote>  $query
     * @return \Illuminate\Database\Eloquent\Builder<GoodsReceiptNote>
     */
    public function scopeForPurchaseOrder($query, int $purchaseOrderId)
    {
        return $query->where('purchase_order_id', $purchaseOrderId);
    }
}
