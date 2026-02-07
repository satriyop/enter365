<?php

namespace Database\Factories\Sales;

use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Sales\DownPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DownPayment>
 */
class DownPaymentFactory extends Factory
{
    protected $model = DownPayment::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement([DownPayment::TYPE_RECEIVABLE, DownPayment::TYPE_PAYABLE]);
        $prefix = $type === DownPayment::TYPE_RECEIVABLE ? 'DPR-' : 'DPP-';

        return [
            'dp_number' => $prefix.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'type' => $type,
            'contact_id' => Contact::factory(),
            'dp_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'amount' => $this->faker->numberBetween(1000000, 50000000),
            'applied_amount' => 0,
            'payment_method' => $this->faker->randomElement(DownPayment::PAYMENT_METHODS),
            'cash_account_id' => Account::factory(),
            'reference' => $this->faker->optional()->numerify('REF-####'),
            'description' => $this->faker->optional()->sentence(),
            'notes' => $this->faker->optional()->paragraph(),
            'status' => DownPayment::STATUS_ACTIVE,
        ];
    }

    /**
     * DP from customer (receivable).
     */
    public function receivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DownPayment::TYPE_RECEIVABLE,
            'dp_number' => 'DPR-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'contact_id' => Contact::factory()->customer(),
        ]);
    }

    /**
     * DP to vendor (payable).
     */
    public function payable(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DownPayment::TYPE_PAYABLE,
            'dp_number' => 'DPP-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'contact_id' => Contact::factory()->vendor(),
        ]);
    }

    /**
     * Partially applied DP.
     */
    public function partiallyApplied(?int $appliedAmount = null): static
    {
        return $this->state(function (array $attributes) use ($appliedAmount) {
            $amount = $attributes['amount'] ?? 10000000;
            $applied = $appliedAmount ?? (int) ($amount * 0.5);

            return [
                'amount' => $amount,
                'applied_amount' => $applied,
                'status' => DownPayment::STATUS_ACTIVE,
            ];
        });
    }

    /**
     * Fully applied DP.
     */
    public function fullyApplied(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $attributes['amount'] ?? 10000000;

            return [
                'applied_amount' => $amount,
                'status' => DownPayment::STATUS_FULLY_APPLIED,
            ];
        });
    }

    /**
     * Refunded DP.
     */
    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DownPayment::STATUS_REFUNDED,
            'refunded_at' => now(),
        ]);
    }

    /**
     * Cancelled DP.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DownPayment::STATUS_CANCELLED,
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

    /**
     * With specific contact.
     */
    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }
}
