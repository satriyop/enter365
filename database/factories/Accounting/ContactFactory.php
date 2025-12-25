<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'code' => 'C-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->company(),
            'type' => Contact::TYPE_CUSTOMER,
            'email' => $this->faker->unique()->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'province' => $this->faker->randomElement(['DKI Jakarta', 'Jawa Barat', 'Jawa Tengah', 'Jawa Timur', 'Banten']),
            'postal_code' => $this->faker->postcode(),
            'npwp' => $this->faker->optional(0.7)->numerify('##.###.###.#-###.###'),
            'nik' => null,
            'credit_limit' => $this->faker->randomElement([0, 5000000, 10000000, 25000000, 50000000]),
            'payment_term_days' => $this->faker->randomElement([7, 14, 30, 45, 60]),
            'is_active' => true,
        ];
    }

    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contact::TYPE_CUSTOMER,
        ]);
    }

    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contact::TYPE_SUPPLIER,
        ]);
    }

    public function both(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contact::TYPE_BOTH,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withNpwp(): static
    {
        return $this->state(fn (array $attributes) => [
            'npwp' => $this->faker->numerify('##.###.###.#-###.###'),
        ]);
    }

    public function individual(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'nik' => $this->faker->numerify('################'),
            'npwp' => null,
        ]);
    }
}
