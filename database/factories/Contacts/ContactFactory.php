<?php

namespace Database\Factories\Contacts;

use App\Enums\PphCategory;
use App\Models\Contacts\Contact;
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
            'code' => 'C-'.$this->faker->unique()->numerify('####'),
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

    /**
     * Alias for supplier.
     */
    public function vendor(): static
    {
        return $this->supplier();
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

    /**
     * Subcontractor type.
     */
    public function subcontractor(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => Contact::TYPE_SUPPLIER,
            'is_subcontractor' => true,
            'subcontractor_services' => $this->faker->randomElements([
                'instalasi_listrik',
                'instalasi_panel',
                'instalasi_solar',
                'pekerjaan_sipil',
                'commissioning',
            ], $this->faker->numberBetween(1, 3)),
            'hourly_rate' => $this->faker->randomElement([75000, 100000, 125000, 150000]),
            'daily_rate' => $this->faker->randomElement([500000, 750000, 1000000, 1250000]),
        ]);
    }

    /**
     * PPh subject (domestic supplier).
     */
    public function pphSubject(PphCategory $category = PphCategory::Pph23Jasa): static
    {
        return $this->supplier()->withNpwp()->state(fn (array $attributes) => [
            'is_pph_subject' => true,
            'pph_category' => $category,
            'pph_rate' => null, // use default from enum/config
            'is_foreign_entity' => false,
        ]);
    }

    /**
     * PPh subject without NPWP (gets 200% surcharge).
     */
    public function pphSubjectNoNpwp(PphCategory $category = PphCategory::Pph23Jasa): static
    {
        return $this->supplier()->state(fn (array $attributes) => [
            'npwp' => null,
            'is_pph_subject' => true,
            'pph_category' => $category,
            'pph_rate' => null,
            'is_foreign_entity' => false,
        ]);
    }

    /**
     * Foreign entity supplier (PPh 26 at 20%).
     */
    public function foreignEntity(): static
    {
        return $this->supplier()->state(fn (array $attributes) => [
            'npwp' => null,
            'is_pph_subject' => true,
            'pph_category' => PphCategory::Pph26,
            'pph_rate' => null,
            'is_foreign_entity' => true,
        ]);
    }

    /**
     * With subcontractor rates.
     */
    public function withSubcontractorRates(int $hourlyRate, int $dailyRate): static
    {
        return $this->state(fn (array $attributes) => [
            'is_subcontractor' => true,
            'hourly_rate' => $hourlyRate,
            'daily_rate' => $dailyRate,
        ]);
    }
}
