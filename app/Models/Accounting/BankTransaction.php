<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'integer',
            'credit' => 'integer',
            'balance' => 'integer',
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
        return $this->status === self::STATUS_RECONCILED;
    }

    /**
     * Match this transaction to a payment.
     */
    public function matchToPayment(Payment $payment): void
    {
        $this->update([
            'status' => self::STATUS_MATCHED,
            'matched_payment_id' => $payment->id,
        ]);
    }

    /**
     * Match this transaction to a journal entry line.
     */
    public function matchToJournalLine(JournalEntryLine $line): void
    {
        $this->update([
            'status' => self::STATUS_MATCHED,
            'matched_journal_line_id' => $line->id,
        ]);
    }

    /**
     * Mark as reconciled.
     */
    public function reconcile(?int $userId = null): void
    {
        $this->update([
            'status' => self::STATUS_RECONCILED,
            'reconciled_at' => now(),
            'reconciled_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Unmatch this transaction.
     */
    public function unmatch(): void
    {
        $this->update([
            'status' => self::STATUS_UNMATCHED,
            'matched_payment_id' => null,
            'matched_journal_line_id' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);
    }
}
