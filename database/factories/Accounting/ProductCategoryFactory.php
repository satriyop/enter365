<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    public function definition(): array
    {
        return [
            'code' => 'CAT-' . str_pad($this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->optional()->sentence(),
            'parent_id' => null,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function childOf(ProductCategory $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
            'code' => $parent->code . '-' . str_pad($this->faker->unique()->numberBetween(1, 99), 2, '0', STR_PAD_LEFT),
        ]);
    }
}
