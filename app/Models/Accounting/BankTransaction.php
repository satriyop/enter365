<?php

namespace App\Models\Accounting;

use App\Enums\BankTransactionStatus;
use App\Models\Shared\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Carbon\Carbon $transaction_date
 * @property \Carbon\Carbon|null $reconciled_at
 */
class BankTransaction extends Model
{
    use HasFactory;

    public const STATUS_UNMATCHED = 'unmatched';

    public const STATUS_MATCHED = 'matched';

    public const STATUS_RECONCILED = 'reconciled';

    protected $fillable = [
        'account_id',
        'transaction_date',
        'description',
        'reference',
        'debit',
        'credit',
        'balance',
        'status',
        'matched_payment_id',
        'matched_journal_line_id',
        'reconciled_at',
        'reconciled_by',
        'import_batch',
        'external_id',
        'session_id',
        'match_confidence',
        'match_rule',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'integer',
            'credit' => 'integer',
            'balance' => 'integer',
            'status' => BankTransactionStatus::class,
            'reconciled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function matchedPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'matched_payment_id');
    }

    /**
     * @return BelongsTo<JournalEntryLine, $this>
     */
    public function matchedJournalLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class, 'matched_journal_line_id');
    }

    /**
     * @return BelongsTo<BankReconciliationSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(BankReconciliationSession::class, 'session_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the net amount (debit - credit).
     */
    public function getNetAmount(): int
    {
        return $this->debit - $this->credit;
    }

    /**
     * Check if transaction is reconciled.
     */
    public function isReconciled(): bool
    {
        return $this->status === BankTransactionStatus::Reconciled;
    }

    /**
     * Check if transaction is matched (ready to reconcile).
     */
    public function isMatched(): bool
    {
        return $this->status === BankTransactionStatus::Matched;
    }

    /**
     * Check if transaction is still unmatched.
     */
    public function isUnmatched(): bool
    {
        return $this->status === BankTransactionStatus::Unmatched;
    }

    // Workflow transitions (match / unmatch / reconcile) live only on
    // BankReconciliationServiceInterface — do not add model mutators here.
}
