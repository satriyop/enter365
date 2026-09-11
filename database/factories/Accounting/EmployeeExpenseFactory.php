<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\EmployeeExpense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeExpense>
 */
class EmployeeExpenseFactory extends Factory
{
    protected $model = EmployeeExpense::class;

    public function definition(): array
    {
        $amount = $this->faker->randomElement([50_000, 100_000, 250_000, 500_000]);

        return [
            'expense_number' => 'EXP-'.$this->faker->unique()->numerify('######'),
            'employee_id' => User::factory(),
            'expense_date' => now()->toDateString(),
            'description' => $this->faker->sentence(4),
            'amount' => $amount,
            'tax_amount' => 0,
            'total_amount' => $amount,
            'expense_account_id' => Account::query()->where('code', '5-2100')->value('id')
                ?? Account::factory(),
            'status' => EmployeeExpense::STATUS_DRAFT,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeExpense::STATUS_SUBMITTED,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EmployeeExpense::STATUS_APPROVED,
        ]);
    }
}
