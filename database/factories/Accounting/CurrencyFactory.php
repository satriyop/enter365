<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$'],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'],
            ['code' => 'SGD', 'name' => 'Singapore Dollar', 'symbol' => 'S$'],
            ['code' => 'JPY', 'name' => 'Japanese Yen', 'symbol' => '¥'],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£'],
            ['code' => 'AUD', 'name' => 'Australian Dollar', 'symbol' => 'A$'],
        ];

        $currency = $this->faker->randomElement($currencies);

        return [
            'code' => $currency['code'],
            'name' => $currency['name'],
            'symbol' => $currency['symbol'],
            'decimal_places' => $currency['code'] === 'JPY' ? 0 : 2,
            'is_base_currency' => false,
            'is_active' => true,
        ];
    }

    public function idr(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'IDR',
            'name' => 'Indonesian Rupiah',
            'symbol' => 'Rp',
            'decimal_places' => 0,
            'is_base_currency' => true,
            'is_active' => true,
        ]);
    }

    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_base_currency' => false,
            'is_active' => true,
        ]);
    }

    public function baseCurrency(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_base_currency' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
