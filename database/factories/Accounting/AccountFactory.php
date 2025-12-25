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

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('1-####'),
            'name' => $this->faker->words(2, true),
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
            'description' => $this->faker->optional()->sentence(),
            'parent_id' => null,
            'is_active' => true,
            'is_system' => false,
            'opening_balance' => 0,
        ];
    }

    public function asset(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->numerify('1-####'),
            'type' => Account::TYPE_ASSET,
            'subtype' => Account::SUBTYPE_CURRENT_ASSET,
        ]);
    }

    public function liability(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->numerify('2-####'),
            'type' => Account::TYPE_LIABILITY,
            'subtype' => Account::SUBTYPE_CURRENT_LIABILITY,
        ]);
    }

    public function equity(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->numerify('3-####'),
            'type' => Account::TYPE_EQUITY,
            'subtype' => Account::SUBTYPE_EQUITY,
        ]);
    }

    public function revenue(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->numerify('4-####'),
            'type' => Account::TYPE_REVENUE,
            'subtype' => Account::SUBTYPE_OPERATING_REVENUE,
        ]);
    }

    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $this->faker->unique()->numerify('5-####'),
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
}
