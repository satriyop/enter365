<?php

namespace Database\Factories\Pos;

use App\Enums\Pos\PosTenderType;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleTender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSaleTender>
 */
class PosSaleTenderFactory extends Factory
{
    protected $model = PosSaleTender::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_id' => PosSale::factory(),
            'type' => PosTenderType::Cash,
            'amount' => 111_00,
        ];
    }

    public function qris(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PosTenderType::Qris,
        ]);
    }
}
