<?php

namespace Database\Factories\Pos;

use App\Enums\Pos\PosSaleStatus;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSale>
 */
class PosSaleFactory extends Factory
{
    protected $model = PosSale::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payable = 111_00;

        return [
            'sale_number' => 'POS-'.now()->format('Ym').'-'.$this->faker->unique()->numerify('####'),
            'pos_session_id' => PosSession::factory(),
            'status' => PosSaleStatus::Completed,
            'subtotal_amount' => $payable,
            'dpp_amount' => 100_00,
            'ppn_amount' => 11_00,
            'payable_amount' => $payable,
            'cash_received_amount' => $payable,
            'change_amount' => 0,
            'sold_at' => now(),
            'created_by' => User::factory(),
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PosSaleStatus::Voided,
            'voided_at' => now(),
            'voided_by' => User::factory(),
            'void_reason' => 'Salah barang',
        ]);
    }
}
