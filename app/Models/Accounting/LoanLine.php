<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $due_date
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class LoanLine extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\LoanLineFactory> */
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_POSTED = 'posted';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected $fillable = [
        'loan_id',
        'sequence',
        'due_date',
        'principal_amount',
        'interest_amount',
        'payment_amount',
        'remaining_principal',
        'status',
        'journal_entry_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'due_date' => 'date',
            'principal_amount' => 'integer',
            'interest_amount' => 'integer',
            'payment_amount' => 'integer',
            'remaining_principal' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
