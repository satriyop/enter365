<?php

namespace App\Models\Purchasing;

use App\Contracts\Purchasing\PurchaseOrderCalculatorInterface;
use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Shared\Attachment;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\Filterable;
use App\Traits\HasDocumentDiscount;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $po_number
 * @property int $revision
 * @property int $contact_id
 * @property Carbon $po_date
 * @property Carbon|null $expected_date
 * @property string|null $reference
 * @property string|null $subject
 * @property DocumentStatus $status
 * @property string $currency
 * @property string $exchange_rate
 * @property int $subtotal
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property int $discount_amount
 * @property string $tax_rate
 * @property int $tax_amount
 * @property int $total
 * @property int $base_currency_total
 * @property string|null $notes
 * @property string|null $terms_conditions
 * @property string|null $shipping_address
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancellation_reason
 * @property Carbon|null $first_received_at
 * @property Carbon|null $fully_received_at
 * @property int|null $converted_to_bill_id
 * @property Carbon|null $converted_at
 * @property int|null $original_po_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PurchaseOrder extends Model
{
    use Auditable, Filterable, HasDocumentDiscount, HasFactory, HasStatusHistory, SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (PurchaseOrder $po) {
            if (empty($po->po_number)) {
                $prefix = 'PO-'.now()->format('Ym').'-';
                $po->po_number = \App\Domain\Shared\DocumentNumbers::generate(
                    $prefix,
                    'purchase_orders',
                    'po_number'
                );
            }
        });
    }

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
            'total_amount' => 'integer',
            'base_currency_total' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'first_received_at' => 'datetime',
            'fully_received_at' => 'datetime',
            'converted_at' => 'datetime',
            'status' => DocumentStatus::class,
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
        return $query->where('status', DocumentStatus::Draft);
    }

    /**
     * Scope for submitted POs.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Submitted);
    }

    /**
     * Scope for approved POs.
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', DocumentStatus::Approved);
    }

    /**
     * Scope for outstanding POs (approved but not fully received).
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereIn('status', [DocumentStatus::Approved, DocumentStatus::Partial]);
    }

    /**
     * Scope for active POs (not cancelled/rejected).
     *
     * @param  Builder<PurchaseOrder>  $query
     * @return Builder<PurchaseOrder>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [DocumentStatus::Cancelled, DocumentStatus::Rejected]);
    }

    /**
     * Check if PO is editable.
     */
    public function isEditable(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Check if PO can be submitted.
     */
    public function canSubmit(): bool
    {
        $hasItems = isset($this->items_count)
            ? $this->items_count > 0
            : $this->items()->exists();

        return $this->status === DocumentStatus::Draft
            && $hasItems;
    }

    /**
     * Check if PO can be approved.
     */
    public function canApprove(): bool
    {
        return $this->status === DocumentStatus::Submitted;
    }

    /**
     * Check if PO can be rejected.
     */
    public function canReject(): bool
    {
        return $this->status === DocumentStatus::Submitted;
    }

    /**
     * Check if PO can be cancelled.
     */
    public function canCancel(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
        ], true);
    }

    /**
     * Check if PO can receive items.
     */
    public function canReceive(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ], true);
    }

    /**
     * Check if PO can be converted to bill.
     */
    public function canConvert(): bool
    {
        return in_array($this->status, [DocumentStatus::Partial, DocumentStatus::Received], true)
            && $this->converted_to_bill_id === null;
    }

    /**
     * Check if PO can be revised.
     */
    public function canRevise(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Cancelled,
        ], true);
    }

    public function isFullyReceived(): bool
    {
        if ($this->status === DocumentStatus::Received) {
            return true;
        }

        if (array_key_exists('total_quantity', $this->attributes) && array_key_exists('total_received', $this->attributes)) {
            $totalQty = (float) $this->attributes['total_quantity'];
            $receivedQty = (float) $this->attributes['total_received'];

            return $receivedQty >= $totalQty && $totalQty > 0;
        }

        foreach ($this->items as $item) {
            if ($item->quantity_received < $item->quantity) {
                return false;
            }
        }

        return $this->items->count() > 0;
    }

    public function hasReceivedItems(): bool
    {
        if (array_key_exists('total_received', $this->attributes)) {
            return (float) $this->attributes['total_received'] > 0;
        }

        return $this->items()->where('quantity_received', '>', 0)->exists();
    }

    public function getReceivingProgress(): float
    {
        $totalQty = array_key_exists('total_quantity', $this->attributes)
            ? (float) $this->attributes['total_quantity']
            : (float) $this->items()->sum('quantity');

        $receivedQty = array_key_exists('total_received', $this->attributes)
            ? (float) $this->attributes['total_received']
            : (float) $this->items()->sum('quantity_received');

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
        return $this->status->label();
    }

    /**
     * Calculate and update totals from items.
     *
     * @param  PurchaseOrderCalculatorInterface|null  $calculator  Optional calculator for unit testing
     */
    public function calculateTotals(?PurchaseOrderCalculatorInterface $calculator = null): void
    {
        $calculator ??= app(PurchaseOrderCalculatorInterface::class);

        $lineTotals = $this->items->pluck('line_total')->toArray();
        $totals = $calculator->calculate(
            $lineTotals,
            $this->tax_rate,
            $this->discount_type,
            $this->discount_value,
            $this->currency,
            $this->exchange_rate
        );

        $this->subtotal = $totals->subtotal;
        $this->discount_amount = $totals->discountAmount;
        $this->tax_amount = $totals->taxAmount;
        $this->total_amount = $totals->totalAmount;
        $this->base_currency_total = $totals->baseCurrencyTotal;
    }

    /**
     * Update receiving status based on items.
     *
     * @deprecated Use PurchaseOrderReceivingService::updateReceivingStatus() instead for proper state machine handling.
     */
    public function updateReceivingStatus(): void
    {
        trigger_error(
            'PurchaseOrder::updateReceivingStatus() is deprecated. Use PurchaseOrderReceivingService::updateReceivingStatus() instead.',
            E_USER_DEPRECATED
        );

        if (! $this->stateMachine()->canReceive()) {
            return;
        }

        $isFullyReceived = $this->isFullyReceived();
        $hasReceivedItems = $this->hasReceivedItems();

        // Use state machine for proper event dispatch
        if ($isFullyReceived && $this->status !== DocumentStatus::Received) {
            $this->transitionTo(DocumentStatus::Received, auth()->id());
        } elseif ($hasReceivedItems && $this->status === DocumentStatus::Approved) {
            $this->transitionTo(DocumentStatus::Partial, auth()->id());
        }
    }

    /**
     * Get the state machine instance for this purchase order.
     */
    public function stateMachine(): \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderStateMachine
    {
        return \App\Domain\Purchasing\PurchaseOrders\PurchaseOrderStateMachine::fromPurchaseOrder($this);
    }

    /**
     * Transition this purchase order to a new status.
     */
    public function transitionTo(DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $this->stateMachine()->transitionTo($status, array_merge(['user_id' => $userId], $context));

        return $this->refresh();
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
