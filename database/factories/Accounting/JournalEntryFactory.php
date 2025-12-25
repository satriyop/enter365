<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    protected $model = JournalEntry::class;

    private static int $sequenceNumber = 0;

    public function definition(): array
    {
        self::$sequenceNumber++;
        $prefix = 'JE-' . now()->format('Ym') . '-';
        $entryNumber = $prefix . str_pad((string) self::$sequenceNumber, 4, '0', STR_PAD_LEFT);

        return [
            'entry_number' => $entryNumber,
            'entry_date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'description' => $this->faker->sentence(),
            'reference' => $this->faker->optional()->bothify('REF-####'),
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => null,
            'fiscal_period_id' => null,
            'is_posted' => false,
            'is_reversed' => false,
            'reversed_by_id' => null,
            'reversal_of_id' => null,
            'created_by' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_posted' => true,
        ]);
    }

    public function reversed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_reversed' => true,
        ]);
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source_type' => JournalEntry::SOURCE_MANUAL,
            'source_id' => null,
        ]);
    }

    public function forFiscalPeriod(FiscalPeriod $period): static
    {
        return $this->state(fn (array $attributes) => [
            'fiscal_period_id' => $period->id,
            'entry_date' => $this->faker->dateTimeBetween($period->start_date, $period->end_date),
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }
}
