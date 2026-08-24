<?php

declare(strict_types=1);

namespace App\Models\Pos;

use App\Enums\Pos\PosSaleStatus;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosSale extends Model
{
    /** @use HasFactory<\Database\Factories\Pos\PosSaleFactory> */
    use HasFactory;

    protected $fillable = [
        'sale_number',
        'pos_session_id',
        'status',
        'subtotal_amount',
        'service_amount',
        'tax_amount',
        'dpp_amount',
        'ppn_amount',
        'payable_amount',
        'cash_received_amount',
        'change_amount',
        'journal_entry_id',
        'cogs_journal_entry_id',
        'sold_at',
        'voided_at',
        'voided_by',
        'void_reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => PosSaleStatus::class,
            'subtotal_amount' => 'integer',
            'service_amount' => 'integer',
            'tax_amount' => 'integer',
            'dpp_amount' => 'integer',
            'ppn_amount' => 'integer',
            'payable_amount' => 'integer',
            'cash_received_amount' => 'integer',
            'change_amount' => 'integer',
            'sold_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PosSession, $this>
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(PosSession::class, 'pos_session_id');
    }

    /**
     * @return HasMany<PosSaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PosSaleItem::class);
    }

    /**
     * @return HasMany<PosSaleTender, $this>
     */
    public function tenders(): HasMany
    {
        return $this->hasMany(PosSaleTender::class);
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function cogsJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'cogs_journal_entry_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isCompleted(): bool
    {
        return $this->status === PosSaleStatus::Completed;
    }
}
