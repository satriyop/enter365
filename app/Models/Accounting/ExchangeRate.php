<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    protected $fillable = [
        'from_currency',
        'to_currency',
        'rate',
        'effective_date',
        'source',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the exchange rate for a specific currency pair and date.
     */
    public static function getRate(string $fromCurrency, string $toCurrency = 'IDR', ?\DateTimeInterface $date = null): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now();

        $rate = static::query()
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $date)
            ->orderByDesc('effective_date')
            ->first();

        return $rate?->rate;
    }

    /**
     * Convert an amount from one currency to another.
     */
    public static function convert(int|float $amount, string $fromCurrency, string $toCurrency = 'IDR', ?\DateTimeInterface $date = null): ?float
    {
        $rate = static::getRate($fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            return null;
        }

        return $amount * $rate;
    }

    /**
     * Get the latest rates for all currencies.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ExchangeRate>
     */
    public static function latestRates(string $toCurrency = 'IDR'): \Illuminate\Database\Eloquent\Collection
    {
        return static::query()
            ->where('to_currency', $toCurrency)
            ->whereIn('id', function ($query) use ($toCurrency) {
                $query->selectRaw('MAX(id)')
                    ->from('exchange_rates')
                    ->where('to_currency', $toCurrency)
                    ->groupBy('from_currency');
            })
            ->get();
    }
}
