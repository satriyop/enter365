<?php

namespace App\Models\Sales;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Projects\Project;
use App\Models\Shared\Attachment;
use App\Models\Shared\Payment;
use App\Models\Shared\PaymentReminder;
use App\Models\Shared\RecurringTemplate;
use App\Models\Tax\NsfpRange;
use App\Models\User;
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
 * @property string|null $nsfp_number
 * @property int|null $nsfp_range_id
 * @property Carbon|null $nsfp_assigned_at
 * @property bool $is_nsfp_cancelled
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
 * @property int $credited_amount
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
    use CascadesSoftDeletes, Filterable, HasDocumentDiscount, HasFactory, HasStatusHistory, SoftDeletes;

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
        'nsfp_number',
        'nsfp_range_id',
        'nsfp_assigned_at',
        'is_nsfp_cancelled',
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
            'credited_amount' => 'integer',
            'last_reminder_at' => 'datetime',
            'nsfp_assigned_at' => 'datetime',
            'is_nsfp_cancelled' => 'boolean',
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
     * @return BelongsTo<NsfpRange, $this>
     */
    public function nsfpRange(): BelongsTo
    {
        return $this->belongsTo(NsfpRange::class, 'nsfp_range_id');
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
     * @return HasMany<WriteOff, $this>
     */
    public function writeOffs(): HasMany
    {
        return $this->hasMany(WriteOff::class);
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
        return max(0, $this->total_amount - $this->getSettledAmount());
    }

    /**
     * Cash collected plus credit-note relief.
     */
    public function getSettledAmount(): int
    {
        return (int) $this->paid_amount + (int) $this->credited_amount;
    }

    /**
     * Check if invoice is fully paid.
     */
    public function isFullyPaid(): bool
    {
        return $this->getOutstandingAmount() === 0;
    }

    /**
     * Check if invoice has an assigned NSFP number.
     */
    public function hasNsfp(): bool
    {
        return $this->nsfp_number !== null;
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
     * Check if early payment discount is still available (not past deadline).
     */
    public function isEarlyPaymentDiscountAvailable(): bool
    {
        if (! $this->hasEarlyPaymentDiscount()) {
            return false;
        }

        if ($this->early_discount_deadline) {
            return ! $this->early_discount_deadline->isPast();
        }

        // Fallback: calculate from invoice_date + days
        return ! $this->invoice_date->copy()->addDays($this->early_discount_days)->isPast();
    }

    /**
     * Set up early payment discount from contact defaults.
     */
    public function applyContactDiscountTerms(): void
    {
        if ($this->contact && $this->contact->early_discount_percent > 0) {
            $this->early_discount_percent = (string) $this->contact->early_discount_percent;
            $this->early_discount_days = $this->contact->early_discount_days;
            $this->early_discount_deadline = $this->invoice_date->copy()
                ->addDays($this->early_discount_days);
        }
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
