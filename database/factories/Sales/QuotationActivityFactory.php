<?php

namespace Database\Factories\Sales;

use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotationActivity>
 */
class QuotationActivityFactory extends Factory
{
    protected $model = QuotationActivity::class;

    public function definition(): array
    {
        return [
            'quotation_id' => Quotation::factory(),
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement([
                QuotationActivity::TYPE_CALL,
                QuotationActivity::TYPE_EMAIL,
                QuotationActivity::TYPE_MEETING,
                QuotationActivity::TYPE_NOTE,
            ]),
            'contact_method' => $this->faker->optional()->randomElement([
                QuotationActivity::METHOD_PHONE,
                QuotationActivity::METHOD_WHATSAPP,
                QuotationActivity::METHOD_EMAIL,
            ]),
            'subject' => $this->faker->optional()->sentence(4),
            'description' => $this->faker->optional()->paragraph(),
            'activity_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'duration_minutes' => $this->faker->optional()->numberBetween(5, 120),
            'contact_person' => $this->faker->optional()->name(),
            'contact_phone' => $this->faker->optional()->phoneNumber(),
            'next_follow_up_at' => null,
            'follow_up_type' => null,
            'outcome' => null,
        ];
    }

    public function call(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuotationActivity::TYPE_CALL,
            'contact_method' => QuotationActivity::METHOD_PHONE,
            'duration_minutes' => $this->faker->numberBetween(5, 60),
        ]);
    }

    public function email(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuotationActivity::TYPE_EMAIL,
            'contact_method' => QuotationActivity::METHOD_EMAIL,
        ]);
    }

    public function meeting(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuotationActivity::TYPE_MEETING,
            'contact_method' => QuotationActivity::METHOD_VISIT,
            'duration_minutes' => $this->faker->numberBetween(30, 180),
        ]);
    }

    public function note(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuotationActivity::TYPE_NOTE,
            'contact_method' => null,
        ]);
    }

    public function whatsapp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => QuotationActivity::TYPE_WHATSAPP,
            'contact_method' => QuotationActivity::METHOD_WHATSAPP,
        ]);
    }

    public function forQuotation(Quotation $quotation): static
    {
        return $this->state(fn (array $attributes) => [
            'quotation_id' => $quotation->id,
        ]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function withFollowUp(int $daysFromNow = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'next_follow_up_at' => now()->addDays($daysFromNow),
            'follow_up_type' => $this->faker->randomElement([
                QuotationActivity::TYPE_CALL,
                QuotationActivity::TYPE_EMAIL,
            ]),
        ]);
    }

    public function positive(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => QuotationActivity::OUTCOME_POSITIVE,
        ]);
    }

    public function negative(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => QuotationActivity::OUTCOME_NEGATIVE,
        ]);
    }

    public function neutral(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => QuotationActivity::OUTCOME_NEUTRAL,
        ]);
    }

    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'outcome' => QuotationActivity::OUTCOME_NO_ANSWER,
        ]);
    }
}
