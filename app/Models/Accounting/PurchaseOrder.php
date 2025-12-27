<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'po_number',
        'revision',
        'contact_id',
        'po_date',
        'expected_date',
        'reference',
        'subject',
        'status',
        'currency',
        'exchange_rate',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'total',
        'base_currency_total',
        'notes',
        'terms_conditions',
        'shipping_address',
        'submitted_at',
        'submitted_by',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'first_received_at',
        'fully_received_at',
        'converted_to_bill_id',
        'converted_at',
        'original_po_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'po_date' => 'date',
            'expected_date' => 'date',
            'revision' => 'integer',
            'exchange_rate' => 'decimal:4',
            'subtotal' => 'integer',
            'discount_value' => 'decimal:2',
            'discount_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'base_currency_total' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'first_received_at' => 'datetime',
            'fully_received_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return HasMany<PurchaseOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
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
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
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
    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function convertedBill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'converted_to_bill_id');
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function originalPo(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'original_po_id');
    }

    /**
     * @return HasMany<PurchaseOrder, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'original_po_id');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Scope for draft POs.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope for submitted POs.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    /**
     * Scope for approved POs.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope for outstanding POs (approved but not fully received).
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_APPROVED, self::STATUS_PARTIAL]);
    }

    /**
     * Scope for active POs (not cancelled/rejected).
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REJECTED]);
    }

    /**
     * Check if PO is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if PO can be submitted.
     */
    public function canSubmit(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->items()->exists();
    }

    /**
     * Check if PO can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if PO can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if PO can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_SUBMITTED,
            self::STATUS_APPROVED,
        ]);
    }

    /**
     * Check if PO can receive items.
     */
    public function canReceive(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_PARTIAL,
        ]);
    }

    /**
     * Check if PO can be converted to bill.
     */
    public function canConvert(): bool
    {
        return in_array($this->status, [self::STATUS_PARTIAL, self::STATUS_RECEIVED])
            && $this->converted_to_bill_id === null;
    }

    /**
     * Check if PO can be revised.
     */
    public function canRevise(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Check if PO is fully received.
     */
    public function isFullyReceived(): bool
    {
        if ($this->status === self::STATUS_RECEIVED) {
            return true;
        }

        foreach ($this->items as $item) {
            if ($item->quantity_received < $item->quantity) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if PO has any received items.
     */
    public function hasReceivedItems(): bool
    {
        return $this->items()->where('quantity_received', '>', 0)->exists();
    }

    /**
     * Get receiving progress percentage.
     */
    public function getReceivingProgress(): float
    {
        $totalQty = $this->items()->sum('quantity');
        $receivedQty = $this->items()->sum('quantity_received');

        if ($totalQty == 0) {
            return 0;
        }

        return round(($receivedQty / $totalQty) * 100, 2);
    }

    /**
     * Get the full PO number with revision suffix.
     */
    public function getFullNumber(): string
    {
        if ($this->revision > 0) {
            return "{$this->po_number}-R{$this->revision}";
        }

        return $this->po_number;
    }

    /**
     * Get status label in Indonesian.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Diajukan',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_PARTIAL => 'Diterima Sebagian',
            self::STATUS_RECEIVED => 'Diterima Lengkap',
            self::STATUS_CANCELLED => 'Dibatalkan',
            default => $this->status,
        };
    }

    /**
     * Calculate and update totals from items.
     */
    public function calculateTotals(): void
    {
        $subtotal = 0;
        $taxAmount = 0;

        foreach ($this->items as $item) {
            $subtotal += $item->line_total;
            $taxAmount += $item->tax_amount;
        }

        $this->subtotal = $subtotal;

        // Apply header-level discount
        if ($this->discount_type === 'percentage' && $this->discount_value > 0) {
            $this->discount_amount = (int) round($subtotal * ($this->discount_value / 100));
        } elseif ($this->discount_type === 'fixed') {
            $this->discount_amount = (int) $this->discount_value;
        } else {
            $this->discount_amount = 0;
        }

        // Calculate tax on (subtotal - discount)
        $taxableAmount = $subtotal - $this->discount_amount;
        $this->tax_amount = (int) round($taxableAmount * ($this->tax_rate / 100));

        // Total
        $this->total = $taxableAmount + $this->tax_amount;

        // Base currency total
        if ($this->currency !== 'IDR' && $this->exchange_rate > 0) {
            $this->base_currency_total = (int) round($this->total * $this->exchange_rate);
        } else {
            $this->base_currency_total = $this->total;
        }
    }

    /**
     * Update receiving status based on items.
     */
    public function updateReceivingStatus(): void
    {
        if (! in_array($this->status, [self::STATUS_APPROVED, self::STATUS_PARTIAL])) {
            return;
        }

        $isFullyReceived = $this->isFullyReceived();
        $hasReceivedItems = $this->hasReceivedItems();

        if ($isFullyReceived) {
            $this->status = self::STATUS_RECEIVED;
            $this->fully_received_at = now();
        } elseif ($hasReceivedItems) {
            $this->status = self::STATUS_PARTIAL;
            if (! $this->first_received_at) {
                $this->first_received_at = now();
            }
        }
    }

    /**
     * Generate the next PO number.
     */
    public static function generatePoNumber(): string
    {
        $prefix = 'PO-'.now()->format('Ym').'-';
        $lastPo = static::query()
            ->where('po_number', 'like', $prefix.'%')
            ->orderBy('po_number', 'desc')
            ->first();

        if ($lastPo) {
            $lastNumber = (int) substr($lastPo->po_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the next revision number for this PO.
     */
    public function getNextRevisionNumber(): int
    {
        $originalId = $this->original_po_id ?? $this->id;

        $maxRevision = static::query()
            ->where(function ($query) use ($originalId) {
                $query->where('id', $originalId)
                    ->orWhere('original_po_id', $originalId);
            })
            ->max('revision');

        return ($maxRevision ?? 0) + 1;
    }
}
