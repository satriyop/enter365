<?php

namespace Database\Factories\Manufacturing;

use App\Models\ElectricalPanel\ComponentStandard;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\BomTemplate;
use App\Models\Manufacturing\BomTemplateItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BomTemplateItem>
 */
class BomTemplateItemFactory extends Factory
{
    protected $model = BomTemplateItem::class;

    public function definition(): array
    {
        return [
            'template_id' => BomTemplate::factory(),
            'type' => BomTemplateItem::TYPE_MATERIAL,
            'component_standard_id' => null,
            'product_id' => null,
            'description' => $this->faker->words(4, true),
            'default_quantity' => $this->faker->randomFloat(2, 1, 10),
            'unit' => $this->faker->randomElement(['pcs', 'set', 'm', 'kg']),
            'is_required' => true,
            'is_quantity_variable' => false,
            'sort_order' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function material(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomTemplateItem::TYPE_MATERIAL,
        ]);
    }

    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomTemplateItem::TYPE_LABOR,
        ]);
    }

    public function overhead(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => BomTemplateItem::TYPE_OVERHEAD,
        ]);
    }

    public function withComponentStandard(): static
    {
        return $this->state(fn (array $attributes) => [
            'component_standard_id' => ComponentStandard::factory(),
        ]);
    }

    public function withProduct(): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => Product::factory(),
        ]);
    }

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
        ]);
    }

    public function variableQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_quantity_variable' => true,
        ]);
    }

    public function sorted(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }
}
