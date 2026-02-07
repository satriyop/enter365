<?php

declare(strict_types=1);

namespace Database\Factories\Tax;

use App\Models\Tax\NsfpRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NsfpRange>
 */
class NsfpRangeFactory extends Factory
{
    protected $model = NsfpRange::class;

    private static int $rangeCounter = 0;

    public function definition(): array
    {
        self::$rangeCounter++;
        $start = (self::$rangeCounter - 1) * 1000 + 1;
        $end = $start + 999;

        return [
            'transaction_code' => '010',
            'branch_code' => '000',
            'year_code' => now()->format('y'),
            'range_start' => $start,
            'range_end' => $end,
            'next_number' => $start,
            'used_count' => 0,
            'is_active' => true,
            'description' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Active range with plenty of numbers remaining.
     */
    public function active(): static
    {
        return $this->state(fn () => [
            'is_active' => true,
        ]);
    }

    /**
     * Fully exhausted range (next_number past range_end).
     */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_number' => $attributes['range_end'] + 1,
            'used_count' => $attributes['range_end'] - $attributes['range_start'] + 1,
            'is_active' => false,
        ]);
    }

    /**
     * Range with numbers below the low stock threshold.
     */
    public function lowStock(): static
    {
        $threshold = (int) config('accounting.nsfp.low_stock_threshold', 50);

        return $this->state(fn (array $attributes) => [
            'next_number' => $attributes['range_end'] - ($threshold - 1),
            'used_count' => $attributes['range_end'] - $attributes['range_start'] + 1 - $threshold,
        ]);
    }

    /**
     * Set a specific year code.
     */
    public function forYear(string $yearCode): static
    {
        return $this->state(fn () => [
            'year_code' => $yearCode,
        ]);
    }

    /**
     * Set a custom range.
     */
    public function withRange(int $start, int $end): static
    {
        return $this->state(fn () => [
            'range_start' => $start,
            'range_end' => $end,
            'next_number' => $start,
            'used_count' => 0,
        ]);
    }
}
