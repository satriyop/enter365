<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Purchasing\PurchaseOrder;
use App\Models\Purchasing\PurchaseOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

it('exposes free to use, incoming, and outgoing on the stock list', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 10]);

    ProductStock::factory()->forProduct($product)->inWarehouse($warehouse)->create([
        'quantity' => 10,
        'reserved_quantity' => 3,
        'average_cost' => 1000,
        'total_value' => 10_000,
    ]);

    $po = PurchaseOrder::factory()->approved()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_id' => $product->id,
        'quantity' => 8,
        'quantity_received' => 0,
    ]);

    $response = $this->getJson('/api/v1/inventory/stock-levels');

    $response->assertOk();
    $row = collect($response->json('data'))->firstWhere('product_id', $product->id);

    expect($row['quantity'])->toBe(10)
        ->and($row['reserved_quantity'])->toBe(3)
        ->and($row['free_to_use'])->toBe(7)
        ->and($row['incoming_qty'])->toBe(8)
        ->and($row['outgoing_qty'])->toBe(3);
});
