<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    authenticatedAdmin();
});

describe('Stock transfer documents', function () {
    it('creates a draft internal transfer with multiple lines', function () {
        $from = Warehouse::factory()->create(['name' => 'Gudang A']);
        $to = Warehouse::factory()->create(['name' => 'Gudang B']);
        $first = Product::factory()->create(['track_inventory' => true, 'unit' => 'kg']);
        $second = Product::factory()->create(['track_inventory' => true, 'unit' => 'pcs']);

        $response = $this->postJson('/api/v1/stock-transfers', [
            'operation_type' => 'internal',
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'scheduled_date' => now()->toDateString(),
            'source_document' => 'PO-100',
            'notes' => 'Pindah stok rutin',
            'items' => [
                ['product_id' => $first->id, 'quantity' => 4, 'unit' => 'kg'],
                ['product_id' => $second->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.operation_type', 'internal')
            ->assertJsonPath('data.source_document', 'PO-100')
            ->assertJsonCount(2, 'data.items');

        expect($response->json('data.transfer_number'))->toStartWith('TRF-');
    });

    it('lists transfer documents by status', function () {
        StockTransfer::factory()->create();
        StockTransfer::factory()->completed()->create();

        $this->getJson('/api/v1/stock-transfers?status=draft')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/stock-transfers?status=completed')->assertOk()->assertJsonCount(1, 'data');
    });

    it('confirms a draft internal transfer and moves stock', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 20]);
        ProductStock::factory()->forProduct($product)->inWarehouse($from)->create([
            'quantity' => 20,
            'average_cost' => 1000,
            'total_value' => 20_000,
        ]);

        $create = $this->postJson('/api/v1/stock-transfers', [
            'operation_type' => 'internal',
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ])->assertCreated();

        $id = $create->json('data.id');

        $this->postJson("/api/v1/stock-transfers/{$id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $from->id,
            'quantity' => 15,
        ]);
        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $to->id,
            'quantity' => 5,
        ]);
    });

    it('cancels a draft transfer without moving stock', function () {
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $id = $this->postJson('/api/v1/stock-transfers', [
            'operation_type' => 'internal',
            'from_warehouse_id' => $from->id,
            'to_warehouse_id' => $to->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ])->json('data.id');

        $this->postJson("/api/v1/stock-transfers/{$id}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });
});
