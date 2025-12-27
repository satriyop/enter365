<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomVariantGroup;
use App\Models\Accounting\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bom>
 */
class BomFactory extends Factory
{
    protected $model = Bom::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'bom_number' => 'BOM-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->words(3, true).' Assembly',
            'description' => $this->faker->optional()->sentence(),
            'product_id' => Product::factory(),
            'output_quantity' => 1,
            'output_unit' => $this->faker->randomElement(['pcs', 'unit', 'set']),
            'total_material_cost' => 0,
            'total_labor_cost' => 0,
            'total_overhead_cost' => 0,
            'total_cost' => 0,
            'unit_cost' => 0,
            'status' => Bom::STATUS_DRAFT,
            'version' => '1.0',
            'variant_group_id' => null,
            'variant_name' => null,
            'variant_label' => null,
            'is_primary_variant' => false,
            'variant_sort_order' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bom::STATUS_DRAFT,
        ]);
    }

    /**
     * Active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bom::STATUS_ACTIVE,
            'approved_at' => now(),
        ]);
    }

    /**
     * Inactive status.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bom::STATUS_INACTIVE,
        ]);
    }

    /**
     * For specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'output_unit' => $product->unit,
        ]);
    }

    /**
     * With calculated totals.
     */
    public function withTotals(int $materialCost = 500000, int $laborCost = 200000, int $overheadCost = 100000): static
    {
        return $this->state(function (array $attributes) use ($materialCost, $laborCost, $overheadCost) {
            $totalCost = $materialCost + $laborCost + $overheadCost;
            $outputQuantity = $attributes['output_quantity'] ?? 1;
            $unitCost = (int) round($totalCost / $outputQuantity);

            return [
                'total_material_cost' => $materialCost,
                'total_labor_cost' => $laborCost,
                'total_overhead_cost' => $overheadCost,
                'total_cost' => $totalCost,
                'unit_cost' => $unitCost,
            ];
        });
    }

    /**
     * With output quantity.
     */
    public function withOutputQuantity(float $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'output_quantity' => $quantity,
        ]);
    }

    /**
     * For specific variant group.
     */
    public function forVariantGroup(BomVariantGroup $group, ?string $variantName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $group->product_id,
            'variant_group_id' => $group->id,
            'variant_name' => $variantName ?? $this->faker->randomElement(['Budget', 'Standard', 'Premium']),
            'variant_label' => $this->faker->optional()->words(3, true),
        ]);
    }

    /**
     * As primary variant.
     */
    public function asPrimaryVariant(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary_variant' => true,
        ]);
    }

    /**
     * With variant sort order.
     */
    public function withVariantSortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'variant_sort_order' => $order,
        ]);
    }
}
