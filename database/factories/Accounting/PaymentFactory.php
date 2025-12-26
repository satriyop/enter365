<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    private static int $receiveSequence = 0;

    private static int $sendSequence = 0;

    public function definition(): array
    {
        self::$receiveSequence++;
        $prefix = 'RCV-'.now()->format('Ym').'-';
        $paymentNumber = $prefix.str_pad((string) self::$receiveSequence, 4, '0', STR_PAD_LEFT);
        $type = Payment::TYPE_RECEIVE;

        return [
            'payment_number' => $paymentNumber,
            'type' => $type,
            'contact_id' => Contact::factory()->customer(),
            'payment_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'amount' => $this->faker->randomElement([1000000, 2500000, 5000000, 10000000]),
            'payment_method' => Payment::METHOD_TRANSFER,
            'reference' => $this->faker->optional()->bothify('TRF-######'),
            'notes' => $this->faker->optional()->sentence(),
            'cash_account_id' => Account::factory()->bank(),
            'journal_entry_id' => null,
            'payable_type' => null,
            'payable_id' => null,
            'is_voided' => false,
            'created_by' => null,
        ];
    }

    public function receive(): static
    {
        return $this->state(function (array $attributes) {
            self::$receiveSequence++;
            $prefix = 'RCV-'.now()->format('Ym').'-';
            $paymentNumber = $prefix.str_pad((string) self::$receiveSequence, 4, '0', STR_PAD_LEFT);

            return [
                'type' => Payment::TYPE_RECEIVE,
                'payment_number' => $paymentNumber,
                'contact_id' => Contact::factory()->customer(),
            ];
        });
    }

    public function send(): static
    {
        return $this->state(function (array $attributes) {
            self::$sendSequence++;
            $prefix = 'PAY-'.now()->format('Ym').'-';
            $paymentNumber = $prefix.str_pad((string) self::$sendSequence, 4, '0', STR_PAD_LEFT);

            return [
                'type' => Payment::TYPE_SEND,
                'payment_number' => $paymentNumber,
                'contact_id' => Contact::factory()->supplier(),
            ];
        });
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => Payment::METHOD_CASH,
        ]);
    }

    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => Payment::METHOD_TRANSFER,
        ]);
    }

    public function check(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => Payment::METHOD_CHECK,
            'reference' => 'CHK-'.$this->faker->numerify('######'),
        ]);
    }

    public function giro(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => Payment::METHOD_GIRO,
            'reference' => 'GIRO-'.$this->faker->numerify('######'),
        ]);
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_voided' => true,
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    public function withCashAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'cash_account_id' => $account->id,
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    public function withAmount(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'cash_account_id' => $account->id,
        ]);
    }

    public function forInvoice(\App\Models\Accounting\Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'payable_type' => \App\Models\Accounting\Invoice::class,
            'payable_id' => $invoice->id,
        ]);
    }

    public function forBill(\App\Models\Accounting\Bill $bill): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'payable_type' => \App\Models\Accounting\Bill::class,
            'payable_id' => $bill->id,
        ]);
    }
}
