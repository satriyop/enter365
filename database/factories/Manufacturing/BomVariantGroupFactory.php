<?php

namespace Database\Factories\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomVariantGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomVariantGroup>
 */
class BomVariantGroupFactory extends Factory
{
    protected $model = BomVariantGroup::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->words(3, true).' Options',
            'description' => $this->faker->optional()->sentence(),
            'comparison_notes' => $this->faker->optional()->paragraph(),
            'status' => DocumentStatus::Draft,
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Active,
        ]);
    }

    /**
     * Archived status.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Archived,
        ]);
    }

    /**
     * For specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
