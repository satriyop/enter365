<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductStock>
 */
class ProductStockFactory extends Factory
{
    protected $model = ProductStock::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(10, 100);
        $averageCost = $this->faker->randomElement([50000, 100000, 250000, 500000]);
        $totalValue = $quantity * $averageCost;

        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'quantity' => $quantity,
            'average_cost' => $averageCost,
            'total_value' => $totalValue,
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'average_cost' => $product->purchase_price,
        ]);
    }

    public function inWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function withQuantity(int $quantity): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $quantity,
            'total_value' => $quantity * $attributes['average_cost'],
        ]);
    }

    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 0,
            'total_value' => 0,
        ]);
    }
}
