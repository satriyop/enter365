<?php

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialConsumption>
 */
class MaterialConsumptionFactory extends Factory
{
    protected $model = MaterialConsumption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 20);
        $unitCost = $this->faker->numberBetween(10000, 500000);
        $totalCost = (int) round($quantity * $unitCost);

        return [
            'work_order_id' => WorkOrder::factory(),
            'work_order_item_id' => null,
            'product_id' => Product::factory(),
            'quantity_consumed' => $quantity,
            'quantity_scrapped' => 0,
            'scrap_reason' => null,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'kg', 'm']),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'consumed_date' => now(),
            'batch_number' => null,
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
     * For specific work order item.
     */
    public function forWorkOrderItem(WorkOrderItem $woItem): static
    {
        return $this->state(fn (array $attributes) => [
            'work_order_id' => $woItem->work_order_id,
            'work_order_item_id' => $woItem->id,
            'product_id' => $woItem->product_id,
            'unit' => $woItem->unit,
            'unit_cost' => $woItem->unit_cost,
        ]);
    }

    /**
     * With scrap.
     */
    public function withScrap(float $scrapQty = 1, ?string $reason = null): static
    {
        return $this->state(function (array $attributes) use ($scrapQty, $reason) {
            $consumed = $attributes['quantity_consumed'] ?? 10;
            $unitCost = $attributes['unit_cost'] ?? 100000;
            $totalQty = $consumed + $scrapQty;

            return [
                'quantity_scrapped' => $scrapQty,
                'scrap_reason' => $reason ?? 'Material rusak',
                'total_cost' => (int) round($totalQty * $unitCost),
            ];
        });
    }

    /**
     * With specific amount.
     */
    public function withAmount(int $unitCost, float $quantity = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity_consumed' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => (int) round($quantity * $unitCost),
        ]);
    }

    /**
     * With batch number.
     */
    public function withBatch(string $batchNumber): static
    {
        return $this->state(fn (array $attributes) => [
            'batch_number' => $batchNumber,
        ]);
    }
}
