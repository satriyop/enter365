<?php

namespace Database\Factories\Projects;

use App\Models\Projects\Project;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\DownPayment;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRevenue>
 */
class ProjectRevenueFactory extends Factory
{
    protected $model = ProjectRevenue::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'revenue_type' => $this->faker->randomElement([
                ProjectRevenue::TYPE_INVOICE,
                ProjectRevenue::TYPE_MILESTONE,
            ]),
            'description' => $this->faker->words(4, true),
            'revenue_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'amount' => $this->faker->numberBetween(1000000, 50000000),
            'invoice_id' => null,
            'down_payment_id' => null,
            'milestone_name' => null,
            'milestone_percentage' => null,
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
     * Invoice type.
     */
    public function invoice(?Invoice $invoice = null): static
    {
        return $this->state(function (array $attributes) use ($invoice) {
            $inv = $invoice ?? Invoice::factory()->create();

            return [
                'revenue_type' => ProjectRevenue::TYPE_INVOICE,
                'invoice_id' => $inv->id,
                'amount' => $inv->total_amount,
                'description' => 'Invoice: '.$inv->invoice_number,
            ];
        });
    }

    /**
     * Down payment type.
     */
    public function downPayment(?DownPayment $downPayment = null): static
    {
        return $this->state(function (array $attributes) use ($downPayment) {
            $dp = $downPayment ?? DownPayment::factory()->receivable()->create();

            return [
                'revenue_type' => ProjectRevenue::TYPE_DOWN_PAYMENT,
                'down_payment_id' => $dp->id,
                'amount' => $dp->amount,
                'description' => 'Uang Muka: '.$dp->dp_number,
            ];
        });
    }

    /**
     * Milestone type.
     */
    public function milestone(?string $name = null, ?float $percentage = null): static
    {
        return $this->state(fn (array $attributes) => [
            'revenue_type' => ProjectRevenue::TYPE_MILESTONE,
            'milestone_name' => $name ?? 'Milestone '.$this->faker->numberBetween(1, 5),
            'milestone_percentage' => $percentage ?? $this->faker->randomElement([20, 25, 30, 50]),
        ]);
    }

    /**
     * With specific amount.
     */
    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }
}
