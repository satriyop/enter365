<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $currency_code
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $notes
 */
class AccountingLedger extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\AccountingLedgerFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'currency_code',
        'is_default',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Currency, $this>
     */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
