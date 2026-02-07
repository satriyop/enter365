<?php

namespace Database\Factories\Sales;

use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownPaymentApplication>
 */
class DownPaymentApplicationFactory extends Factory
{
    protected $model = DownPaymentApplication::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'down_payment_id' => DownPayment::factory(),
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory(),
            'amount' => $this->faker->numberBetween(500000, 5000000),
            'applied_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Applied to an invoice.
     */
    public function toInvoice(?Invoice $invoice = null): static
    {
        return $this->state(function (array $attributes) use ($invoice) {
            $inv = $invoice ?? Invoice::factory()->create();

            return [
                'applicable_type' => Invoice::class,
                'applicable_id' => $inv->id,
            ];
        });
    }

    /**
     * Applied to a bill.
     */
    public function toBill(?Bill $bill = null): static
    {
        return $this->state(function (array $attributes) use ($bill) {
            $b = $bill ?? Bill::factory()->create();

            return [
                'applicable_type' => Bill::class,
                'applicable_id' => $b->id,
            ];
        });
    }

    /**
     * For specific down payment.
     */
    public function forDownPayment(DownPayment $downPayment): static
    {
        return $this->state(fn (array $attributes) => [
            'down_payment_id' => $downPayment->id,
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
