<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductVendorPricelist;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    authenticatedAdmin();
});

describe('PO line vendor pricelist', function () {
    it('defaults unit_price from priceForVendor when omitted', function () {
        $vendor = Contact::factory()->vendor()->create();
        $product = Product::factory()->create(['purchase_price' => 80_000]);
        ProductVendorPricelist::factory()->create([
            'product_id' => $product->id,
            'contact_id' => $vendor->id,
            'min_qty' => 10,
            'price' => 75_000,
            'unit' => 'kg',
        ]);

        $belowMin = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $belowMin->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 80_000);

        $atMin = $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 10,
                    'unit' => 'kg',
                ],
            ],
        ]);

        $atMin->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 75_000);
    });

    it('keeps an explicit unit_price', function () {
        $vendor = Contact::factory()->vendor()->create();
        $product = Product::factory()->create(['purchase_price' => 80_000]);
        ProductVendorPricelist::factory()->create([
            'product_id' => $product->id,
            'contact_id' => $vendor->id,
            'min_qty' => 1,
            'price' => 75_000,
        ]);

        $this->postJson('/api/v1/purchase-orders', [
            'contact_id' => $vendor->id,
            'po_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 10,
                    'unit' => 'kg',
                    'unit_price' => 90_000,
                ],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.items.0.unit_price', 90_000);
    });
});
