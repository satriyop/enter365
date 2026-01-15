<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\StockOpnameItem>
 */
class StockOpnameItemFactory extends Factory
{
    protected $model = StockOpnameItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $systemQty = $this->faker->numberBetween(10, 100);
        $systemCost = $this->faker->numberBetween(10000, 500000);

        return [
            'stock_opname_id' => StockOpname::factory(),
            'product_id' => Product::factory()->state(['track_inventory' => true]),
            'system_quantity' => $systemQty,
            'system_cost' => $systemCost,
            'system_value' => $systemQty * $systemCost,
            'counted_quantity' => null,
            'variance_quantity' => 0,
            'variance_value' => 0,
            'notes' => null,
            'counted_at' => null,
        ];
    }

    /**
     * Indicate the item has been counted with no variance.
     */
    public function counted(): static
    {
        return $this->state(function (array $attributes) {
            $countedQty = $attributes['system_quantity'];

            return [
                'counted_quantity' => $countedQty,
                'variance_quantity' => 0,
                'variance_value' => 0,
                'counted_at' => now(),
            ];
        });
    }

    /**
     * Indicate the item has been counted with positive variance (surplus).
     */
    public function withSurplus(int $surplus = 5): static
    {
        return $this->state(function (array $attributes) use ($surplus) {
            $countedQty = $attributes['system_quantity'] + $surplus;

            return [
                'counted_quantity' => $countedQty,
                'variance_quantity' => $surplus,
                'variance_value' => $surplus * $attributes['system_cost'],
                'counted_at' => now(),
            ];
        });
    }

    /**
     * Indicate the item has been counted with negative variance (shortage).
     */
    public function withShortage(int $shortage = 5): static
    {
        return $this->state(function (array $attributes) use ($shortage) {
            $countedQty = max(0, $attributes['system_quantity'] - $shortage);
            $varianceQty = $countedQty - $attributes['system_quantity'];

            return [
                'counted_quantity' => $countedQty,
                'variance_quantity' => $varianceQty,
                'variance_value' => $varianceQty * $attributes['system_cost'],
                'counted_at' => now(),
            ];
        });
    }

    /**
     * Assign to a specific stock opname.
     */
    public function forStockOpname(StockOpname $stockOpname): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_opname_id' => $stockOpname->id,
        ]);
    }

    /**
     * Assign to a specific product.
     */
    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
        ]);
    }
}
