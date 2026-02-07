<?php

namespace App\Models\Sales;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\Shared\Payment;
use App\Models\User;
use App\Traits\CascadesSoftDeletes;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property DocumentStatus $status
 * @property \Carbon\Carbon $dp_date
 * @property int $amount
 * @property int $applied_amount
 * @property \Carbon\Carbon|null $refunded_at
 */
class DownPayment extends Model
{
    use CascadesSoftDeletes, Filterable, HasFactory, SoftDeletes;

    /** @var array<int, string> */
    protected array $cascadeSoftDeletes = ['applications'];

    public const TYPE_RECEIVABLE = 'receivable'; // From customer (uang muka penjualan)

    public const TYPE_PAYABLE = 'payable'; // To vendor (uang muka pembelian)

    public const STATUS_ACTIVE = 'active';

    public const STATUS_FULLY_APPLIED = 'fully_applied';

    public const STATUS_REFUNDED = 'refunded';

    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_METHODS = [
        'bank_transfer',
        'cash',
        'check',
        'giro',
        'credit_card',
    ];

    protected $fillable = [
        'dp_number',
        'type',
        'contact_id',
        'dp_date',
        'amount',
        'applied_amount',
        'payment_method',
        'cash_account_id',
        'reference',
        'description',
        'notes',
        'status',
        'journal_entry_id',
        'refund_payment_id',
        'refunded_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'dp_date' => 'date',
            'amount' => 'integer',
            'applied_amount' => 'integer',
            'refunded_at' => 'datetime',
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
     * @return BelongsTo<Account, $this>
     */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function refundPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'refund_payment_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DownPaymentApplication, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(DownPaymentApplication::class);
    }

    /**
     * Remaining amount accessor — always computes from current PHP attributes
     * so it stays correct even on dirty (unsaved) models.
     * The DB virtual column (storedAs) is kept for raw query sorting/filtering.
     *
     * @return Attribute<int, never>
     */
    protected function remainingAmount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->amount - $this->applied_amount,
        );
    }

    /**
     * Check if DP is fully applied.
     */
    public function isFullyApplied(): bool
    {
        return $this->applied_amount >= $this->amount;
    }

    /**
     * Check if DP can be applied to documents.
     */
    public function canBeApplied(): bool
    {
        return $this->status === DocumentStatus::Active && $this->remaining_amount > 0;
    }

    /**
     * Check if DP can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === DocumentStatus::Active && $this->remaining_amount > 0;
    }

    /**
     * Check if DP is for receivable (from customer).
     */
    public function isReceivable(): bool
    {
        return $this->type === self::TYPE_RECEIVABLE;
    }

    /**
     * Check if DP is for payable (to vendor).
     */
    public function isPayable(): bool
    {
        return $this->type === self::TYPE_PAYABLE;
    }

    /**
     * Update status based on applied amount.
     */
    public function updateStatus(): void
    {
        if ($this->status === DocumentStatus::Refunded || $this->status === DocumentStatus::Cancelled) {
            return;
        }

        if ($this->isFullyApplied()) {
            $this->status = DocumentStatus::FullyApplied;
        } else {
            $this->status = DocumentStatus::Active;
        }
    }

    /**
     * Get the DP account based on type.
     * Receivable: Uang Muka Penjualan (liability - we owe customer)
     * Payable: Uang Muka Pembelian (asset - vendor owes us)
     */
    public function getDpAccountCode(): string
    {
        return $this->isReceivable()
            ? config('accounting.default_accounts.dp_receivable', '2-1700')
            : config('accounting.default_accounts.dp_payable', '1-1700');
    }

    /**
     * Generate the next DP number.
     */
    public static function generateDpNumber(string $type): string
    {
        $prefix = $type === self::TYPE_RECEIVABLE ? 'DPR-' : 'DPP-';
        $prefix .= now()->format('Ym').'-';

        $lastDp = static::query()
            ->where('dp_number', 'like', $prefix.'%')
            ->orderBy('dp_number', 'desc')
            ->first();

        if ($lastDp) {
            $lastNumber = (int) substr($lastDp->dp_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
