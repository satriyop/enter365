<?php

namespace App\Models\Accounting;

use App\Traits\Filterable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journal extends Model
{
    use Filterable, HasFactory;

    protected static function newFactory(): Factory
    {
        return \Database\Factories\Accounting\JournalFactory::new();
    }

    public const TYPE_SALES = 'sales';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_BANK = 'bank';

    public const TYPE_CASH = 'cash';

    public const TYPE_MISCELLANEOUS = 'miscellaneous';

    protected $fillable = [
        'name',
        'type',
        'sequence_prefix',
        'default_account_id',
        'currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_SALES,
            self::TYPE_PURCHASE,
            self::TYPE_BANK,
            self::TYPE_CASH,
            self::TYPE_MISCELLANEOUS,
        ];
    }

    /**
     * Build the document-number prefix for a given date (includes year-month).
     */
    public function sequencePrefixForDate(CarbonInterface|\DateTimeInterface|string|null $date = null): string
    {
        $carbon = $date === null
            ? now()
            : (\Illuminate\Support\Carbon::parse($date));

        return rtrim($this->sequence_prefix, '-').'-'.$carbon->format('Ym').'-';
    }

    /**
     * Resolve the default journal for a journal-entry source type.
     */
    public static function defaultForSourceType(?string $sourceType): self
    {
        $type = match ($sourceType) {
            JournalEntry::SOURCE_INVOICE,
            JournalEntry::SOURCE_SALES_RETURN => self::TYPE_SALES,
            JournalEntry::SOURCE_BILL => self::TYPE_PURCHASE,
            JournalEntry::SOURCE_PAYMENT => self::TYPE_BANK,
            default => self::TYPE_MISCELLANEOUS,
        };

        $journal = static::query()
            ->where('type', $type)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($journal) {
            return $journal;
        }

        return static::query()
            ->where('type', self::TYPE_MISCELLANEOUS)
            ->orderBy('id')
            ->firstOrFail();
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function defaultAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'default_account_id');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }
}
