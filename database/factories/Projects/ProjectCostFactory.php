<?php

namespace Database\Factories\Projects;

use App\Models\Inventory\Product;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Purchasing\Bill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectCost>
 */
class ProjectCostFactory extends Factory
{
    protected $model = ProjectCost::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(2, 1, 100);
        $unitCost = $this->faker->numberBetween(10000, 1000000);
        $totalCost = (int) round($quantity * $unitCost);

        return [
            'project_id' => Project::factory(),
            'cost_type' => $this->faker->randomElement([
                ProjectCost::TYPE_MATERIAL,
                ProjectCost::TYPE_LABOR,
                ProjectCost::TYPE_SUBCONTRACTOR,
            ]),
            'description' => $this->faker->words(4, true),
            'cost_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'quantity' => $quantity,
            'unit' => $this->faker->randomElement(['pcs', 'unit', 'jam', 'hari', 'lot']),
            'unit_cost' => $unitCost,
            'total_cost' => $totalCost,
            'bill_id' => null,
            'product_id' => null,
            'vendor_name' => $this->faker->optional()->company(),
            'is_billable' => true,
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * For specific project.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes) => [
            'project_id' => $project->id,
        ]);
    }

    /**
     * Material cost.
     */
    public function material(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => ProjectCost::TYPE_MATERIAL,
        ]);
    }

    /**
     * Labor cost.
     */
    public function labor(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => ProjectCost::TYPE_LABOR,
            'unit' => 'jam',
        ]);
    }

    /**
     * Subcontractor cost.
     */
    public function subcontractor(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => ProjectCost::TYPE_SUBCONTRACTOR,
            'unit' => 'lot',
        ]);
    }

    /**
     * Equipment cost.
     */
    public function equipment(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => ProjectCost::TYPE_EQUIPMENT,
        ]);
    }

    /**
     * Overhead cost.
     */
    public function overhead(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => ProjectCost::TYPE_OVERHEAD,
        ]);
    }

    /**
     * With bill.
     */
    public function withBill(?Bill $bill = null): static
    {
        return $this->state(function (array $attributes) use ($bill) {
            $b = $bill ?? Bill::factory()->create();

            return [
                'bill_id' => $b->id,
                'vendor_name' => $b->contact->name,
            ];
        });
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
            ];
        });
    }

    /**
     * With specific amount.
     */
    public function withAmount(int $totalCost): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => 1,
            'unit_cost' => $totalCost,
            'total_cost' => $totalCost,
        ]);
    }

    /**
     * Non-billable.
     */
    public function nonBillable(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_billable' => false,
        ]);
    }
}
