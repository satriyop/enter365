<?php

namespace Database\Factories\Sales;

use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'INV-'.now()->format('Ym').'-';
        $invoiceNumber = $prefix.str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        $invoiceDate = $this->faker->dateTimeBetween('-1 month', 'now');
        $subtotal = $this->faker->randomElement([1000000, 2500000, 5000000, 10000000, 25000000]);
        $taxRate = 11.00;
        $taxAmount = (int) round($subtotal * ($taxRate / 100));
        $discountAmount = 0;
        $totalAmount = $subtotal + $taxAmount - $discountAmount;

        return [
            'invoice_number' => $invoiceNumber,
            'contact_id' => Contact::factory()->customer(),
            'invoice_date' => $invoiceDate,
            'due_date' => (clone $invoiceDate)->modify('+30 days'),
            'description' => $this->faker->optional()->sentence(),
            'reference' => $this->faker->optional()->bothify('PO-####'),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'paid_amount' => 0,
            'status' => DocumentStatus::Draft,
            'journal_entry_id' => null,
            'receivable_account_id' => null,
            'created_by' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Sent,
        ]);
    }

    public function partial(): static
    {
        return $this->state(function (array $attributes) {
            $partialPayment = (int) ($attributes['total_amount'] * 0.5);

            return [
                'status' => DocumentStatus::Partial,
                'paid_amount' => $partialPayment,
            ];
        });
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Paid,
            'paid_amount' => $attributes['total_amount'],
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Overdue,
            'invoice_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function withReceivableAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'receivable_account_id' => $account->id,
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

    public function withDiscount(int $amount): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            return [
                'discount_amount' => $amount,
                'total_amount' => $attributes['subtotal'] + $attributes['tax_amount'] - $amount,
            ];
        });
    }
}
