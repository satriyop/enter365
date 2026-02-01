<?php

namespace App\Models\Sales;

use App\Contracts\Sales\InvoiceCalculatorInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Projects\Project;
use App\Models\Shared\Attachment;
use App\Models\Shared\Payment;
use App\Models\Shared\PaymentReminder;
use App\Models\Shared\RecurringTemplate;
use App\Models\User;
use App\Traits\Auditable;
use App\Traits\CascadesSoftDeletes;
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
 * @property string $invoice_number
 * @property int $contact_id
 * @property Carbon $invoice_date
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
 * @property int|null $receivable_account_id
 * @property int|null $project_id
 * @property int|null $recurring_template_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
class Invoice extends Model
{
    use Auditable, CascadesSoftDeletes, Filterable, HasDocumentDiscount, HasFactory, HasStatusHistory, SoftDeletes;

    /** @var array<int, string> */
    protected array $cascadeSoftDeletes = ['payments'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Invoice $invoice) {
            if (empty($invoice->invoice_number)) {
                $prefix = 'INV-'.now()->format('Ym').'-';
                $invoice->invoice_number = \App\Domain\Shared\DocumentNumbers::generate(
                    $prefix,
                    'invoices',
                    'invoice_number'
                );
            }
        });
    }

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
        'project_id',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
     * Check if invoice is overdue.
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
     * Check if invoice has early payment discount configured.
     */
    public function hasEarlyPaymentDiscount(): bool
    {
        return $this->early_discount_percent !== null
            && $this->early_discount_percent > 0
            && $this->early_discount_days !== null
            && $this->early_discount_days > 0;
    }

    /**
     * Calculate and update totals from items.
     *
     * @param  InvoiceCalculatorInterface|null  $calculator  Optional calculator for unit testing
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
     *
     * @deprecated Use InvoiceService::updatePaymentStatus() instead for proper state machine handling.
     */
    public function updatePaymentStatus(): void
    {
        trigger_error(
            'Invoice::updatePaymentStatus() is deprecated. Use InvoiceService::updatePaymentStatus() instead.',
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
     * @deprecated Use InvoiceService::markAsOverdue() instead for proper state machine handling.
     */
    public function markAsOverdue(): bool
    {
        trigger_error(
            'Invoice::markAsOverdue() is deprecated. Use InvoiceService::markAsOverdue() instead.',
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
     * Get the invoice state machine instance.
     */
    public function stateMachine(): \App\Domain\Sales\Invoices\InvoiceStateMachine
    {
        return \App\Domain\Sales\Invoices\InvoiceStateMachine::fromInvoice($this);
    }

    /**
     * Transition the invoice to a new status.
     */
    public function transitionTo(DocumentStatus $status, ?int $userId = null): self
    {
        $this->stateMachine()->transitionTo($status, ['user_id' => $userId]);

        return $this->refresh();
    }
}
