<?php

namespace Database\Factories\Sales;

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\DeliveryOrder;
use App\Models\Sales\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryOrder>
 */
class DeliveryOrderFactory extends Factory
{
    protected $model = DeliveryOrder::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'do_number' => 'DO-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'invoice_id' => null,
            'contact_id' => Contact::factory()->customer(),
            'warehouse_id' => null,
            'do_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'shipping_date' => null,
            'received_date' => null,
            'shipping_address' => $this->faker->optional()->address(),
            'shipping_method' => $this->faker->randomElement(DeliveryOrder::SHIPPING_METHODS),
            'tracking_number' => $this->faker->optional()->numerify('TRK########'),
            'driver_name' => $this->faker->optional()->name(),
            'vehicle_number' => $this->faker->optional()->bothify('B #### ???'),
            'notes' => $this->faker->optional()->sentence(),
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
     * Confirmed status.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * Shipped status.
     */
    public function shipped(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Shipped,
            'confirmed_at' => now()->subHours(2),
            'shipped_at' => now(),
            'shipping_date' => now()->toDateString(),
        ]);
    }

    /**
     * Delivered status.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentStatus::Delivered,
            'confirmed_at' => now()->subDays(2),
            'shipped_at' => now()->subDay(),
            'delivered_at' => now(),
            'shipping_date' => now()->subDay()->toDateString(),
            'received_date' => now()->toDateString(),
            'received_by' => $this->faker->name(),
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
     * With contact.
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
    public function fromWarehouse(?Warehouse $warehouse = null): static
    {
        return $this->state(function (array $attributes) use ($warehouse) {
            return [
                'warehouse_id' => $warehouse->id ?? Warehouse::factory(),
            ];
        });
    }

    /**
     * With shipping info.
     */
    public function withShippingInfo(): static
    {
        return $this->state(fn (array $attributes) => [
            'shipping_address' => $this->faker->address(),
            'shipping_method' => $this->faker->randomElement(DeliveryOrder::SHIPPING_METHODS),
            'driver_name' => $this->faker->name(),
            'vehicle_number' => $this->faker->bothify('B #### ???'),
        ]);
    }
}
