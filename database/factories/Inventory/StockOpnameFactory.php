<?php

namespace Database\Factories\Inventory;

use App\Enums\DocumentStatus;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\StockOpname>
 */
class StockOpnameFactory extends Factory
{
    protected $model = StockOpname::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = now()->format('Ymd');
        $unique = fake()->unique()->numberBetween(1, 9999);

        return [
            'opname_number' => "SO-{$date}-".str_pad((string) $unique, 4, '0', STR_PAD_LEFT),
            'warehouse_id' => Warehouse::factory(),
            'opname_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'status' => DocumentStatus::Draft,
            'name' => fake()->randomElement([
                'Stock Opname Bulanan',
                'Stock Opname Tahunan',
                'Cycle Count',
                'Physical Inventory Count',
            ]).' '.fake()->monthName(),
            'notes' => fake()->optional()->sentence(),
            'total_items' => 0,
            'total_counted' => 0,
            'total_variance_qty' => 0,
            'total_variance_value' => 0,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Indicate the stock opname is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    /**
     * Indicate the stock opname is in counting status.
     */
    public function counting(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Counting,
            'counted_by' => User::factory(),
            'counting_started_at' => now(),
        ]);
    }

    /**
     * Indicate the stock opname is in reviewed status.
     */
    public function reviewed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Reviewed,
            'counted_by' => User::factory(),
            'counting_started_at' => now()->subHours(2),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Indicate the stock opname is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'counted_by' => User::factory(),
            'counting_started_at' => now()->subHours(3),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHour(),
            'approved_by' => User::factory(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate the stock opname is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Completed,
            'counted_by' => User::factory(),
            'counting_started_at' => now()->subHours(4),
            'reviewed_by' => User::factory(),
            'reviewed_at' => now()->subHours(2),
            'approved_by' => User::factory(),
            'approved_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate the stock opname is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }

    /**
     * Assign to a specific warehouse.
     */
    public function forWarehouse(Warehouse $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouse->id,
        ]);
    }
}
