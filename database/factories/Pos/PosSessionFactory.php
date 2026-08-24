<?php

namespace Database\Factories\Pos;

use App\Enums\Pos\PosSessionStatus;
use App\Models\Accounting\Account;
use App\Models\Inventory\Warehouse;
use App\Models\Pos\PosSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSession>
 */
class PosSessionFactory extends Factory
{
    protected $model = PosSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'session_number' => 'PSS-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'status' => PosSessionStatus::Open,
            'warehouse_id' => Warehouse::factory(),
            'cash_account_id' => Account::factory(),
            'qris_account_id' => Account::factory(),
            'pricing_mode' => \App\Enums\Pos\PosPricingMode::Inclusive,
            'service_rate' => 0,
            'tax_add_rate' => 0,
            'tax_add_name' => 'PBJT',
            'opening_cash_amount' => 200_000_00,
            'opened_by' => User::factory(),
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PosSessionStatus::Closed,
            'expected_cash_amount' => $attributes['opening_cash_amount'] ?? 0,
            'counted_cash_amount' => $attributes['opening_cash_amount'] ?? 0,
            'cash_difference_amount' => 0,
            'closed_by' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
