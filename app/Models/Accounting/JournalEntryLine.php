<?php

namespace App\Models\Accounting;

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Contacts\Contact;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property int $account_id
 * @property int|null $partner_id
 * @property array<string, float|int>|null $analytic_distribution
 * @property list<int>|null $tax_tag_ids
 * @property string $description
 * @property int $debit
 * @property int $credit
 * @property int|null $balance
 * @property string|null $currency_code
 * @property int|null $amount_currency
 * @property float|null $exchange_rate
 * @property-read \App\Models\Accounting\JournalEntry $journalEntry
 * @property-read \App\Models\Accounting\Account $account
 * @property-read \App\Models\Contacts\Contact|null $partner
 */
class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'partner_id',
        'analytic_distribution',
        'tax_tag_ids',
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
            'analytic_distribution' => 'array',
            'tax_tag_ids' => 'array',
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

    /**
     * Odoo-style partner dimension (enter365 Contact: customer/vendor/both).
     *
     * @return BelongsTo<Contact, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'partner_id');
    }
}
