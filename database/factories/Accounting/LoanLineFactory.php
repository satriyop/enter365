<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Loan;
use App\Models\Accounting\LoanLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanLine>
 */
class LoanLineFactory extends Factory
{
    protected $model = LoanLine::class;

    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'sequence' => 1,
            'due_date' => now()->endOfMonth()->toDateString(),
            'principal_amount' => 100_000,
            'interest_amount' => 0,
            'payment_amount' => 100_000,
            'remaining_principal' => 1_100_000,
            'status' => LoanLine::STATUS_DRAFT,
        ];
    }
}
