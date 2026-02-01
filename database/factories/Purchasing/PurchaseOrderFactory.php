<?php

namespace Database\Factories\Purchasing;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    protected $model = PurchaseOrder::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'PO-'.now()->format('Ym').'-';
        $poNumber = $prefix.str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        $poDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $expectedDate = (clone $poDate)->modify('+14 days');

        $subtotal = $this->faker->randomElement([5000000, 10000000, 25000000, 50000000, 100000000]);
        $taxRate = 11.00;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $total = $subtotal + $taxAmount;

        return [
            'po_number' => $poNumber,
            'revision' => 0,
            'contact_id' => Contact::factory()->vendor(),
            'po_date' => $poDate,
            'expected_date' => $expectedDate,
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'subject' => $this->faker->optional()->sentence(4),
            'status' => DocumentStatus::Draft,
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'subtotal' => $subtotal,
            'discount_type' => null,
            'discount_value' => 0,
            'discount_amount' => 0,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total_amount' => $total,
            'base_currency_total' => $total,
            'notes' => $this->faker->optional()->paragraph(),
            'terms_conditions' => null,
            'shipping_address' => $this->faker->optional()->address(),
            'submitted_at' => null,
            'submitted_by' => null,
            'approved_at' => null,
            'approved_by' => null,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_reason' => null,
            'cancelled_at' => null,
            'cancelled_by' => null,
            'cancellation_reason' => null,
            'first_received_at' => null,
            'fully_received_at' => null,
            'converted_to_bill_id' => null,
            'converted_at' => null,
            'original_po_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
            'submitted_by' => User::factory(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'submitted_at' => now()->subDay(),
            'submitted_by' => User::factory(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'submitted_by' => User::factory(),
            'rejected_at' => now(),
            'rejected_by' => User::factory(),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }

    public function partial(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Partial,
            'submitted_at' => now()->subDays(3),
            'submitted_by' => User::factory(),
            'approved_at' => now()->subDays(2),
            'approved_by' => User::factory(),
            'first_received_at' => now()->subDay(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Received,
            'submitted_at' => now()->subDays(5),
            'submitted_by' => User::factory(),
            'approved_at' => now()->subDays(4),
            'approved_by' => User::factory(),
            'first_received_at' => now()->subDays(2),
            'fully_received_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => User::factory(),
            'cancellation_reason' => $this->faker->sentence(),
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Received,
            'submitted_at' => now()->subDays(5),
            'submitted_by' => User::factory(),
            'approved_at' => now()->subDays(4),
            'approved_by' => User::factory(),
            'first_received_at' => now()->subDays(2),
            'fully_received_at' => now()->subDay(),
            'converted_to_bill_id' => Bill::factory(),
            'converted_at' => now(),
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withPercentageDiscount(float $percent): static
    {
        return $this->state(function (array $attributes) use ($percent) {
            $discountAmount = (int) round($attributes['subtotal'] * ($percent / 100));
            $taxableAmount = $attributes['subtotal'] - $discountAmount;
            $taxAmount = (int) round($taxableAmount * ($attributes['tax_rate'] / 100));
            $total = $taxableAmount + $taxAmount;

            return [
                'discount_type' => 'percentage',
                'discount_value' => $percent,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'base_currency_total' => $total,
            ];
        });
    }

    public function withFixedDiscount(int $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $taxableAmount = $attributes['subtotal'] - $amount;
            $taxAmount = (int) round($taxableAmount * ($attributes['tax_rate'] / 100));
            $total = $taxableAmount + $taxAmount;

            return [
                'discount_type' => 'fixed',
                'discount_value' => $amount,
                'discount_amount' => $amount,
                'tax_amount' => $taxAmount,
                'total_amount' => $total,
                'base_currency_total' => $total,
            ];
        });
    }

    public function expectedIn(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'po_date' => now(),
            'expected_date' => now()->addDays($days),
        ]);
    }

    public function withoutTax(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_amount' => $attributes['subtotal'] - $attributes['discount_amount'],
                'base_currency_total' => $attributes['subtotal'] - $attributes['discount_amount'],
            ];
        });
    }
}
