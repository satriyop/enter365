<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $account_reconciliation_id
 * @property int $journal_entry_line_id
 * @property int $amount
 */
class AccountReconciliationItem extends Model
{
    protected $fillable = [
        'account_reconciliation_id',
        'journal_entry_line_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AccountReconciliation, $this>
     */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(AccountReconciliation::class, 'account_reconciliation_id');
    }

    /**
     * @return BelongsTo<JournalEntryLine, $this>
     */
    public function journalEntryLine(): BelongsTo
    {
        return $this->belongsTo(JournalEntryLine::class);
    }
}
