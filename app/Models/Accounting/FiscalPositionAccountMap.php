<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalPositionAccountMap extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\FiscalPositionAccountMapFactory> */
    use HasFactory;

    protected $fillable = [
        'fiscal_position_id',
        'source_account_id',
        'dest_account_id',
    ];

    /**
     * @return BelongsTo<FiscalPosition, $this>
     */
    public function fiscalPosition(): BelongsTo
    {
        return $this->belongsTo(FiscalPosition::class);
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'source_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function destAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'dest_account_id');
    }
}
