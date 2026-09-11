<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property int $rounding
 * @property string $strategy
 * @property int|null $profit_account_id
 * @property int|null $loss_account_id
 * @property bool $is_active
 * @property string|null $notes
 */
class CashRounding extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\CashRoundingFactory> */
    use HasFactory;

    public const STRATEGY_HALF_UP = 'half_up';

    public const STRATEGY_UP = 'up';

    public const STRATEGY_DOWN = 'down';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'rounding' => 100,
        'strategy' => self::STRATEGY_HALF_UP,
        'is_active' => true,
    ];

    protected $fillable = [
        'name',
        'rounding',
        'strategy',
        'profit_account_id',
        'loss_account_id',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'rounding' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<string>
     */
    public static function strategies(): array
    {
        return [self::STRATEGY_HALF_UP, self::STRATEGY_UP, self::STRATEGY_DOWN];
    }

    /**
     * Round an integer amount (sen) using this rule.
     */
    public function roundAmount(int $amount): int
    {
        $unit = max(1, (int) $this->rounding);
        $remainder = $amount % $unit;
        if ($remainder === 0) {
            return $amount;
        }

        return match ($this->strategy) {
            self::STRATEGY_UP => $amount - $remainder + $unit,
            self::STRATEGY_DOWN => $amount - $remainder,
            default => $remainder >= intdiv($unit, 2)
                ? $amount - $remainder + $unit
                : $amount - $remainder,
        };
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function profitAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'profit_account_id');
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function lossAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'loss_account_id');
    }
}
