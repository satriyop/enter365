<?php

namespace Database\Factories\Inventory;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVendorPricelist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVendorPricelist>
 */
class ProductVendorPricelistFactory extends Factory
{
    protected $model = ProductVendorPricelist::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'contact_id' => Contact::factory()->vendor(),
            'min_qty' => 1,
            'unit' => 'kg',
            'price' => 50_000,
            'currency' => 'IDR',
            'lead_time_days' => 7,
            'vendor_product_code' => null,
        ];
    }
}
