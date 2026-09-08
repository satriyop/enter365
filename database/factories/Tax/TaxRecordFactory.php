<?php

namespace Database\Factories\Tax;

use App\Models\Tax\TaxRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRecord>
 */
class TaxRecordFactory extends Factory
{
    protected $model = TaxRecord::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('TAX-###')),
            'name' => 'PPN 11%',
            'rate' => 11.00,
            'applicability' => TaxRecord::APPLICABILITY_BOTH,
            'is_active' => true,
        ];
    }

    public function sales(): static
    {
        return $this->state(fn () => [
            'code' => 'PPN-SALES',
            'name' => 'PPN Keluaran 11%',
            'applicability' => TaxRecord::APPLICABILITY_SALES,
        ]);
    }

    public function purchase(): static
    {
        return $this->state(fn () => [
            'code' => 'PPN-PURCHASE',
            'name' => 'PPN Masukan 11%',
            'applicability' => TaxRecord::APPLICABILITY_PURCHASE,
        ]);
    }
}
