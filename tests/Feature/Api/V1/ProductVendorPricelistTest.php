<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Product vendor pricelists', function () {
    it('stores vendor pricelist lines on a product', function () {
        $vendor = Contact::factory()->vendor()->create(['name' => 'PT Kopi Aceh']);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Green beans',
            'type' => 'product',
            'unit' => 'kg',
            'purchase_price' => 80_000,
            'selling_price' => 120_000,
            'purchase_control_policy' => 'ordered',
            'purchase_description' => 'Arabica Gayo grade 1',
            'vendor_pricelists' => [
                [
                    'contact_id' => $vendor->id,
                    'min_qty' => 10,
                    'unit' => 'kg',
                    'price' => 75_000,
                    'currency' => 'IDR',
                    'lead_time_days' => 5,
                    'vendor_product_code' => 'GY-1',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.purchase_control_policy', 'ordered')
            ->assertJsonPath('data.purchase_description', 'Arabica Gayo grade 1')
            ->assertJsonPath('data.vendor_pricelists.0.contact_id', $vendor->id)
            ->assertJsonPath('data.vendor_pricelists.0.price', 75_000)
            ->assertJsonPath('data.vendor_pricelists.0.min_qty', 10)
            ->assertJsonPath('data.vendor_pricelists.0.lead_time_days', 5)
            ->assertJsonPath('data.vendor_pricelists.0.vendor_product_code', 'GY-1')
            ->assertJsonPath('data.vendor_pricelists.0.contact.name', 'PT Kopi Aceh');

        $product = Product::query()->findOrFail($response->json('data.id'));

        expect($product->default_supplier_id)->toBe($vendor->id)
            ->and($product->priceForVendor($vendor->id, 10))->toBe(75_000)
            ->and($product->priceForVendor($vendor->id, 1))->toBe(80_000)
            ->and($product->priceForVendor(null))->toBe(80_000);
    });

    it('replaces vendor pricelist lines on update', function () {
        $first = Contact::factory()->vendor()->create();
        $second = Contact::factory()->vendor()->create();
        $product = Product::factory()->create(['purchase_price' => 50_000]);

        $this->putJson("/api/v1/products/{$product->id}", [
            'vendor_pricelists' => [
                [
                    'contact_id' => $first->id,
                    'price' => 45_000,
                    'unit' => 'kg',
                    'min_qty' => 1,
                    'lead_time_days' => 3,
                ],
            ],
        ])->assertOk()->assertJsonPath('data.vendor_pricelists.0.contact_id', $first->id);

        $updated = $this->putJson("/api/v1/products/{$product->id}", [
            'vendor_pricelists' => [
                [
                    'contact_id' => $second->id,
                    'price' => 40_000,
                    'unit' => 'kg',
                    'min_qty' => 25,
                    'currency' => 'IDR',
                    'lead_time_days' => 14,
                ],
            ],
        ]);

        $updated->assertOk()
            ->assertJsonCount(1, 'data.vendor_pricelists')
            ->assertJsonPath('data.vendor_pricelists.0.contact_id', $second->id)
            ->assertJsonPath('data.vendor_pricelists.0.price', 40_000);

        expect($product->fresh()->priceForVendor($second->id, 25))->toBe(40_000);
    });

    it('rejects vendor lines without a contact', function () {
        $this->postJson('/api/v1/products', [
            'name' => 'No vendor',
            'type' => 'product',
            'unit' => 'pcs',
            'purchase_price' => 1000,
            'selling_price' => 2000,
            'vendor_pricelists' => [
                ['price' => 900],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['vendor_pricelists.0.contact_id']);
    });
});
