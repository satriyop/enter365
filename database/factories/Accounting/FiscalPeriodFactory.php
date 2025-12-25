<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPeriod>
 */
class FiscalPeriodFactory extends Factory
{
    protected $model = FiscalPeriod::class;

    public function definition(): array
    {
        $year = $this->faker->year();

        return [
            'name' => "Tahun Fiskal {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_closed' => false,
            'is_locked' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_closed' => true,
        ]);
    }

    public function current(): static
    {
        $year = now()->year;

        return $this->state(fn (array $attributes) => [
            'name' => "Tahun Fiskal {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_closed' => false,
            'is_locked' => false,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }
}
