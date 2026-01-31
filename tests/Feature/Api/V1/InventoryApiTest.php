<?php

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
});

describe('Inventory Stock In', function () {

    it('can record stock in', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create([
            'track_inventory' => true,
            'purchase_price' => 100000,
            'current_stock' => 0,
        ]);

        $response = $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'unit_cost' => 100000,
            'notes' => 'Pembelian pertama',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'in')
            ->assertJsonPath('data.quantity', 10);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

        expect($product->fresh()->current_stock)->toBe(10);
    });

    it('uses default warehouse when not specified', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_cost' => 50000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse.id', $warehouse->id);
    });

    it('fails for product not tracking inventory', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->service()->create();

        $response = $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'unit_cost' => 100000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Produk ini tidak melacak inventori.');
    });

    it('fails when no default warehouse exists', function () {
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => 100000,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Tidak ada gudang default. Silakan buat gudang terlebih dahulu.');
    });

    it('calculates weighted average cost correctly', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 100000]);

        // First stock in: 10 @ 100,000
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'unit_cost' => 100000,
        ]);

        // Second stock in: 10 @ 200,000
        $this->postJson('/api/v1/inventory/stock-in', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'unit_cost' => 200000,
        ]);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();

        // Weighted average: (10*100000 + 10*200000) / 20 = 150,000
        expect($stock->quantity)->toBe(20)
            ->and($stock->average_cost)->toBe(150000);
    });
});

describe('Inventory Stock Out', function () {

    it('can record stock out', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 20]);

        // Setup initial stock
        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 20, 'average_cost' => 100000, 'total_value' => 2000000]);

        $response = $this->postJson('/api/v1/inventory/stock-out', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'notes' => 'Penjualan',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'out')
            ->assertJsonPath('data.quantity', -5);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 15,
        ]);
    });

    it('fails when insufficient stock', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 5]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 5, 'average_cost' => 100000, 'total_value' => 500000]);

        $response = $this->postJson('/api/v1/inventory/stock-out', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Stok tidak mencukupi. Tersedia: 5, diminta: 10']);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/inventory/stock-out', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    });
});

describe('Inventory Adjustment', function () {

    it('can adjust stock up', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 10]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 10, 'average_cost' => 100000, 'total_value' => 1000000]);

        $response = $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'new_quantity' => 15,
            'notes' => 'Stock opname',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'adjustment')
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.quantity_after', 15);
    });

    it('can adjust stock down', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 10]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 10, 'average_cost' => 100000, 'total_value' => 1000000]);

        $response = $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'new_quantity' => 8,
            'notes' => 'Barang rusak',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quantity', -2)
            ->assertJsonPath('data.quantity_after', 8);
    });

    it('can adjust unit cost', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 10]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 10, 'average_cost' => 100000, 'total_value' => 1000000]);

        $response = $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'new_quantity' => 10,
            'new_unit_cost' => 120000,
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'average_cost' => 120000,
            'total_value' => 1200000,
        ]);
    });
});

describe('Inventory Transfer', function () {

    it('can transfer stock between warehouses', function () {
        $warehouse1 = Warehouse::factory()->create(['name' => 'Gudang A']);
        $warehouse2 = Warehouse::factory()->create(['name' => 'Gudang B']);
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 20]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse1)
            ->create(['quantity' => 20, 'average_cost' => 100000, 'total_value' => 2000000]);

        $response = $this->postJson('/api/v1/inventory/transfer', [
            'product_id' => $product->id,
            'from_warehouse_id' => $warehouse1->id,
            'to_warehouse_id' => $warehouse2->id,
            'quantity' => 5,
            'notes' => 'Transfer antar gudang',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.out.type', 'transfer_out')
            ->assertJsonPath('data.in.type', 'transfer_in');

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse1->id,
            'quantity' => 15,
        ]);

        $this->assertDatabaseHas('product_stocks', [
            'product_id' => $product->id,
            'warehouse_id' => $warehouse2->id,
            'quantity' => 5,
        ]);
    });

    it('fails when insufficient stock for transfer', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 5]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse1)
            ->create(['quantity' => 5, 'average_cost' => 100000, 'total_value' => 500000]);

        $response = $this->postJson('/api/v1/inventory/transfer', [
            'product_id' => $product->id,
            'from_warehouse_id' => $warehouse1->id,
            'to_warehouse_id' => $warehouse2->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(422);
    });

    it('cannot transfer to same warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson('/api/v1/inventory/transfer', [
            'product_id' => $product->id,
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_warehouse_id']);
    });
});

