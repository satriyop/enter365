<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVendorPricelist;
use Database\Seeders\Demo\VendorPricelistDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('GET /products/{product}/price-for-vendor', function () {
    it('returns the vendor pricelist price when a line matches', function () {
        $vendor = Contact::factory()->vendor()->create();
        $product = Product::factory()->create(['purchase_price' => 80_000, 'selling_price' => 100_000]);
        ProductVendorPricelist::factory()->create([
            'product_id' => $product->id,
            'contact_id' => $vendor->id,
            'min_qty' => 10,
            'price' => 75_000,
        ]);

        $this->getJson("/api/v1/products/{$product->id}/price-for-vendor?contact_id={$vendor->id}&quantity=10")
            ->assertOk()
            ->assertJsonPath('data.price', 75_000)
            ->assertJsonPath('data.source', 'pricelist');

        $this->getJson("/api/v1/products/{$product->id}/price-for-vendor?contact_id={$vendor->id}&quantity=1")
            ->assertOk()
            ->assertJsonPath('data.price', 80_000)
            ->assertJsonPath('data.source', 'purchase_price');
    });

    it('falls back to purchase_price when the vendor has no line', function () {
        $vendor = Contact::factory()->vendor()->create();
        $other = Contact::factory()->vendor()->create();
        $product = Product::factory()->create(['purchase_price' => 80_000]);
        ProductVendorPricelist::factory()->create([
            'product_id' => $product->id,
            'contact_id' => $other->id,
            'min_qty' => 1,
            'price' => 70_000,
        ]);

        $this->getJson("/api/v1/products/{$product->id}/price-for-vendor?contact_id={$vendor->id}&quantity=1")
            ->assertOk()
            ->assertJsonPath('data.price', 80_000)
            ->assertJsonPath('data.source', 'purchase_price');
    });
});

describe('VendorPricelistDemoSeeder', function () {
    it('creates a pricelist line for each supplier on a purchasable product', function () {
        $vendor = Contact::factory()->vendor()->create();
        $product = Product::factory()->create([
            'sku' => 'KT57-AIR',
            'purchase_price' => 2560,
            'is_purchasable' => true,
            'is_active' => true,
        ]);

        $this->seed(VendorPricelistDemoSeeder::class);

        $line = ProductVendorPricelist::query()
            ->where('product_id', $product->id)
            ->where('contact_id', $vendor->id)
            ->where('min_qty', 1)
            ->first();

        expect($line)->not->toBeNull()
            ->and($line->price)->toBe(2432)
            ->and($product->fresh()->vendorPriceSource($vendor->id, 1))->toBe('pricelist')
            ->and($product->fresh()->priceForVendor($vendor->id, 1))->toBe(2432);

        $this->seed(VendorPricelistDemoSeeder::class);

        expect(ProductVendorPricelist::query()->count())->toBe(1);
    });
});
