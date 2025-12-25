<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_number',
        'contact_id',
        'invoice_date',
        'due_date',
        'description',
        'reference',
        'subtotal',
        'tax_amount',
        'tax_rate',
        'discount_amount',
        'early_discount_percent',
        'early_discount_days',
        'early_discount_deadline',
        'early_discount_amount',
        'total_amount',
        'currency',
        'exchange_rate',
        'base_currency_total',
        'paid_amount',
        'status',
        'reminder_count',
        'last_reminder_at',
        'journal_entry_id',
        'receivable_account_id',
        'recurring_template_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'early_discount_deadline' => 'date',
            'subtotal' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate' => 'decimal:2',
            'discount_amount' => 'integer',
            'early_discount_percent' => 'decimal:2',
            'early_discount_amount' => 'integer',
            'total_amount' => 'integer',
            'exchange_rate' => 'decimal:4',
            'base_currency_total' => 'integer',
            'paid_amount' => 'integer',
            'last_reminder_at' => 'datetime',
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
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function receivableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'receivable_account_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<RecurringTemplate, $this>
     */
    public function recurringTemplate(): BelongsTo
    {
        return $this->belongsTo(RecurringTemplate::class);
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * @return MorphMany<PaymentReminder, $this>
     */
    public function reminders(): MorphMany
    {
        return $this->morphMany(PaymentReminder::class, 'remindable');
    }

    /**
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /**
     * Get the outstanding balance.
     */
    public function getOutstandingAmount(): int
    {
        return $this->total_amount - $this->paid_amount;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if early payment discount is available.
     */
    public function hasEarlyPaymentDiscount(): bool
    {
        return $this->early_discount_percent > 0
            && $this->early_discount_deadline
            && $this->early_discount_deadline->isFuture();
    }

    /**
     * Calculate early payment discount amount.
     */
    public function calculateEarlyDiscountAmount(): int
    {
        if (!$this->hasEarlyPaymentDiscount()) {
            return 0;
        }

        return (int) round($this->total_amount * ($this->early_discount_percent / 100));
    }

    /**
     * Get the discounted total if paid early.
     */
    public function getEarlyPaymentTotal(): int
    {
        return $this->total_amount - $this->calculateEarlyDiscountAmount();
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast()
            && $this->status !== self::STATUS_PAID
            && $this->status !== self::STATUS_CANCELLED
            && $this->status !== self::STATUS_DRAFT;
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now());
    }

    /**
     * Get days until due.
     */
    public function getDaysUntilDue(): int
    {
        if ($this->due_date->isPast()) {
            return 0;
        }

        return (int) now()->diffInDays($this->due_date);
    }

    /**
     * Calculate and update totals from items.
     */
    public function calculateTotals(): void
    {
        $this->subtotal = (int) $this->items()->sum('amount');
        $this->tax_amount = (int) round($this->subtotal * ($this->tax_rate / 100));
        $this->total_amount = $this->subtotal + $this->tax_amount - $this->discount_amount;

        // Calculate base currency total if multi-currency
        if ($this->currency !== 'IDR' && $this->exchange_rate > 0) {
            $this->base_currency_total = (int) round($this->total_amount * $this->exchange_rate);
        } else {
            $this->base_currency_total = $this->total_amount;
        }
    }

    /**
     * Set up early payment discount from contact defaults.
     */
    public function applyContactDiscountTerms(): void
    {
        if ($this->contact && $this->contact->early_discount_percent > 0) {
            $this->early_discount_percent = $this->contact->early_discount_percent;
            $this->early_discount_days = $this->contact->early_discount_days;
            $this->early_discount_deadline = $this->invoice_date->copy()
                ->addDays($this->early_discount_days);
        }
    }

    /**
     * Update payment status based on paid amount.
     */
    public function updatePaymentStatus(): void
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($this->paid_amount >= $this->total_amount) {
            $this->status = self::STATUS_PAID;
        } elseif ($this->paid_amount > 0) {
            $this->status = self::STATUS_PARTIAL;
        } elseif ($this->due_date < now() && $this->status !== self::STATUS_DRAFT) {
            $this->status = self::STATUS_OVERDUE;
        }
    }

    /**
     * Mark as overdue.
     */
    public function markAsOverdue(): bool
    {
        if ($this->status === self::STATUS_PAID || $this->status === self::STATUS_CANCELLED) {
            return false;
        }

        if ($this->status === self::STATUS_DRAFT) {
            return false;
        }

        $this->status = self::STATUS_OVERDUE;
        $this->save();

        return true;
    }

    /**
     * Generate the next invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym') . '-';
        $lastInvoice = static::query()
            ->where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
