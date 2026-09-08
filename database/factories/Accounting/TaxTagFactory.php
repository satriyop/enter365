<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\TaxTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxTag>
 */
class TaxTagFactory extends Factory
{
    protected $model = TaxTag::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TAG-###')),
            'name' => fake()->words(2, true),
            'applicability' => fake()->randomElement(TaxTag::APPLICABILITIES),
            'is_active' => true,
        ];
    }

    public function base(): static
    {
        return $this->state(fn (): array => ['applicability' => TaxTag::APPLICABILITY_BASE]);
    }

    public function tax(): static
    {
        return $this->state(fn (): array => ['applicability' => TaxTag::APPLICABILITY_TAX]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
