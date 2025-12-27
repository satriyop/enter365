<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\ComponentStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentStandard>
 */
class ComponentStandardFactory extends Factory
{
    protected $model = ComponentStandard::class;

    public function definition(): array
    {
        $categories = ComponentStandard::getCategories();
        $category = $this->faker->randomElement(array_keys($categories));

        return [
            'code' => strtoupper($this->faker->unique()->bothify('???-##A')),
            'name' => $this->faker->words(3, true),
            'category' => $category,
            'subcategory' => $category === ComponentStandard::CATEGORY_CIRCUIT_BREAKER
                ? $this->faker->randomElement([
                    ComponentStandard::SUBCATEGORY_MCB,
                    ComponentStandard::SUBCATEGORY_MCCB,
                    ComponentStandard::SUBCATEGORY_ACB,
                ])
                : null,
            'standard' => $this->faker->randomElement(['IEC 60898', 'IEC 60947', 'IEC 61009']),
            'description' => $this->faker->optional()->sentence(),
            'unit' => 'pcs',
            'is_active' => true,
            'created_by' => null,
            'specifications' => [
                'rating_amps' => $this->faker->randomElement([6, 10, 16, 20, 25, 32, 40, 50]),
                'poles' => $this->faker->randomElement([1, 2, 3]),
                'breaking_capacity_ka' => $this->faker->randomElement([3, 6, 10, 15, 20]),
                'curve' => $this->faker->randomElement(['B', 'C', 'D']),
                'voltage' => '230/400V',
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function forCategory(string $category): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
        ]);
    }

    public function withSpecifications(array $specs): static
    {
        return $this->state(fn (array $attributes) => [
            'specifications' => array_merge($attributes['specifications'] ?? [], $specs),
        ]);
    }
}
