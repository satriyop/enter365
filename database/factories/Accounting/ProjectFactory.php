<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Contact;
use App\Models\Accounting\Project;
use App\Models\Accounting\Quotation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('-1 month', '+1 month');
        $endDate = $this->faker->dateTimeBetween($startDate, '+6 months');

        return [
            'project_number' => 'PRJ-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'name' => $this->faker->words(4, true),
            'description' => $this->faker->optional()->paragraph(),
            'contact_id' => Contact::factory()->customer(),
            'quotation_id' => null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'actual_start_date' => null,
            'actual_end_date' => null,
            'status' => Project::STATUS_DRAFT,
            'budget_amount' => $this->faker->numberBetween(10000000, 500000000),
            'contract_amount' => $this->faker->numberBetween(15000000, 600000000),
            'total_cost' => 0,
            'total_revenue' => 0,
            'gross_profit' => 0,
            'profit_margin' => 0,
            'progress_percentage' => 0,
            'priority' => Project::PRIORITY_NORMAL,
            'location' => $this->faker->optional()->city(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_DRAFT,
        ]);
    }

    /**
     * Planning status.
     */
    public function planning(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_PLANNING,
        ]);
    }

    /**
     * In progress status.
     */
    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_IN_PROGRESS,
            'start_date' => now()->subWeek(),
            'end_date' => now()->addMonths(2),
            'actual_start_date' => now()->subWeek(),
            'progress_percentage' => $this->faker->numberBetween(10, 80),
        ]);
    }

    /**
     * On hold status.
     */
    public function onHold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_ON_HOLD,
            'actual_start_date' => now()->subMonth(),
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_COMPLETED,
            'actual_start_date' => now()->subMonths(3),
            'actual_end_date' => now()->subWeek(),
            'progress_percentage' => 100,
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_CANCELLED,
        ]);
    }

    /**
     * For specific contact.
     */
    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * From quotation.
     */
    public function fromQuotation(?Quotation $quotation = null): static
    {
        return $this->state(function (array $attributes) use ($quotation) {
            $q = $quotation ?? Quotation::factory()->approved()->create();

            return [
                'quotation_id' => $q->id,
                'contact_id' => $q->contact_id,
                'contract_amount' => $q->total_amount,
            ];
        });
    }

    /**
     * With financials.
     */
    public function withFinancials(int $totalCost = 10000000, int $totalRevenue = 15000000): static
    {
        return $this->state(function (array $attributes) use ($totalCost, $totalRevenue) {
            $grossProfit = $totalRevenue - $totalCost;
            $profitMargin = $totalRevenue > 0 ? round(($grossProfit / $totalRevenue) * 100, 2) : 0;

            return [
                'total_cost' => $totalCost,
                'total_revenue' => $totalRevenue,
                'gross_profit' => $grossProfit,
                'profit_margin' => $profitMargin,
            ];
        });
    }

    /**
     * High priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Project::PRIORITY_HIGH,
        ]);
    }

    /**
     * Urgent priority.
     */
    public function urgent(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => Project::PRIORITY_URGENT,
        ]);
    }

    /**
     * Overdue project.
     */
    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Project::STATUS_IN_PROGRESS,
            'start_date' => now()->subMonths(3),
            'end_date' => now()->subWeek(),
            'actual_start_date' => now()->subMonths(3),
        ]);
    }
}
