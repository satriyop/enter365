<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\PaymentTerm;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentTerm> */
class PaymentTermFactory extends Factory
{
    protected $model = PaymentTerm::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PAY-????-####')),
            'name' => fake()->words(3, true),
            'is_active' => true,
            'note' => null,
            'lines' => [['type' => 'balance', 'value' => 0, 'days' => 30, 'due_type' => 'days_after']],
        ];
    }
}
