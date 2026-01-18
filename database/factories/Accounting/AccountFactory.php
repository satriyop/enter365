<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Random code ranges use x-9### to avoid conflicts with fixed codes (x-1xxx, x-2xxx).
     * Fixed codes: 1-1001, 1-1002, 1-1100, 1-1300, 2-1100, 2-1200, 3-2000, 4-1001, 4-2001, 5-1002, 5-2001
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('1-9###'),
            'name' => fake()->words(2, true),
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'description' => fake()->optional()->sentence(),
            'parent_id' => null,
            'is_active' => true,
            'is_system' => false,
            'opening_balance' => 0,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('1-9###'),
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('2-9###'),
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('3-9###'),
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('4-9###'),
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('5-9###'),
            'type' => Account::TYPE_EXPENSE,
            'subtype' => Account::SUBTYPE_OPERATING_EXPENSE,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_system' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withOpeningBalance(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'opening_balance' => $balance,
        ]);
    }

    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '1-1001',
            'name' => 'Kas',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'is_system' => true,
        ]);
    }

    public function bank(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '1-1002',
            'name' => 'Bank',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'is_system' => true,
        ]);
    }

    public function accountsReceivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '1-1100',
            'name' => 'Piutang Usaha',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'is_system' => true,
        ]);
    }

    public function accountsPayable(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '2-1100',
            'name' => 'Utang Usaha',
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'is_system' => true,
        ]);
    }

    public function salesRevenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '4-1001',
            'name' => 'Pendapatan Penjualan',
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
            'is_system' => true,
        ]);
    }

    public function taxPayable(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '2-1200',
            'name' => 'PPN Keluaran',
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
            'is_system' => true,
        ]);
    }

    public function taxReceivable(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => '1-1300',
            'name' => 'PPN Masukan',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'is_system' => true,
        ]);
    }

    public function downPaymentAsset(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => fake()->unique()->numerify('1-15##'),
            'name' => 'Uang Muka',
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
        ]);
    }
}
