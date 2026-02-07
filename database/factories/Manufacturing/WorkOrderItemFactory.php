<?php

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrderItem>
 */
class WorkOrderItemFactory extends Factory
{
    protected $model = WorkOrderItem::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 10);
        $unitCost = $this->faker->numberBetween(10000, 500000);
        $totalCost = (int) round($quantity * $unitCost);

        return [
            'work_order_id' => WorkOrder::factory(),
            'bom_item_id' => null,
            'parent_item_id' => null,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => null,
            'description' => $this->faker->words(3, true),
            'quantity_required' => $quantity,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'quantity_scrapped' => 0,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'kg', 'm', 'liter']),
            'unit_cost' => $unitCost,
            'actual_unit_cost' => 0,
            'total_estimated_cost' => $totalCost,
            'total_actual_cost' => 0,
            'sort_order' => 0,
            'level' => 0,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific work order.
     */
    public function forWorkOrder(WorkOrder $workOrder): static
    {
        return $this->state(fn (array $attributes) => [
            'work_order_id' => $workOrder->id,
        ]);
    }

    /**
     * Material type.
     */
    public function material(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkOrderItem::TYPE_MATERIAL,
        ]);
    }

    /**
     * Labor type.
     */
    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => WorkOrderItem::TYPE_LABOR,
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
            'type' => WorkOrderItem::TYPE_OVERHEAD,
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
            'quantity_required' => $quantity,
            'total_estimated_cost' => (int) round($quantity * $unitCost),
        ]);
    }

    /**
     * Reserved.
     */
    public function reserved(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_reserved' => $attributes['quantity_required'] ?? 1,
        ]);
    }

    /**
     * Consumed.
     */
    public function consumed(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity_required'] ?? 1;
            $unitCost = $attributes['unit_cost'] ?? 100000;

            return [
                'quantity_consumed' => $quantity,
                'actual_unit_cost' => $unitCost,
                'total_actual_cost' => (int) round($quantity * $unitCost),
            ];
        });
    }

    /**
     * With hierarchy level.
     */
    public function atLevel(int $level): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => $level,
        ]);
    }

    /**
     * As child item.
     */
    public function childOf(WorkOrderItem $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_item_id' => $parent->id,
            'work_order_id' => $parent->work_order_id,
            'level' => $parent->level + 1,
        ]);
    }
}
