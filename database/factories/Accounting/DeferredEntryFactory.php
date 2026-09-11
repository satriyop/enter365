<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\DeferredEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeferredEntry>
 */
class DeferredEntryFactory extends Factory
{
    protected $model = DeferredEntry::class;

    public function definition(): array
    {
        return [
            'kind' => DeferredEntry::KIND_EXPENSE,
            'code' => strtoupper(fake()->unique()->bothify('DEF-####')),
            'name' => 'Prepaid rent',
            'amount' => 1_200_000,
            'duration_months' => 12,
            'start_date' => now()->startOfMonth()->toDateString(),
            'deferred_account_id' => Account::factory()->asset(),
            'recognition_account_id' => Account::factory()->expense(),
            'counterpart_account_id' => Account::factory()->asset(),
            'status' => DeferredEntry::STATUS_DRAFT,
            'remaining_amount' => 0,
        ];
    }

    public function expense(): static
    {
        return $this->state(fn (): array => [
            'kind' => DeferredEntry::KIND_EXPENSE,
            'name' => 'Prepaid rent',
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (): array => [
            'kind' => DeferredEntry::KIND_REVENUE,
            'name' => 'Unearned rent',
        ]);
    }

    public function running(): static
    {
        return $this->state(fn (): array => [
            'status' => DeferredEntry::STATUS_RUNNING,
            'remaining_amount' => 1_200_000,
        ]);
    }
}
