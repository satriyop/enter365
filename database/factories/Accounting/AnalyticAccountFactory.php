<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\AnalyticAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AnalyticAccount>
 */
class AnalyticAccountFactory extends Factory
{
    protected $model = AnalyticAccount::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('ANA-###')),
            'name' => fake()->words(2, true),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
