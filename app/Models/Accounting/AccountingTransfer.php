<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $transfer_number
 * @property Carbon|null $transfer_date
 * @property int $from_journal_id
 * @property int $to_journal_id
 * @property int $from_account_id
 * @property int $to_account_id
 * @property int $amount
 * @property string $status
 * @property string|null $memo
 * @property int|null $journal_entry_id
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AccountingTransfer extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AccountingTransferFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'amount' => 0,
    ];

    protected $fillable = [
        'transfer_number',
        'transfer_date',
        'from_journal_id',
        'to_journal_id',
        'from_account_id',
        'to_account_id',
        'amount',
        'status',
        'memo',
        'journal_entry_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transfer_date' => 'date',
            'amount' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_POSTED, self::STATUS_CANCELLED];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function fromJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'from_journal_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function toJournal(): BelongsTo
    {
        return $this->belongsTo(Journal::class, 'to_journal_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
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
}
