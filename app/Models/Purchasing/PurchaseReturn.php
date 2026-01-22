<?php

namespace App\Models\Purchasing;

use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $return_number
 * @property int|null $bill_id
 * @property int $contact_id
 * @property int $warehouse_id
 * @property Carbon $return_date
 * @property string|null $reason
 * @property string|null $notes
 * @property int $subtotal
 * @property string $tax_rate
 * @property int $tax_amount
 * @property int $total_amount
 * @property DocumentStatus $status
 * @property int|null $journal_entry_id
 * @property int|null $debit_note_id
 * @property int|null $created_by
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property Carbon|null $rejected_at
 * @property string|null $rejection_reason
 * @property int|null $completed_by
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class PurchaseReturn extends Model
{
    use Filterable, HasFactory, HasStatusHistory, SoftDeletes;

    public const REASON_DAMAGED = 'damaged';

    public const REASON_WRONG_ITEM = 'wrong_item';

    public const REASON_QUALITY_ISSUE = 'quality_issue';

    public const REASON_EXCESS_QUANTITY = 'excess_quantity';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'return_number',
        'bill_id',
        'contact_id',
        'warehouse_id',
        'return_date',
        'reason',
        'notes',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'status',
        'journal_entry_id',
        'debit_note_id',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'completed_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'return_date' => 'date',
            'subtotal' => 'integer',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'integer',
            'total_amount' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'completed_at' => 'datetime',
            'status' => DocumentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * @return BelongsTo<Warehouse, $this>
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * @return HasMany<PurchaseReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Debit note (negative bill).
     *
     * @return BelongsTo<Bill, $this>
     */
    public function debitNote(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'debit_note_id');
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
    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * Check if the return can be edited.
     */
    public function canBeEdited(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isEditable(): bool
    {
        return $this->canBeEdited();
    }

    public function isDeletable(): bool
    {
        return $this->isEditable();
    }

    /**
     * Check if the return can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === DocumentStatus::Draft
            && $this->items()->exists();
    }

    /**
     * Check if the return can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === DocumentStatus::Submitted;
    }

    /**
     * Check if the return can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->status === DocumentStatus::Submitted;
    }

    /**
     * Check if the return can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === DocumentStatus::Approved;
    }

    /**
     * Check if the return can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [DocumentStatus::Draft, DocumentStatus::Submitted], true);
    }

    /**
     * Calculate and update totals from items.
     */
    public function calculateTotals(?InvoiceCalculatorInterface $calculator = null): void
    {
        $calculator ??= app(InvoiceCalculatorInterface::class);

        $lineTotals = $this->items->pluck('line_total')->toArray();
        $taxRate = $this->tax_rate ?? config('accounting.tax.default_rate', 11.00);
        $totals = $calculator->calculate(
            $lineTotals,
            $taxRate,
            0, // No discount in purchase return
            $this->currency ?? 'IDR',
            $this->exchange_rate ?? 1
        );

        $this->subtotal = $totals->subtotal;
        $this->tax_amount = $totals->taxAmount;
        $this->total_amount = $totals->totalAmount;
    }

    /**
     * Get available reasons.
     *
     * @return array<string, string>
     */
    public static function getReasons(): array
    {
        return [
            self::REASON_DAMAGED => 'Barang Rusak',
            self::REASON_WRONG_ITEM => 'Barang Salah',
            self::REASON_QUALITY_ISSUE => 'Masalah Kualitas',
            self::REASON_EXCESS_QUANTITY => 'Kelebihan Kuantitas',
            self::REASON_OTHER => 'Lainnya',
        ];
    }

    /**
     * Generate the next return number.
     */
    public static function generateReturnNumber(): string
    {
        $prefix = 'PR-'.now()->format('Ym').'-';
        $lastReturn = static::query()
            ->where('return_number', 'like', $prefix.'%')
            ->orderBy('return_number', 'desc')
            ->first();

        if ($lastReturn) {
            $lastNumber = (int) substr($lastReturn->return_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    public function stateMachine(): \App\Domain\Purchasing\PurchaseReturns\PurchaseReturnStateMachine
    {
        return \App\Domain\Purchasing\PurchaseReturns\PurchaseReturnStateMachine::fromPurchaseReturn($this);
    }

    public function transitionTo(\App\Enums\DocumentStatus $status, ?int $userId = null, array $context = []): self
    {
        $context = array_merge(['user_id' => $userId], $context);
        $this->stateMachine()->transitionTo($status, $context);

        return $this->refresh();
    }
}
