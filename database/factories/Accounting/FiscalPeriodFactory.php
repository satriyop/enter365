<?php

namespace Database\Factories\Accounting;

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
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
            'status' => FiscalPeriodStatus::Open,
            'is_closed' => false,
            'is_locked' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FiscalPeriodStatus::Closed,
            'is_closed' => true,
            'is_locked' => true,
            'closed_at' => now(),
        ]);
    }

    public function current(): static
    {
        $year = now()->year;

        return $this->state(fn (array $attributes) => [
            'name' => "Tahun Fiskal {$year}",
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'status' => FiscalPeriodStatus::Open,
            'is_closed' => false,
            'is_locked' => false,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FiscalPeriodStatus::Locked,
            'is_locked' => true,
        ]);
    }

    public function closing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FiscalPeriodStatus::Closing,
            'is_locked' => true,
        ]);
    }
}
