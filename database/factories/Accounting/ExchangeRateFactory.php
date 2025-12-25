<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    protected $model = ExchangeRate::class;

    public function definition(): array
    {
        return [
            'from_currency' => 'USD',
            'to_currency' => 'IDR',
            'rate' => $this->faker->randomFloat(4, 15000, 16500),
            'effective_date' => now(),
            'source' => $this->faker->randomElement(['manual', 'bank_indonesia', 'api']),
            'created_by' => null,
        ];
    }

    public function usdToIdr(float $rate = 15800): static
    {
        return $this->state(fn (array $attributes) => [
            'from_currency' => 'USD',
            'to_currency' => 'IDR',
            'rate' => $rate,
        ]);
    }

    public function eurToIdr(float $rate = 17200): static
    {
        return $this->state(fn (array $attributes) => [
            'from_currency' => 'EUR',
            'to_currency' => 'IDR',
            'rate' => $rate,
        ]);
    }

    public function sgdToIdr(float $rate = 11800): static
    {
        return $this->state(fn (array $attributes) => [
            'from_currency' => 'SGD',
            'to_currency' => 'IDR',
            'rate' => $rate,
        ]);
    }

    public function effectiveOn(\DateTimeInterface $date): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_date' => $date,
        ]);
    }

    public function fromBankIndonesia(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'bank_indonesia',
        ]);
    }
}
