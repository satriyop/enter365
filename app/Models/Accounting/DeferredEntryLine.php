<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon|null $recognition_date
 * @property Carbon|null $posted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeferredEntryLine extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\DeferredEntryLineFactory> */
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
        'deferred_entry_id',
        'sequence',
        'recognition_date',
        'amount',
        'remaining_amount',
        'status',
        'journal_entry_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'recognition_date' => 'date',
            'amount' => 'integer',
            'remaining_amount' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * @return BelongsTo<DeferredEntry, $this>
     */
    public function deferredEntry(): BelongsTo
    {
        return $this->belongsTo(DeferredEntry::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
