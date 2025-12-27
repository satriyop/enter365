<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DownPayment extends Model
{
    use HasFactory, SoftDeletes;

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
     * Get remaining amount that can be applied.
     */
    public function getRemainingAmount(): int
    {
        return $this->amount - $this->applied_amount;
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
        return $this->status === self::STATUS_ACTIVE && $this->getRemainingAmount() > 0;
    }

    /**
     * Check if DP can be refunded.
     */
    public function canBeRefunded(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->getRemainingAmount() > 0;
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
        if ($this->status === self::STATUS_REFUNDED || $this->status === self::STATUS_CANCELLED) {
            return;
        }

        if ($this->isFullyApplied()) {
            $this->status = self::STATUS_FULLY_APPLIED;
        } else {
            $this->status = self::STATUS_ACTIVE;
        }
    }

    /**
     * Get the DP account based on type.
     * Receivable: Uang Muka Penjualan (liability - we owe customer)
     * Payable: Uang Muka Pembelian (asset - vendor owes us)
     */
    public function getDpAccountCode(): string
    {
        return $this->isReceivable() ? '2130' : '1140'; // Default codes, can be configured
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
