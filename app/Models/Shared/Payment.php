<?php

namespace App\Models\Shared;

use App\Enums\PphCategory;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntry;
use App\Models\Contacts\Contact;
use App\Models\User;
use App\Traits\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property \Carbon\Carbon $payment_date
 * @property \Carbon\Carbon|null $voided_at
 * @property \App\Enums\PphCategory|null $pph_category
 */
class Payment extends Model
{
    use Filterable, HasFactory, SoftDeletes;

    public const TYPE_RECEIVE = 'receive'; // Payment received from customer

    public const TYPE_SEND = 'send'; // Payment sent to supplier

    public const METHOD_CASH = 'cash';

    public const METHOD_TRANSFER = 'transfer';

    public const METHOD_CHECK = 'check';

    public const METHOD_GIRO = 'giro';

    protected $fillable = [
        'payment_number',
        'type',
        'contact_id',
        'payment_date',
        'amount',
        'currency',
        'exchange_rate',
        'base_currency_amount',
        'pph_category',
        'pph_rate',
        'pph_base_amount',
        'pph_amount',
        'pph_account_id',
        'payment_method',
        'reference',
        'notes',
        'cash_account_id',
        'journal_entry_id',
        'payable_type',
        'payable_id',
        'is_voided',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'integer',
            'exchange_rate' => 'decimal:4',
            'base_currency_amount' => 'integer',
            'pph_category' => PphCategory::class,
            'pph_rate' => 'decimal:2',
            'pph_base_amount' => 'integer',
            'pph_amount' => 'integer',
            'is_voided' => 'boolean',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the payable (Invoice or Bill).
     *
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /**
     * Get the payment allocations (multi-document support).
     *
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function pphAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'pph_account_id');
    }

    /**
     * Get the actual cash disbursement (total payment minus PPh withheld).
     */
    public function getCashDisbursement(): int
    {
        return $this->amount - ($this->pph_amount ?? 0);
    }

    /**
     * Check if this payment has PPh withholding.
     */
    public function hasPphWithholding(): bool
    {
        return ($this->pph_amount ?? 0) > 0;
    }

    /**
     * Check if this is a received payment.
     */
    public function isReceived(): bool
    {
        return $this->type === self::TYPE_RECEIVE;
    }

    /**
     * Check if this is a sent payment.
     */
    public function isSent(): bool
    {
        return $this->type === self::TYPE_SEND;
    }

    /**
     * Get the associated bank transaction.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne<BankTransaction, $this>
     */
    public function bankTransaction(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(BankTransaction::class, 'matched_payment_id');
    }

    /**
     * Generate the next payment number.
     */
    public static function generatePaymentNumber(string $type): string
    {
        // Delegates to DocumentNumbers: reading the sequence back out with
        // substr(-4) reset the counter at 10,000 payments in a month, and
        // descending text order never surfaced the five-digit number anyway.
        $prefix = ($type === self::TYPE_RECEIVE ? 'RCV' : 'PAY').'-'.now()->format('Ym').'-';

        return \App\Domain\Shared\DocumentNumbers::generate($prefix, 'payments', 'payment_number');
    }
}
