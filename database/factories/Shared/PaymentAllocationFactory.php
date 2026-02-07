<?php

namespace Database\Factories\Shared;

use App\Models\Shared\Payment;
use App\Models\Shared\PaymentAllocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'allocatable_type' => 'invoice',
            'allocatable_id' => 1,
            'amount' => $this->faker->randomElement([500000, 1000000, 2000000]),
        ];
    }

    public function forPayment(Payment $payment): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_id' => $payment->id,
        ]);
    }
}
