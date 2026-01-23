<?php

namespace App\Models\Purchasing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $grn_number
 * @property int $purchase_order_id
 * @property int $warehouse_id
 * @property Carbon $receipt_date
 * @property DocumentStatus $status
 * @property string|null $supplier_do_number
 * @property string|null $supplier_invoice_number
 * @property string|null $vehicle_number
 * @property string|null $driver_name
 * @property int|null $received_by
 * @property int|null $checked_by
 * @property string|null $notes
 * @property int $total_items
 * @property int $total_quantity_ordered
 * @property int $total_quantity_received
 * @property int $total_quantity_rejected
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class GoodsReceiptNote extends Model
{
    use HasFactory;

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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (GoodsReceiptNote $grn) {
            if (empty($grn->grn_number)) {
                $grn->grn_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'GRN-'.now()->format('Ym').'-',
                    'goods_receipt_notes',
                    'grn_number'
                );
            }
        });
    }

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
            'status' => DocumentStatus::class,
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
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if GRN is receiving.
     */
    public function isReceiving(): bool
    {
        return $this->status === DocumentStatus::Receiving;
    }

    /**
     * Check if GRN is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === DocumentStatus::Completed;
    }

    /**
     * Check if GRN is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::Cancelled;
    }

    /**
     * Check if GRN can be edited.
     */
    public function canEdit(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Receiving], true);
    }

    /**
     * Check if GRN can be deleted.
     */
    public function canDelete(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if GRN can be completed.
     */
    public function canComplete(): bool
    {
        if (! in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Receiving], true)) {
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
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Receiving], true);
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

    /**
     * Get the GRN state machine instance.
     */
    public function stateMachine(): \App\Domain\Purchasing\GoodsReceiptNotes\GoodsReceiptNoteStateMachine
    {
        return \App\Domain\Purchasing\GoodsReceiptNotes\GoodsReceiptNoteStateMachine::fromGoodsReceiptNote($this);
    }

    /**
     * Transition the GRN to a new status.
     */
    public function transitionTo(DocumentStatus $status, ?int $userId = null): self
    {
        $this->stateMachine()->transitionTo($status, ['user_id' => $userId]);

        return $this->refresh();
    }
}
