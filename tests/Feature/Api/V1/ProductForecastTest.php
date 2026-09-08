<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Product forecast, combo, and routes', function () {
    it('creates a combo product that does not track inventory', function () {
        $response = $this->postJson('/api/v1/products', [
            'name' => 'Kopi + roti',
            'type' => 'combo',
            'unit' => 'paket',
            'purchase_price' => 0,
            'selling_price' => 35_000,
            'procurement_type' => 'buy',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'combo')
            ->assertJsonPath('data.type_label', 'Combo')
            ->assertJsonPath('data.track_inventory', false)
            ->assertJsonPath('data.procurement_type', 'buy');
    });

    it('exposes incoming, outgoing, and forecasted qty beside on hand', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 10,
        ]);

        ProductStock::factory()->forProduct($product)->create([
            'quantity' => 10,
            'reserved_quantity' => 2,
        ]);

        $po = PurchaseOrder::factory()->approved()->create();
        PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $po->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'quantity_received' => 0,
        ]);

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('data.current_stock', 10)
            ->assertJsonPath('data.incoming_qty', 5)
            ->assertJsonPath('data.outgoing_qty', 2)
            ->assertJsonPath('data.forecasted_qty', 13);

        $list = $this->getJson('/api/v1/products?search='.$product->sku);
        $row = collect($list->json('data'))->firstWhere('id', $product->id);

        expect($row['forecasted_qty'])->toBe(13)
            ->and($row['incoming_qty'])->toBe(5)
            ->and($row['outgoing_qty'])->toBe(2);
    });
});
