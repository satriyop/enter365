<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $purchasePrice = $this->faker->randomElement([50000, 100000, 250000, 500000, 1000000]);
        $markup = $this->faker->randomElement([20, 30, 50, 100]); // percentage
        $sellingPrice = (int) ($purchasePrice * (1 + $markup / 100));

        return [
            'sku' => 'PRD-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->optional()->sentence(),
            'type' => Product::TYPE_PRODUCT,
            'category_id' => null,
            'unit' => $this->faker->randomElement(['unit', 'pcs', 'kg', 'liter', 'box', 'pack']),
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
            'tax_rate' => 11.00,
            'is_taxable' => true,
            'track_inventory' => true,
            'min_stock' => $this->faker->numberBetween(5, 20),
            'current_stock' => $this->faker->numberBetween(0, 100),
            'inventory_account_id' => null,
            'cogs_account_id' => null,
            'sales_account_id' => null,
            'purchase_account_id' => null,
            'is_active' => true,
            'is_purchasable' => true,
            'is_sellable' => true,
            'barcode' => $this->faker->optional(0.5)->ean13(),
            'brand' => $this->faker->optional(0.3)->company(),
            'custom_fields' => null,
        ];
    }

    public function service(): static
    {
        return $this->state(fn (array $attributes) => [
            'sku' => 'SVC-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'type' => Product::TYPE_SERVICE,
            'unit' => $this->faker->randomElement(['jam', 'hari', 'bulan', 'proyek']),
            'track_inventory' => false,
            'min_stock' => 0,
            'current_stock' => 0,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function lowStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'track_inventory' => true,
            'min_stock' => 10,
            'current_stock' => $this->faker->numberBetween(0, 10),
        ]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'track_inventory' => true,
            'current_stock' => 0,
        ]);
    }

    public function withCategory(?ProductCategory $category = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category_id' => $category?->id ?? ProductCategory::factory(),
        ]);
    }

    public function notSellable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_sellable' => false,
        ]);
    }

    public function notPurchasable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_purchasable' => false,
        ]);
    }

    public function taxFree(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_taxable' => false,
            'tax_rate' => 0,
        ]);
    }

    public function withPrices(int $purchasePrice, int $sellingPrice): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_price' => $purchasePrice,
            'selling_price' => $sellingPrice,
        ]);
    }
}
