<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciationLine extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AssetDepreciationLineFactory> */
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
        'fixed_asset_id',
        'sequence',
        'depreciation_date',
        'amount',
        'depreciated_value',
        'remaining_value',
        'status',
        'journal_entry_id',
        'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'depreciation_date' => 'date',
            'amount' => 'integer',
            'depreciated_value' => 'integer',
            'remaining_value' => 'integer',
            'posted_at' => 'datetime',
        ];
    }

    public function isPosted(): bool
    {
        return $this->status === self::STATUS_POSTED;
    }

    /**
     * @return BelongsTo<FixedAsset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
