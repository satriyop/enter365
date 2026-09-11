<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Currency extends Model
{
    /** @use HasFactory<\Database\Factories\Accounting\CurrencyFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'decimal_places' => 0,
        'is_base_currency' => false,
        'is_active' => true,
    ];

    protected $fillable = [
        'code',
        'name',
        'symbol',
        'decimal_places',
        'is_base_currency',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'is_base_currency' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ExchangeRate, $this>
     */
    public function exchangeRates(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency', 'code');
    }

    /**
     * Get the base currency.
     */
    public static function base(): ?self
    {
        return static::where('is_base_currency', true)->first();
    }

    /**
     * Get all active currencies.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Currency>
     */
    public static function active(): \Illuminate\Database\Eloquent\Collection
    {
        return static::where('is_active', true)->get();
    }

    /**
     * Format an amount according to this currency's settings.
     */
    public function format(int|float $amount): string
    {
        $formatted = number_format($amount, $this->decimal_places, ',', '.');

        return $this->symbol.' '.$formatted;
    }
}
