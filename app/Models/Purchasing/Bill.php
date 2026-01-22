<?php

namespace App\Models\Purchasing;

use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Shared\Attachment;
use App\Models\Shared\Payment;
use App\Models\Shared\PaymentReminder;
use App\Models\Shared\RecurringTemplate;
use App\Models\User;
use App\Traits\Filterable;
use App\Traits\HasDocumentDiscount;
use App\Traits\HasStatusHistory;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $bill_number
 * @property string|null $vendor_invoice_number
 * @property int $contact_id
 * @property Carbon $bill_date
 * @property Carbon $due_date
 * @property Carbon|null $early_discount_deadline
 * @property string|null $description
 * @property string|null $reference
 * @property int $subtotal
 * @property int $tax_amount
 * @property string $tax_rate
 * @property int $discount_amount
 * @property string|null $early_discount_percent
 * @property int|null $early_discount_days
 * @property int|null $early_discount_amount
 * @property int $total_amount
 * @property string $currency
 * @property string $exchange_rate
 * @property int $base_currency_total
 * @property int $paid_amount
 * @property DocumentStatus $status
 * @property int $reminder_count
 * @property Carbon|null $last_reminder_at
 * @property int|null $journal_entry_id
 * @property int|null $payable_account_id
 * @property int|null $recurring_template_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Bill extends Model
{
    use Filterable, HasDocumentDiscount, HasFactory, HasStatusHistory, SoftDeletes;

    protected $fillable = [
        'bill_number',
        'vendor_invoice_number',
        'contact_id',
        'bill_date',
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
        'payable_account_id',
        'recurring_template_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'bill_date' => 'date',
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
     * @return HasMany<BillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class);
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
    public function payableAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'payable_account_id');
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
     * Check if bill is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->paid_amount >= $this->total_amount;
    }

    /**
     * Check if bill is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->due_date->isPast()
            && $this->status !== DocumentStatus::Paid
            && $this->status !== DocumentStatus::Cancelled
            && $this->status !== DocumentStatus::Draft;
    }

    /**
     * Get days overdue.
     */
    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
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
    public function calculateTotals(?InvoiceCalculatorInterface $calculator = null): void
    {
        $calculator ??= app(InvoiceCalculatorInterface::class);

        $lineTotals = $this->items->pluck('line_total')->toArray();
        $totals = $calculator->calculate(
            $lineTotals,
            $this->tax_rate,
            $this->discount_amount,
            $this->currency,
            $this->exchange_rate
        );

        $this->subtotal = $totals->subtotal;
        $this->tax_amount = $totals->taxAmount;
        $this->total_amount = $totals->totalAmount;

        // Calculate base currency total if multi-currency
        if ($this->currency !== 'IDR' && $this->exchange_rate > 0) {
            $this->base_currency_total = (int) round($this->total_amount * $this->exchange_rate);
        } else {
            $this->base_currency_total = $this->total_amount;
        }
    }

    /**
     * Update payment status based on paid amount.
     *
     * @deprecated Use BillService::updatePaymentStatus() instead for proper state machine handling.
     */
    public function updatePaymentStatus(): void
    {
        trigger_error(
            'Bill::updatePaymentStatus() is deprecated. Use BillService::updatePaymentStatus() instead.',
            E_USER_DEPRECATED
        );

        if ($this->status === DocumentStatus::Cancelled) {
            return;
        }

        // Determine target status
        $targetStatus = null;
        if ($this->paid_amount >= $this->total_amount) {
            $targetStatus = DocumentStatus::Paid;
        } elseif ($this->paid_amount > 0) {
            $targetStatus = DocumentStatus::Partial;
        } elseif ($this->due_date < now() && $this->status !== DocumentStatus::Draft) {
            $targetStatus = DocumentStatus::Overdue;
        }

        // Use state machine if possible
        if ($targetStatus && $this->stateMachine()->canTransitionTo($targetStatus)) {
            $this->transitionTo($targetStatus);
        }
    }

    /**
     * Mark as overdue.
     *
     * @deprecated Use BillService::markAsOverdue() instead for proper state machine handling.
     */
    public function markAsOverdue(): bool
    {
        trigger_error(
            'Bill::markAsOverdue() is deprecated. Use BillService::markAsOverdue() instead.',
            E_USER_DEPRECATED
        );

        if ($this->status === DocumentStatus::Paid || $this->status === DocumentStatus::Cancelled) {
            return false;
        }

        if ($this->status === DocumentStatus::Draft) {
            return false;
        }

        // Use state machine
        if ($this->stateMachine()->canMarkAsOverdue()) {
            $this->transitionTo(DocumentStatus::Overdue);

            return true;
        }

        return false;
    }

    /**
     * Generate the next bill number.
     */
    public static function generateBillNumber(): string
    {
        $prefix = 'BILL-'.now()->format('Ym').'-';
        $lastBill = static::query()
            ->where('bill_number', 'like', $prefix.'%')
            ->orderBy('bill_number', 'desc')
            ->first();

        if ($lastBill) {
            $lastNumber = (int) substr($lastBill->bill_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get the bill state machine instance.
     */
    public function stateMachine(): \App\Domain\Purchasing\Bills\BillStateMachine
    {
        return \App\Domain\Purchasing\Bills\BillStateMachine::fromBill($this);
    }

    /**
     * Transition the bill to a new status.
     */
    public function transitionTo(DocumentStatus $status, ?int $userId = null): self
    {
        $this->stateMachine()->transitionTo($status, ['user_id' => $userId]);

        return $this->refresh();
    }
}
