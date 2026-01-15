<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([
            InventoryMovement::TYPE_IN,
            InventoryMovement::TYPE_OUT,
            InventoryMovement::TYPE_ADJUSTMENT,
        ]);

        $quantityBefore = $this->faker->numberBetween(10, 100);
        $quantity = $type === InventoryMovement::TYPE_OUT
            ? -$this->faker->numberBetween(1, min(10, $quantityBefore))
            : $this->faker->numberBetween(1, 50);
        $quantityAfter = $quantityBefore + $quantity;
        $unitCost = $this->faker->randomElement([50000, 100000, 250000]);

        return [
            'movement_number' => $this->generateMovementNumber($type),
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'type' => $type,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => max(0, $quantityAfter),
            'unit_cost' => $unitCost,
            'total_cost' => abs($quantity) * $unitCost,
            'reference_type' => null,
            'reference_id' => null,
            'transfer_warehouse_id' => null,
            'movement_date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'notes' => $this->faker->optional(0.5)->sentence(),
            'created_by' => null,
        ];
    }

    private function generateMovementNumber(string $type): string
    {
        $prefix = match ($type) {
            InventoryMovement::TYPE_IN => 'IN',
            InventoryMovement::TYPE_OUT => 'OUT',
            InventoryMovement::TYPE_ADJUSTMENT => 'ADJ',
            InventoryMovement::TYPE_TRANSFER_IN, InventoryMovement::TYPE_TRANSFER_OUT => 'TRF',
            default => 'MOV',
        };

        $date = now()->format('Ymd');

        return "{$prefix}-{$date}-".str_pad($this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function stockIn(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $this->faker->numberBetween(1, 50);

            return [
                'movement_number' => $this->generateMovementNumber(InventoryMovement::TYPE_IN),
                'type' => InventoryMovement::TYPE_IN,
                'quantity' => $quantity,
                'quantity_after' => $attributes['quantity_before'] + $quantity,
                'notes' => 'Penerimaan barang',
            ];
        });
    }

    public function stockOut(): static
    {
        return $this->state(function (array $attributes) {
            $maxOut = min(10, $attributes['quantity_before']);
            $quantity = $this->faker->numberBetween(1, max(1, $maxOut));

            return [
                'movement_number' => $this->generateMovementNumber(InventoryMovement::TYPE_OUT),
                'type' => InventoryMovement::TYPE_OUT,
                'quantity' => -$quantity,
                'quantity_after' => max(0, $attributes['quantity_before'] - $quantity),
                'notes' => 'Pengeluaran barang',
            ];
        });
    }

    public function adjustment(): static
    {
        return $this->state(function (array $attributes) {
            $diff = $this->faker->numberBetween(-10, 10);

            return [
                'movement_number' => $this->generateMovementNumber(InventoryMovement::TYPE_ADJUSTMENT),
                'type' => InventoryMovement::TYPE_ADJUSTMENT,
                'quantity' => $diff,
                'quantity_after' => max(0, $attributes['quantity_before'] + $diff),
                'notes' => 'Penyesuaian stok',
            ];
        });
    }

    public function transferOut(Warehouse $toWarehouse): static
    {
        return $this->state(function (array $attributes) use ($toWarehouse) {
            $quantity = $this->faker->numberBetween(1, min(10, $attributes['quantity_before']));

            return [
                'movement_number' => $this->generateMovementNumber(InventoryMovement::TYPE_TRANSFER_OUT),
                'type' => InventoryMovement::TYPE_TRANSFER_OUT,
                'quantity' => -$quantity,
                'quantity_after' => max(0, $attributes['quantity_before'] - $quantity),
                'transfer_warehouse_id' => $toWarehouse->id,
                'notes' => "Transfer ke {$toWarehouse->name}",
            ];
        });
    }

    public function transferIn(Warehouse $fromWarehouse): static
    {
        return $this->state(function (array $attributes) use ($fromWarehouse) {
            $quantity = $this->faker->numberBetween(1, 10);

            return [
                'movement_number' => $this->generateMovementNumber(InventoryMovement::TYPE_TRANSFER_IN),
                'type' => InventoryMovement::TYPE_TRANSFER_IN,
                'quantity' => $quantity,
                'quantity_after' => $attributes['quantity_before'] + $quantity,
                'transfer_warehouse_id' => $fromWarehouse->id,
                'notes' => "Transfer dari {$fromWarehouse->name}",
            ];
        });
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (array $attributes) => [
            'product_id' => $product->id,
            'unit_cost' => $product->purchase_price,
        ]);
    }

    public function inWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouse->id,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'movement_date' => $date,
        ]);
    }
}
