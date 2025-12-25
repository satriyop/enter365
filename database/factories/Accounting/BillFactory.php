<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bill>
 */
class BillFactory extends Factory
{
    protected $model = Bill::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'BILL-' . now()->format('Ym') . '-';
        $billNumber = $prefix . str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        $billDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $subtotal = $this->faker->randomElement([1000000, 2500000, 5000000, 10000000, 25000000]);
        $taxRate = 11.00;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $discountAmount = 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        return [
            'bill_number' => $billNumber,
            'vendor_invoice_number' => $this->faker->optional()->bothify('INV-####'),
            'contact_id' => Contact::factory()->supplier(),
            'bill_date' => $billDate,
            'due_date' => (clone $billDate)->modify('+30 days'),
            'description' => $this->faker->optional()->sentence(),
            'reference' => $this->faker->optional()->bothify('PO-####'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => Bill::STATUS_DRAFT,
            'journal_entry_id' => null,
            'payable_account_id' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_DRAFT,
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_RECEIVED,
        ]);
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $partialPayment = (int) ($attributes['total_amount'] * 0.5);
            return [
                'status' => Bill::STATUS_PARTIAL,
                'paid_amount' => $partialPayment,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_PAID,
            'paid_amount' => $attributes['total_amount'],
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_OVERDUE,
            'bill_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Bill::STATUS_CANCELLED,
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function withPayableAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'payable_account_id' => $account->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withoutTax(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'tax_rate' => 0,
                'tax_amount' => 0,
                'total_amount' => $attributes['subtotal'] - $attributes['discount_amount'],
            ];
        });
    }
}
