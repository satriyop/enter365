<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Account extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_ASSET = 'asset';

    public const TYPE_LIABILITY = 'liability';

    public const TYPE_EQUITY = 'equity';

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_EXPENSE = 'expense';

    public const SUBTYPE_CURRENT_ASSET = 'current_asset';

    public const SUBTYPE_FIXED_ASSET = 'fixed_asset';

    public const SUBTYPE_CURRENT_LIABILITY = 'current_liability';

    public const SUBTYPE_LONG_TERM_LIABILITY = 'long_term_liability';

    public const SUBTYPE_EQUITY = 'equity';

    public const SUBTYPE_OPERATING_REVENUE = 'operating_revenue';

    public const SUBTYPE_OTHER_REVENUE = 'other_revenue';

    public const SUBTYPE_OPERATING_EXPENSE = 'operating_expense';

    public const SUBTYPE_OTHER_EXPENSE = 'other_expense';

    protected $fillable = [
        'code',
        'name',
        'type',
        'subtype',
        'description',
        'parent_id',
        'is_active',
        'is_system',
        'opening_balance',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'opening_balance' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<Account, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * Check if this is a debit-normal account (Assets, Expenses).
     */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, [self::TYPE_ASSET, self::TYPE_EXPENSE]);
    }

    /**
     * Check if this is a credit-normal account (Liabilities, Equity, Revenue).
     */
    public function isCreditNormal(): bool
    {
        return in_array($this->type, [self::TYPE_LIABILITY, self::TYPE_EQUITY, self::TYPE_REVENUE]);
    }

    /**
     * Get the current balance of this account.
     */
    public function getBalance(?string $asOfDate = null): int
    {
        $query = $this->journalEntryLines()
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true);
                if ($asOfDate) {
                    $q->where('entry_date', '<=', $asOfDate);
                }
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        $netMovement = $this->isDebitNormal()
            ? $totalDebit - $totalCredit
            : $totalCredit - $totalDebit;

        return $this->opening_balance + $netMovement;
    }

    /**
     * Get all account types.
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_ASSET,
            self::TYPE_LIABILITY,
            self::TYPE_EQUITY,
            self::TYPE_REVENUE,
            self::TYPE_EXPENSE,
        ];
    }
}
