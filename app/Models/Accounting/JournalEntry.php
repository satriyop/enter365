<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntry extends Model
{
    use HasFactory, SoftDeletes;

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_INVOICE = 'invoice';

    public const SOURCE_BILL = 'bill';

    public const SOURCE_PAYMENT = 'payment';

    public const SOURCE_CLOSING = 'closing';

    public const SOURCE_OPENING = 'opening';

    protected $fillable = [
        'entry_number',
        'entry_date',
        'description',
        'reference',
        'source_type',
        'source_id',
        'fiscal_period_id',
        'is_posted',
        'is_reversed',
        'reversed_by_id',
        'reversal_of_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_posted' => 'boolean',
            'is_reversed' => 'boolean',
        ];
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    /**
     * @return BelongsTo<FiscalPeriod, $this>
     */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversed_by_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'reversal_of_id');
    }

    /**
     * Get the source document (invoice, bill, payment, etc.).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id');
    }

    /**
     * Get total debits.
     */
    public function getTotalDebit(): int
    {
        return (int) $this->lines()->sum('debit');
    }

    /**
     * Get total credits.
     */
    public function getTotalCredit(): int
    {
        return (int) $this->lines()->sum('credit');
    }

    /**
     * Check if the entry is balanced (debits = credits).
     */
    public function isBalanced(): bool
    {
        return $this->getTotalDebit() === $this->getTotalCredit();
    }

    /**
     * Generate the next entry number.
     */
    public static function generateEntryNumber(): string
    {
        $prefix = 'JE-'.now()->format('Ym').'-';
        $lastEntry = static::query()
            ->where('entry_number', 'like', $prefix.'%')
            ->orderBy('entry_number', 'desc')
            ->first();

        if ($lastEntry) {
            $lastNumber = (int) substr($lastEntry->entry_number, -4);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
