<?php

namespace Database\Factories\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\WriteOff;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WriteOff>
 */
class WriteOffFactory extends Factory
{
    protected $model = WriteOff::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory()->sent(),
            'amount' => $this->faker->randomElement([500000, 1000000, 2500000]),
            'base_amount' => fn (array $attributes) => $attributes['amount'],
            'reason' => $this->faker->sentence(),
            'write_off_date' => now()->toDateString(),
            'journal_entry_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'invoice_id' => $invoice->id,
            'amount' => $invoice->getOutstandingAmount(),
            'base_amount' => $invoice->currency !== 'IDR'
                ? (int) round($invoice->getOutstandingAmount() * (float) $invoice->exchange_rate)
                : $invoice->getOutstandingAmount(),
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
