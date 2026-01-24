<?php

namespace Database\Factories\Inventory;

use App\Models\Inventory\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    public function definition(): array
    {
        return [
            'code' => 'WH-'.str_pad((string) $this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'name' => $this->faker->randomElement(['Gudang Utama', 'Gudang Cabang', 'Gudang Toko']).' '.$this->faker->city(),
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'contact_person' => $this->faker->name(),
            'is_default' => false,
            'is_active' => true,
            'notes' => $this->faker->optional(0.3)->sentence(),
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function main(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'WH-001',
            'name' => 'Gudang Utama',
            'is_default' => true,
        ]);
    }
}
