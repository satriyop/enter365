<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PaymentProvider> */
class PaymentProviderFactory extends Factory
{
    protected $model = PaymentProvider::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('PAY-????-####')),
            'name' => fake()->words(3, true),
            'is_active' => true,
            'state' => 'disabled',
            'journal_id' => null,
            'website' => null,
        ];
    }
}
