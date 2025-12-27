<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomVariantGroup>
 */
class BomVariantGroupFactory extends Factory
{
    protected $model = BomVariantGroup::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => $this->faker->words(3, true).' Options',
            'description' => $this->faker->optional()->sentence(),
            'comparison_notes' => $this->faker->optional()->paragraph(),
            'status' => BomVariantGroup::STATUS_DRAFT,
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BomVariantGroup::STATUS_DRAFT,
        ]);
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BomVariantGroup::STATUS_ACTIVE,
        ]);
    }

    /**
     * Archived status.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BomVariantGroup::STATUS_ARCHIVED,
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
