<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

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
        'payment_method',
        'reference',
        'notes',
        'cash_account_id',
        'journal_entry_id',
        'payable_type',
        'payable_id',
        'is_voided',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'integer',
            'is_voided' => 'boolean',
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
        return $this->morphTo();
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
        $prefix = ($type === self::TYPE_RECEIVE ? 'RCV' : 'PAY').'-'.now()->format('Ym').'-';
        $lastPayment = static::query()
            ->where('payment_number', 'like', $prefix.'%')
            ->orderBy('payment_number', 'desc')
            ->first();

        if ($lastPayment) {
            $lastNumber = (int) substr($lastPayment->payment_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
