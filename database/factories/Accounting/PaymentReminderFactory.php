<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\PaymentReminder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentReminder>
 */
class PaymentReminderFactory extends Factory
{
    protected $model = PaymentReminder::class;

    public function definition(): array
    {
        return [
            'remindable_type' => Invoice::class,
            'remindable_id' => Invoice::factory(),
            'contact_id' => Contact::factory()->customer(),
            'type' => PaymentReminder::TYPE_UPCOMING,
            'days_offset' => -7,
            'scheduled_date' => now()->addDays(7),
            'sent_date' => null,
            'status' => PaymentReminder::STATUS_PENDING,
            'channel' => PaymentReminder::CHANNEL_EMAIL,
            'message' => $this->faker->sentence(),
            'metadata' => null,
            'created_by' => null,
        ];
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentReminder::TYPE_UPCOMING,
            'days_offset' => -7,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentReminder::TYPE_OVERDUE,
            'days_offset' => 7,
        ]);
    }

    public function finalNotice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PaymentReminder::TYPE_FINAL_NOTICE,
            'days_offset' => 30,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentReminder::STATUS_PENDING,
            'sent_date' => null,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentReminder::STATUS_SENT,
            'sent_date' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentReminder::STATUS_FAILED,
        ]);
    }

    public function dueToday(): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_date' => now(),
            'status' => PaymentReminder::STATUS_PENDING,
        ]);
    }

    public function forInvoice(Invoice $invoice): static
    {
        return $this->state(fn (array $attributes) => [
            'remindable_type' => Invoice::class,
            'remindable_id' => $invoice->id,
            'contact_id' => $invoice->contact_id,
        ]);
    }

    public function viaEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => PaymentReminder::CHANNEL_EMAIL,
        ]);
    }

    public function viaSms(): static
    {
        return $this->state(fn (array $attributes) => [
            'channel' => PaymentReminder::CHANNEL_SMS,
        ]);
    }
}
