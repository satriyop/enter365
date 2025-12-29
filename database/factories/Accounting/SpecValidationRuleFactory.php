<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\SpecValidationRule;
use App\Models\Accounting\SpecValidationRuleSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SpecValidationRule>
 */
class SpecValidationRuleFactory extends Factory
{
    protected $model = SpecValidationRule::class;

    public function definition(): array
    {
        return [
            'rule_set_id' => SpecValidationRuleSet::factory(),
            'category' => $this->faker->randomElement(array_keys(ComponentStandard::getCategories())),
            'spec_key' => $this->faker->randomElement(['rating_amps', 'poles', 'breaking_capacity_ka']),
            'validation_type' => SpecValidationRule::TYPE_WARN_IF_LOWER,
            'severity' => SpecValidationRule::SEVERITY_WARNING,
            'message' => $this->faker->sentence(),
            'sort_order' => 0,
        ];
    }

    public function warnIfLower(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_type' => SpecValidationRule::TYPE_WARN_IF_LOWER,
        ]);
    }

    public function warnIfHigher(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_type' => SpecValidationRule::TYPE_WARN_IF_HIGHER,
        ]);
    }

    public function mustMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'validation_type' => SpecValidationRule::TYPE_MUST_MATCH,
        ]);
    }

    public function error(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => SpecValidationRule::SEVERITY_ERROR,
        ]);
    }

    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => SpecValidationRule::SEVERITY_WARNING,
        ]);
    }

    public function forCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    public function forSpecKey(string $specKey): static
    {
        return $this->state(fn (array $attributes) => [
            'spec_key' => $specKey,
        ]);
    }

    public function sorted(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
