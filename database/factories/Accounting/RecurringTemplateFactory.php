<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Contact;
use App\Models\Accounting\RecurringTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringTemplate>
 */
class RecurringTemplateFactory extends Factory
{
    protected $model = RecurringTemplate::class;

    public function definition(): array
    {
        $type = $this->faker->randomElement([RecurringTemplate::TYPE_INVOICE, RecurringTemplate::TYPE_BILL]);
        $contactType = $type === RecurringTemplate::TYPE_INVOICE ? 'customer' : 'supplier';

        return [
            'name' => 'Recurring ' . $this->faker->words(3, true),
            'type' => $type,
            'contact_id' => Contact::factory()->{$contactType}(),
            'frequency' => $this->faker->randomElement([
                RecurringTemplate::FREQUENCY_MONTHLY,
                RecurringTemplate::FREQUENCY_QUARTERLY,
                RecurringTemplate::FREQUENCY_YEARLY,
            ]),
            'interval' => 1,
            'start_date' => now()->addDays(rand(1, 30)),
            'end_date' => null,
            'next_generate_date' => now()->addDays(rand(1, 30)),
            'occurrences_limit' => $this->faker->optional(0.3)->numberBetween(6, 24),
            'occurrences_count' => 0,
            'description' => $this->faker->optional()->sentence(),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'tax_rate' => 11.00,
            'discount_amount' => 0,
            'early_discount_percent' => 0,
            'early_discount_days' => 0,
            'payment_term_days' => 30,
            'currency' => 'IDR',
            'items' => [
                [
                    'description' => $this->faker->sentence(4),
                    'quantity' => $this->faker->numberBetween(1, 10),
                    'unit' => $this->faker->randomElement(['unit', 'jam', 'bulan', 'paket']),
                    'unit_price' => $this->faker->randomElement([500000, 1000000, 2500000, 5000000]),
                ],
            ],
            'is_active' => true,
            'auto_post' => false,
            'auto_send' => false,
            'created_by' => null,
        ];
    }

    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RecurringTemplate::TYPE_INVOICE,
            'contact_id' => Contact::factory()->customer(),
        ]);
    }

    public function bill(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => RecurringTemplate::TYPE_BILL,
            'contact_id' => Contact::factory()->supplier(),
        ]);
    }

    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => RecurringTemplate::FREQUENCY_MONTHLY,
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'frequency' => RecurringTemplate::FREQUENCY_WEEKLY,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function autoPost(): static
    {
        return $this->state(fn (array $attributes) => [
            'auto_post' => true,
        ]);
    }

    public function dueToGenerate(): static
    {
        return $this->state(fn (array $attributes) => [
            'next_generate_date' => now()->subDay(),
            'is_active' => true,
        ]);
    }

    public function withLimit(int $limit): static
    {
        return $this->state(fn (array $attributes) => [
            'occurrences_limit' => $limit,
        ]);
    }

    public function forContact(Contact $contact): static
    {
        return $this->state(fn (array $attributes) => [
            'contact_id' => $contact->id,
        ]);
    }
}
