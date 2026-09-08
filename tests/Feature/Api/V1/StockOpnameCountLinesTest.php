<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Stock opname count lines', function () {
    it('creates an opname with product lines, book qty, counted qty, and difference', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'track_inventory' => true,
            'unit' => 'kg',
            'name' => 'Green beans',
        ]);
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'average_cost' => 10_000,
        ]);

        $response = $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Monthly count',
            'items' => [
                [
                    'product_id' => $product->id,
                    'counted_quantity' => 95,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.system_quantity', 100)
            ->assertJsonPath('data.items.0.counted_quantity', 95)
            ->assertJsonPath('data.items.0.actual_quantity', 95)
            ->assertJsonPath('data.items.0.difference_quantity', -5)
            ->assertJsonPath('data.items.0.product.unit', 'kg');
    });

    it('rejects duplicate products on the same opname', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                ['product_id' => $product->id, 'counted_quantity' => 1],
                ['product_id' => $product->id, 'counted_quantity' => 2],
            ],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['items.1.product_id']);
    });

    it('lists draft, counting, and completed opnames', function () {
        $warehouse = Warehouse::factory()->create();
        \App\Models\Inventory\StockOpname::factory()->draft()->forWarehouse($warehouse)->create();
        \App\Models\Inventory\StockOpname::factory()->counting()->forWarehouse($warehouse)->create();
        \App\Models\Inventory\StockOpname::factory()->completed()->forWarehouse($warehouse)->create();

        $this->getJson('/api/v1/stock-opnames?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stock-opnames?status=counting')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stock-opnames?status=completed')->assertOk()->assertJsonCount(1, 'data');
    });
});
