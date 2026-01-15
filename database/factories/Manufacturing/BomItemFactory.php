<?php

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomItem>
 */
class BomItemFactory extends Factory
{
    protected $model = BomItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 10);
        $unitCost = $this->faker->numberBetween(10000, 500000);
        $totalCost = (int) round($quantity * $unitCost);

        return [
            'bom_id' => Bom::factory(),
            'type' => BomItem::TYPE_MATERIAL,
            'product_id' => null,
            'description' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'kg', 'm', 'liter']),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'waste_percentage' => $this->faker->randomElement([0, 5, 10]),
            'sort_order' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific BOM.
     */
    public function forBom(Bom $bom): static
    {
        return $this->state(fn (array $attributes) => [
            'bom_id' => $bom->id,
        ]);
    }

    /**
     * Material type.
     */
    public function material(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomItem::TYPE_MATERIAL,
        ]);
    }

    /**
     * Labor type.
     */
    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomItem::TYPE_LABOR,
            'product_id' => null,
            'unit' => 'jam',
        ]);
    }

    /**
     * Overhead type.
     */
    public function overhead(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomItem::TYPE_OVERHEAD,
            'product_id' => null,
        ]);
    }

    /**
     * With product.
     */
    public function withProduct(?Product $product = null): static
    {
        return $this->state(function (array $attributes) use ($product) {
            $p = $product ?? Product::factory()->create();

            return [
                'product_id' => $p->id,
                'description' => $p->name,
                'unit' => $p->unit,
                'unit_cost' => $p->purchase_price ?? $attributes['unit_cost'],
            ];
        });
    }

    /**
     * With specific cost.
     */
    public function withCost(int $unitCost, float $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'total_cost' => (int) round($quantity * $unitCost),
        ]);
    }

    /**
     * With waste percentage.
     */
    public function withWaste(float $percentage): static
    {
        return $this->state(function (array $attributes) use ($percentage) {
            $quantity = $attributes['quantity'] ?? 1;
            $unitCost = $attributes['unit_cost'] ?? 100000;
            $wasteMultiplier = 1 + ($percentage / 100);
            $totalCost = (int) round($quantity * $wasteMultiplier * $unitCost);

            return [
                'waste_percentage' => $percentage,
                'total_cost' => $totalCost,
            ];
        });
    }
}
