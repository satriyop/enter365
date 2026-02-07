<?php

namespace Database\Factories\Purchasing;

use App\Models\Accounting\Account;
use App\Models\Purchasing\GoodsReceiptNote;
use App\Models\Purchasing\LandedCost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LandedCost>
 */
class LandedCostFactory extends Factory
{
    protected $model = LandedCost::class;

    public function definition(): array
    {
        $date = now()->format('Ymd');
        $unique = $this->faker->unique()->numberBetween(1, 9999);

        return [
            'landed_cost_number' => "LC-{$date}-".str_pad((string) $unique, 4, '0', STR_PAD_LEFT),
            'goods_receipt_note_id' => GoodsReceiptNote::factory()->completed(),
            'description' => $this->faker->sentence(),
            'cost_type' => $this->faker->randomElement(['shipping', 'insurance', 'customs', 'other']),
            'total_amount' => $this->faker->numberBetween(100000, 5000000),
            'allocation_method' => 'by_value',
            'expense_account_id' => Account::factory(),
            'status' => 'draft',
            'created_by' => User::factory(),
        ];
    }

    public function shipping(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => 'shipping',
            'description' => 'Ongkos angkut pembelian',
        ]);
    }

    public function insurance(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost_type' => 'insurance',
            'description' => 'Asuransi pengiriman',
        ]);
    }

    public function byQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'allocation_method' => 'by_quantity',
        ]);
    }

    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'applied',
        ]);
    }

    public function forGoodsReceiptNote(GoodsReceiptNote $grn): static
    {
        return $this->state(fn (array $attributes) => [
            'goods_receipt_note_id' => $grn->id,
        ]);
    }
}
