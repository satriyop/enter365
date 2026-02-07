<?php

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MrpDemand;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MrpDemand>
 */
class MrpDemandFactory extends Factory
{
    protected $model = MrpDemand::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantityRequired = $this->faker->numberBetween(10, 100);
        $quantityOnHand = $this->faker->numberBetween(0, 50);
        $quantityOnOrder = $this->faker->numberBetween(0, 30);
        $quantityReserved = $this->faker->numberBetween(0, $quantityOnHand);
        $quantityAvailable = max(0, $quantityOnHand + $quantityOnOrder - $quantityReserved);
        $quantityShort = max(0, $quantityRequired - $quantityAvailable);

        $requiredDate = $this->faker->dateTimeBetween('now', '+4 weeks');

        return [
            'mrp_run_id' => MrpRun::factory(),
            'product_id' => Product::factory(),
            'demand_source_type' => WorkOrder::class,
            'demand_source_id' => WorkOrder::factory(),
            'demand_source_number' => 'WO-'.$this->faker->numerify('######'),
            'required_date' => $requiredDate,
            'week_bucket' => (int) $requiredDate->format('W'),
            'quantity_required' => $quantityRequired,
            'quantity_on_hand' => $quantityOnHand,
            'quantity_on_order' => $quantityOnOrder,
            'quantity_reserved' => $quantityReserved,
            'quantity_available' => $quantityAvailable,
            'quantity_short' => $quantityShort,
            'warehouse_id' => null,
            'bom_level' => 0,
        ];
    }

    /**
     * For specific MRP run.
     */
    public function forMrpRun(MrpRun $run): static
    {
        return $this->state(fn (array $attributes) => [
            'mrp_run_id' => $run->id,
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

    /**
     * From work order.
     */
    public function fromWorkOrder(WorkOrder $wo): static
    {
        return $this->state(fn (array $attributes) => [
            'demand_source_type' => WorkOrder::class,
            'demand_source_id' => $wo->id,
            'demand_source_number' => $wo->wo_number,
            'required_date' => $wo->planned_end_date ?? now()->addWeeks(2),
        ]);
    }

    /**
     * With shortage.
     */
    public function withShortage(int $shortageQty = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_required' => 50,
            'quantity_on_hand' => 20,
            'quantity_on_order' => 10,
            'quantity_reserved' => 5,
            'quantity_available' => 25,
            'quantity_short' => $shortageQty,
        ]);
    }

    /**
     * Without shortage.
     */
    public function withoutShortage(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_required' => 30,
            'quantity_on_hand' => 50,
            'quantity_on_order' => 10,
            'quantity_reserved' => 5,
            'quantity_available' => 55,
            'quantity_short' => 0,
        ]);
    }

    /**
     * Direct demand (BOM level 0).
     */
    public function directDemand(): static
    {
        return $this->state(fn (array $attributes) => [
            'bom_level' => 0,
        ]);
    }

    /**
     * Exploded demand (from BOM).
     */
    public function explodedDemand(int $level = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'bom_level' => $level,
        ]);
    }

    /**
     * With warehouse.
     */
    public function withWarehouse(?Warehouse $warehouse = null): static
    {
        return $this->state(function (array $attributes) use ($warehouse) {
            $w = $warehouse ?? Warehouse::factory()->create();

            return [
                'warehouse_id' => $w->id,
            ];
        });
    }

    /**
     * For specific week.
     */
    public function forWeek(int $week): static
    {
        return $this->state(fn (array $attributes) => [
            'week_bucket' => $week,
        ]);
    }
}
