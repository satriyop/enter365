<?php

namespace Database\Factories\Sales;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalesReturn>
 */
class SalesReturnFactory extends Factory
{
    protected $model = SalesReturn::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'return_number' => 'SR-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'invoice_id' => null,
            'contact_id' => Contact::factory()->customer(),
            'warehouse_id' => null,
            'return_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'reason' => $this->faker->randomElement([
                SalesReturn::REASON_DAMAGED,
                SalesReturn::REASON_WRONG_ITEM,
                SalesReturn::REASON_QUALITY_ISSUE,
                SalesReturn::REASON_CUSTOMER_REQUEST,
            ]),
            'notes' => $this->faker->optional()->sentence(),
            'subtotal' => 0,
            'tax_rate' => 11,
            'tax_amount' => 0,
            'total_amount' => 0,
            'status' => DocumentStatus::Draft,
        ];
    }

    /**
     * Draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Draft,
        ]);
    }

    /**
     * Submitted status.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Submitted,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Approved status.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Approved,
            'submitted_at' => now()->subHours(2),
            'approved_at' => now(),
        ]);
    }

    /**
     * Completed status.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Completed,
            'submitted_at' => now()->subDays(2),
            'approved_at' => now()->subDay(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Cancelled status.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Cancelled,
        ]);
    }

    /**
     * With invoice.
     */
    public function forInvoice(?Invoice $invoice = null): static
    {
        return $this->state(function (array $attributes) use ($invoice) {
            $inv = $invoice ?? Invoice::factory()->create();

            return [
                'invoice_id' => $inv->id,
                'contact_id' => $inv->contact_id,
            ];
        });
    }

    /**
     * For specific contact.
     */
    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }

    /**
     * With warehouse.
     */
    public function atWarehouse(?Warehouse $warehouse = null): static
    {
        return $this->state(function (array $attributes) use ($warehouse) {
            return [
                'warehouse_id' => $warehouse->id ?? Warehouse::factory(),
            ];
        });
    }

    /**
     * With totals.
     */
    public function withTotals(int $subtotal = 100000): static
    {
        return $this->state(function (array $attributes) use ($subtotal) {
            $taxRate = $attributes['tax_rate'] ?? 11;
            $taxAmount = (int) round($subtotal * ($taxRate / 100));

            return [
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total_amount' => $subtotal + $taxAmount,
            ];
        });
    }
}
