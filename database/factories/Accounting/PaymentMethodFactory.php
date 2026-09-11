<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentMethod> */
class PaymentMethodFactory extends Factory
{
    protected $model = PaymentMethod::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PAY-????-####')),
            'name' => fake()->words(3, true),
            'is_active' => true,
            'direction' => 'inbound',
            'payment_type' => 'bank_transfer',
            'journal_id' => null,
        ];
    }
}