describe('Inventory Reports', function () {

    it('can get movements list', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        InventoryMovement::factory()
            ->count(5)
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create();

        $response = $this->getJson('/api/v1/inventory/movements');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter movements by product', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product1 = Product::factory()->create(['track_inventory' => true]);
        $product2 = Product::factory()->create(['track_inventory' => true]);

        InventoryMovement::factory()->count(3)->forProduct($product1)->inWarehouse($warehouse)->create();
        InventoryMovement::factory()->count(2)->forProduct($product2)->inWarehouse($warehouse)->create();

        $response = $this->getJson("/api/v1/inventory/movements?product_id={$product1->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter movements by type', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        InventoryMovement::factory()->count(3)->stockIn()->forProduct($product)->inWarehouse($warehouse)->create();
        InventoryMovement::factory()->count(2)->stockOut()->forProduct($product)->inWarehouse($warehouse)->create();

        $response = $this->getJson('/api/v1/inventory/movements?type=in');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can get stock card for product', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true, 'current_stock' => 10]);

        InventoryMovement::factory()
            ->count(5)
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->create();

        $response = $this->getJson("/api/v1/inventory/stock-card/{$product->id}");

        $response->assertOk()
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonCount(5, 'movements');
    });

    it('can get stock valuation', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product1 = Product::factory()->create(['track_inventory' => true]);
        $product2 = Product::factory()->create(['track_inventory' => true]);

        ProductStock::factory()
            ->forProduct($product1)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 10, 'average_cost' => 100000, 'total_value' => 1000000]);
        ProductStock::factory()
            ->forProduct($product2)
            ->inWarehouse($warehouse)
            ->create(['quantity' => 20, 'average_cost' => 50000, 'total_value' => 1000000]);

        $response = $this->getJson('/api/v1/inventory/valuation');

        $response->assertOk()
            ->assertJsonPath('summary.total_items', 2)
            ->assertJsonPath('summary.total_value', 2000000);
    });

    it('can get inventory summary', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product1 = Product::factory()->create([
            'track_inventory' => true,
            'min_stock' => 10,
            'current_stock' => 5, // low stock
            'is_active' => true,
        ]);
        $product2 = Product::factory()->create([
            'track_inventory' => true,
            'min_stock' => 5,
            'current_stock' => 0, // out of stock
            'is_active' => true,
        ]);

        ProductStock::factory()->forProduct($product1)->inWarehouse($warehouse)
            ->create(['quantity' => 5, 'average_cost' => 100000, 'total_value' => 500000]);
        ProductStock::factory()->forProduct($product2)->inWarehouse($warehouse)
            ->create(['quantity' => 0, 'average_cost' => 100000, 'total_value' => 0]);

        $response = $this->getJson('/api/v1/inventory/summary');

        $response->assertOk()
            ->assertJsonPath('summary.low_stock_count', 2)
            ->assertJsonPath('summary.out_of_stock_count', 1);
    });

    it('can get movement summary for period', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        InventoryMovement::factory()
            ->count(3)
            ->stockIn()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->onDate(now()->toDateString())
            ->create();

        $response = $this->getJson('/api/v1/inventory/movement-summary?'.http_build_query([
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->endOfMonth()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonPath('summary.stock_in.count', 3);
    });

    it('can get stock levels', function () {
        $warehouse = Warehouse::factory()->default()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        ProductStock::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->withQuantity(10)
            ->create();

        $response = $this->getJson('/api/v1/inventory/stock-levels');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });
});

describe('Inventory Validation', function () {

    it('validates stock in required fields', function () {
        $response = $this->postJson('/api/v1/inventory/stock-in', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity', 'unit_cost']);
    });

    it('validates stock out required fields', function () {
        $response = $this->postJson('/api/v1/inventory/stock-out', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'quantity']);
    });

    it('validates adjustment required fields', function () {
        $response = $this->postJson('/api/v1/inventory/adjust', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'new_quantity']);
    });

    it('validates transfer required fields', function () {
        $response = $this->postJson('/api/v1/inventory/transfer', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id', 'from_warehouse_id', 'to_warehouse_id', 'quantity']);
    });

    it('validates movement summary requires dates', function () {
        $response = $this->getJson('/api/v1/inventory/movement-summary');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    });
});
