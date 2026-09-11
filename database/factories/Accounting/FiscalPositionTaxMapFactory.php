<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\FiscalPosition;
use App\Models\Accounting\FiscalPositionTaxMap;
use App\Models\Tax\TaxRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalPositionTaxMap>
 */
class FiscalPositionTaxMapFactory extends Factory
{
    protected $model = FiscalPositionTaxMap::class;

    public function definition(): array
    {
        return [
            'fiscal_position_id' => FiscalPosition::factory(),
            'source_tax_record_id' => TaxRecord::factory(),
            'dest_tax_record_id' => TaxRecord::factory(),
        ];
    }

    public function exempt(): static
    {
        return $this->state(fn (): array => ['dest_tax_record_id' => null]);
    }
}
