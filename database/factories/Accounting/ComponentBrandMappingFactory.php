<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\ComponentBrandMapping;
use App\Models\Accounting\ComponentStandard;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComponentBrandMapping>
 */
class ComponentBrandMappingFactory extends Factory
{
    protected $model = ComponentBrandMapping::class;

    public function definition(): array
    {
        return [
            'component_standard_id' => ComponentStandard::factory(),
            'brand' => $this->faker->randomElement([
                ComponentBrandMapping::BRAND_SCHNEIDER,
                ComponentBrandMapping::BRAND_ABB,
                ComponentBrandMapping::BRAND_SIEMENS,
                ComponentBrandMapping::BRAND_CHINT,
                ComponentBrandMapping::BRAND_LS,
            ]),
            'product_id' => Product::factory(),
            'brand_sku' => strtoupper($this->faker->unique()->bothify('???-#####')),
            'is_preferred' => false,
            'is_verified' => false,
            'price_factor' => $this->faker->randomFloat(2, 0.8, 1.5),
            'variant_specs' => null,
            'notes' => $this->faker->optional()->sentence(),
            'verified_by' => null,
            'verified_at' => null,
        ];
    }

    public function preferred(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_preferred' => true,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => true,
            'verified_by' => 1,
            'verified_at' => now(),
        ]);
    }

    public function forBrand(string $brand): static
    {
        return $this->state(fn (array $attributes) => [
            'brand' => $brand,
        ]);
    }

    public function forStandard(ComponentStandard $standard): static
    {
        return $this->state(fn (array $attributes) => [
            'component_standard_id' => $standard->id,
        ]);
    }

    public function withVariantSpecs(array $specs): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_specs' => $specs,
        ]);
    }
}
