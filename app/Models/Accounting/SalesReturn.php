<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesReturn extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const REASON_DAMAGED = 'damaged';

    public const REASON_WRONG_ITEM = 'wrong_item';

    public const REASON_QUALITY_ISSUE = 'quality_issue';

    public const REASON_CUSTOMER_REQUEST = 'customer_request';

    public const REASON_OTHER = 'other';

    protected $fillable = [
        'return_number',
        'invoice_id',
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
        'credit_note_id',
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
        ];
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
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
     * @return HasMany<SalesReturnItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * Credit note (negative invoice).
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'credit_note_id');
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
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * Check if the return can be submitted.
     */
    public function canBeSubmitted(): bool
    {
        return $this->status === self::STATUS_DRAFT
            && $this->items()->exists();
    }

    /**
     * Check if the return can be approved.
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if the return can be rejected.
     */
    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    /**
     * Check if the return can be completed.
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the return can be cancelled.
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_SUBMITTED]);
    }

    /**
     * Calculate and update totals from items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = (int) $this->items()->sum('line_total');
        $this->tax_amount = (int) round($this->subtotal * ($this->tax_rate / 100));
        $this->total_amount = $this->subtotal + $this->tax_amount;
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
            self::REASON_CUSTOMER_REQUEST => 'Permintaan Pelanggan',
            self::REASON_OTHER => 'Lainnya',
        ];
    }

    /**
     * Generate the next return number.
     */
    public static function generateReturnNumber(): string
    {
        $prefix = 'SR-'.now()->format('Ym').'-';
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
}
