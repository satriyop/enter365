<?php

namespace App\Models\Accounting;

use App\Models\Contacts\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $start_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Loan extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\LoanFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_RUNNING = 'running';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
        'annual_interest_rate' => 0,
        'remaining_principal' => 0,
    ];

    protected $fillable = [
        'code',
        'name',
        'contact_id',
        'principal',
        'annual_interest_rate',
        'duration_months',
        'start_date',
        'liability_account_id',
        'interest_account_id',
        'bank_account_id',
        'journal_id',
        'status',
        'remaining_principal',
        'disbursement_journal_entry_id',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'integer',
            'annual_interest_rate' => 'decimal:4',
            'duration_months' => 'integer',
            'start_date' => 'date',
            'remaining_principal' => 'integer',
        ];
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
    public function liabilityAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'liability_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function interestAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'interest_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'bank_account_id');
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
    public function disbursementJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'disbursement_journal_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LoanLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(LoanLine::class)->orderBy('sequence');
    }
}
