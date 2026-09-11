<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\CashRounding;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRounding>
 */
class CashRoundingFactory extends Factory
{
    protected $model = CashRounding::class;

    public function definition(): array
    {
        return [
            'name' => 'Nearest '.$this->faker->unique()->randomElement([100, 500, 1000]),
            'rounding' => 100,
            'strategy' => CashRounding::STRATEGY_HALF_UP,
            'is_active' => true,
        ];
    }
}
