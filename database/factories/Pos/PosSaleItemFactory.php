<?php

namespace Database\Factories\Pos;

use App\Models\Inventory\Product;
use App\Models\Pos\PosSale;
use App\Models\Pos\PosSaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PosSaleItem>
 */
class PosSaleItemFactory extends Factory
{
    protected $model = PosSaleItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pos_sale_id' => PosSale::factory(),
            'product_id' => Product::factory(),
            'quantity' => 1,
            'unit_price_inclusive' => 111_00,
            'payable_amount' => 111_00,
            'dpp_amount' => 100_00,
            'ppn_amount' => 11_00,
            'is_taxable' => true,
            'track_inventory' => true,
            'cogs_amount' => 50_00,
        ];
    }
}
