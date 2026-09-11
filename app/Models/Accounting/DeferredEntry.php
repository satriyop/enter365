<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $kind
 * @property Carbon|null $start_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeferredEntry extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\DeferredEntryFactory> */
    use HasFactory;

    public const KIND_EXPENSE = 'expense';

    public const KIND_REVENUE = 'revenue';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'remaining_amount' => 0,
    ];

    protected $fillable = [
        'kind',
        'code',
        'name',
        'contact_id',
        'amount',
        'duration_months',
        'start_date',
        'deferred_account_id',
        'recognition_account_id',
        'counterpart_account_id',
        'journal_id',
        'status',
        'remaining_amount',
        'origination_journal_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'duration_months' => 'integer',
            'start_date' => 'date',
            'remaining_amount' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [self::KIND_EXPENSE, self::KIND_REVENUE];
    }

    /**
     * @return list<string>
     */
    public static function statuses(): array
    {
        return [self::STATUS_DRAFT, self::STATUS_RUNNING, self::STATUS_CLOSED];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isExpense(): bool
    {
        return $this->kind === self::KIND_EXPENSE;
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeOfKind(Builder $query, string $kind): Builder
    {
        return $query->where('kind', $kind);
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
    public function deferredAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deferred_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function recognitionAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'recognition_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function counterpartAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'counterpart_account_id');
    }

    /**
     * @return BelongsTo<Journal, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function originationJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'origination_journal_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DeferredEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DeferredEntryLine::class)->orderBy('sequence');
    }
}
