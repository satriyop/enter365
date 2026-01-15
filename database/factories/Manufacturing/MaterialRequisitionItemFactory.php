<?php

namespace Database\Factories\Manufacturing;

use App\Models\Inventory\Product;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\Manufacturing\WorkOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaterialRequisitionItem>
 */
class MaterialRequisitionItemFactory extends Factory
{
    protected $model = MaterialRequisitionItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 50);

        return [
            'material_requisition_id' => MaterialRequisition::factory(),
            'work_order_item_id' => null,
            'product_id' => Product::factory(),
            'quantity_requested' => $quantity,
            'quantity_approved' => 0,
            'quantity_issued' => 0,
            'quantity_pending' => 0,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'kg', 'm']),
            'warehouse_location' => $this->faker->optional()->word(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific requisition.
     */
    public function forRequisition(MaterialRequisition $requisition): static
    {
        return $this->state(fn (array $attributes) => [
            'material_requisition_id' => $requisition->id,
        ]);
    }

    /**
     * For specific work order item.
     */
    public function forWorkOrderItem(WorkOrderItem $woItem): static
    {
        return $this->state(fn (array $attributes) => [
            'work_order_item_id' => $woItem->id,
            'product_id' => $woItem->product_id,
            'quantity_requested' => $woItem->quantity_required,
            'unit' => $woItem->unit,
        ]);
    }

    /**
     * Approved.
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity_requested'] ?? 10;

            return [
                'quantity_approved' => $quantity,
                'quantity_pending' => $quantity,
            ];
        });
    }

    /**
     * Fully issued.
     */
    public function issued(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $attributes['quantity_requested'] ?? 10;

            return [
                'quantity_approved' => $quantity,
                'quantity_issued' => $quantity,
                'quantity_pending' => 0,
            ];
        });
    }

    /**
     * Partially issued.
     */
    public function partiallyIssued(?float $issuedQty = null): static
    {
        return $this->state(function (array $attributes) use ($issuedQty) {
            $requested = $attributes['quantity_requested'] ?? 10;
            $issued = $issuedQty ?? ($requested / 2);
            $pending = $requested - $issued;

            return [
                'quantity_approved' => $requested,
                'quantity_issued' => $issued,
                'quantity_pending' => $pending,
            ];
        });
    }
}
