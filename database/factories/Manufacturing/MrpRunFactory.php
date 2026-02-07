<?php

namespace Database\Factories\Manufacturing;

use App\Enums\DocumentStatus;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MrpRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MrpRun>
 */
class MrpRunFactory extends Factory
{
    protected $model = MrpRun::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $horizonStart = now()->startOfWeek();
        $horizonEnd = $horizonStart->copy()->addWeeks(4);

        return [
            'run_number' => 'MRP-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'name' => 'MRP Run '.$this->faker->words(2, true),
            'planning_horizon_start' => $horizonStart,
            'planning_horizon_end' => $horizonEnd,
            'status' => DocumentStatus::Draft,
            'parameters' => [
                'include_safety_stock' => true,
                'respect_moq' => true,
                'respect_order_multiple' => true,
            ],
            'warehouse_id' => null,
            'total_products_analyzed' => 0,
            'total_demands' => 0,
            'total_shortages' => 0,
            'total_purchase_suggestions' => 0,
            'total_work_order_suggestions' => 0,
            'total_subcontract_suggestions' => 0,
            'created_by' => null,
            'completed_at' => null,
            'applied_at' => null,
            'applied_by' => null,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    /**
     * Processing status.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Processing,
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Completed,
            'completed_at' => now(),
            'total_products_analyzed' => $this->faker->numberBetween(5, 20),
            'total_demands' => $this->faker->numberBetween(10, 50),
            'total_shortages' => $this->faker->numberBetween(2, 10),
            'total_purchase_suggestions' => $this->faker->numberBetween(3, 15),
            'total_work_order_suggestions' => $this->faker->numberBetween(1, 5),
            'total_subcontract_suggestions' => $this->faker->numberBetween(0, 3),
        ]);
    }

    /**
     * Applied status.
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Applied,
            'completed_at' => now()->subHour(),
            'applied_at' => now(),
            'total_products_analyzed' => $this->faker->numberBetween(5, 20),
            'total_demands' => $this->faker->numberBetween(10, 50),
            'total_shortages' => $this->faker->numberBetween(2, 10),
            'total_purchase_suggestions' => $this->faker->numberBetween(3, 15),
            'total_work_order_suggestions' => $this->faker->numberBetween(1, 5),
            'total_subcontract_suggestions' => $this->faker->numberBetween(0, 3),
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
     * With creator.
     */
    public function withCreator(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $u = $user ?? User::factory()->create();

            return [
                'created_by' => $u->id,
            ];
        });
    }

    /**
     * With horizon.
     */
    public function withHorizon(\DateTimeInterface $start, \DateTimeInterface $end): static
    {
        return $this->state(fn (array $attributes) => [
            'planning_horizon_start' => $start,
            'planning_horizon_end' => $end,
        ]);
    }

    /**
     * With parameters.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function withParameters(array $parameters): static
    {
        return $this->state(fn (array $attributes) => [
            'parameters' => $parameters,
        ]);
    }
}
