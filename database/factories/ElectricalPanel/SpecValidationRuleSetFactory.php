<?php

namespace Database\Factories\ElectricalPanel;

use App\Models\ElectricalPanel\SpecValidationRuleSet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecValidationRuleSet>
 */
class SpecValidationRuleSetFactory extends Factory
{
    protected $model = SpecValidationRuleSet::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('RS-???-##')),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'is_default' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function withCreator(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => User::factory(),
        ]);
    }
}
