<?php

namespace App\Models\Accounting;

use App\Exceptions\Domain\BusinessRuleException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property string $description
 * @property int $debit
 * @property int $credit
 * @property int|null $balance
 * @property string|null $currency_code
 * @property int|null $amount_currency
 * @property float|null $exchange_rate
 * @property-read \App\Models\Accounting\JournalEntry $journalEntry
 * @property-read \App\Models\Accounting\Account $account
 */
class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'description',
        'debit',
        'credit',
        'currency_code',
        'amount_currency',
        'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'debit' => 'integer',
            'credit' => 'integer',
            'amount_currency' => 'integer',
            'exchange_rate' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line): void {
            $debit = (int) $line->debit;
            $credit = (int) $line->credit;

            if ($debit < 0 || $credit < 0 || ($debit !== 0 && $credit !== 0)) {
                throw BusinessRuleException::operationNotAllowed(
                    'baris jurnal',
                    'Baris jurnal harus debit atau kredit (bukan keduanya), dan tidak boleh negatif.'
                );
            }
        });
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
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
