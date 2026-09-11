<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalPosition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPosition>
 */
class FiscalPositionFactory extends Factory
{
    protected $model = FiscalPosition::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('FP-###')),
            'name' => fake()->randomElement(['Export 0%', 'Non-PKP', 'Import', 'Exempt']),
            'notes' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
