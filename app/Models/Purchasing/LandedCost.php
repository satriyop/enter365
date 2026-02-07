<?php

declare(strict_types=1);

namespace App\Models\Purchasing;

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $landed_cost_number
 * @property int $goods_receipt_note_id
 * @property string $description
 * @property string $cost_type
 * @property int $total_amount
 * @property string $allocation_method
 * @property int $expense_account_id
 * @property int|null $journal_entry_id
 * @property string $status
 * @property int|null $created_by
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class LandedCost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'landed_cost_number',
        'goods_receipt_note_id',
        'description',
        'cost_type',
        'total_amount',
        'allocation_method',
        'expense_account_id',
        'journal_entry_id',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (LandedCost $lc) {
            if (empty($lc->landed_cost_number)) {
                $lc->landed_cost_number = \App\Domain\Shared\DocumentNumbers::generate(
                    'LC-'.now()->format('Ym').'-',
                    'landed_costs',
                    'landed_cost_number'
                );
            }
        });
    }

    /**
     * @return BelongsTo<GoodsReceiptNote, $this>
     */
    public function goodsReceiptNote(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptNote::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'expense_account_id');
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
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<LandedCostAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApplied(): bool
    {
        return $this->status === 'applied';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }
}
