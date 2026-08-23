<?php

namespace Database\Factories\Pos;

use App\Models\Inventory\Product;
use App\Models\Pos\PosSession;
use App\Models\Pos\PosSessionHold;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSessionHold>
 */
class PosSessionHoldFactory extends Factory
{
    protected $model = PosSessionHold::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_session_id' => PosSession::factory(),
            'lines' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (PosSessionHold $hold): void {
            if ($hold->lines !== []) {
                return;
            }

            $hold->lines = [
                [
                    'product_id' => Product::factory()->create()->id,
                    'quantity' => 1,
                ],
            ];
        });
    }
}
