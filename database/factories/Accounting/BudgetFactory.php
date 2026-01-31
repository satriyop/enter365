<?php

namespace Database\Factories\Accounting;

use App\Enums\BudgetStatus;
use App\Models\Accounting\Budget;
use App\Models\Accounting\FiscalPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        $totalRevenue = $this->faker->numberBetween(100000000, 500000000);
        $totalExpense = $this->faker->numberBetween(80000000, 400000000);

        return [
            'name' => 'Anggaran '.$this->faker->year(),
            'description' => $this->faker->optional()->sentence(),
            'fiscal_period_id' => FiscalPeriod::factory(),
            'type' => Budget::TYPE_ANNUAL,
            'status' => BudgetStatus::Draft,
            'total_revenue' => $totalRevenue,
            'total_expense' => $totalExpense,
            'net_budget' => $totalRevenue - $totalExpense,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => $this->faker->optional(0.3)->paragraph(),
        ];
    }

    public function forPeriod(FiscalPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'fiscal_period_id' => $period->id,
            'name' => 'Anggaran '.$period->name,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BudgetStatus::Draft,
            'approved_by' => null,
            'approved_at' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BudgetStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BudgetStatus::Closed,
            'approved_at' => now()->subMonth(),
        ]);
    }

    public function quarterly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Budget::TYPE_QUARTERLY,
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Budget::TYPE_MONTHLY,
        ]);
    }
}
