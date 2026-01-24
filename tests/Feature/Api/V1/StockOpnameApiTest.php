<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate user
    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Stock Opname CRUD', function () {

    it('can list all stock opnames', function () {
        StockOpname::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/stock-opnames');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter stock opnames by status', function () {
        StockOpname::factory()->counting()->count(3)->create();
        StockOpname::factory()->draft()->count(2)->create();

        $response = $this->getJson('/api/v1/stock-opnames?status=counting');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter stock opnames by warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        StockOpname::factory()->forWarehouse($warehouse)->count(2)->create();
        StockOpname::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/stock-opnames?warehouse_id={$warehouse->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a stock opname', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->postJson('/api/v1/stock-opnames', [
            'warehouse_id' => $warehouse->id,
            'opname_date' => '2024-12-25',
            'name' => 'Year End Stock Count',
            'notes' => 'Annual inventory count',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse_id', $warehouse->id)
            ->assertJsonPath('data.name', 'Year End Stock Count')
            ->assertJsonPath('data.status.value', 'draft');
    });

    it('can show a stock opname', function () {
        $opname = StockOpname::factory()->create();

        $response = $this->getJson("/api/v1/stock-opnames/{$opname->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $opname->id)
            ->assertJsonPath('data.opname_number', $opname->opname_number);
    });

    it('can update a stock opname in draft status', function () {
        $opname = StockOpname::factory()->draft()->create();

        $response = $this->putJson("/api/v1/stock-opnames/{$opname->id}", [
            'name' => 'Updated Name',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update a stock opname in completed status', function () {
        $opname = StockOpname::factory()->completed()->create();

        $response = $this->putJson("/api/v1/stock-opnames/{$opname->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(422);
    });

    it('can delete a stock opname in draft status', function () {
        $opname = StockOpname::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/stock-opnames/{$opname->id}");

        $response->assertOk();
        $this->assertSoftDeleted('stock_opnames', ['id' => $opname->id]);
    });

    it('cannot delete a stock opname in counting status', function () {
        $opname = StockOpname::factory()->counting()->create();

        $response = $this->deleteJson("/api/v1/stock-opnames/{$opname->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('stock_opnames', ['id' => $opname->id]);
    });

});

describe('Stock Opname Item Management', function () {

    it('can generate items from warehouse stock', function () {
        $warehouse = Warehouse::factory()->create();
        $products = Product::factory()->count(3)->create(['track_inventory' => true]);

        // Create stock for each product
        foreach ($products as $product) {
            ProductStock::create([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'quantity' => 100,
                'average_cost' => 50000,
                'total_value' => 5000000,
            ]);
        }

        $opname = StockOpname::factory()->forWarehouse($warehouse)->draft()->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/generate-items");

        $response->assertOk()
            ->assertJsonPath('data.total_items', 3);
    });

    it('can add an item manually', function () {
        $opname = StockOpname::factory()->draft()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/items", [
            'product_id' => $product->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.product_id', $product->id);
    });

    it('cannot add a service product', function () {
        $opname = StockOpname::factory()->draft()->create();
        $product = Product::factory()->create(['track_inventory' => false]);

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/items", [
            'product_id' => $product->id,
        ]);

        $response->assertStatus(409); // Business rule violation
    });

    it('can update item with counted quantity', function () {
        $opname = StockOpname::factory()->counting()->create();
        $item = StockOpnameItem::factory()->forStockOpname($opname)->create([
            'system_quantity' => 100,
            'system_cost' => 50000,
        ]);

        $response = $this->putJson("/api/v1/stock-opnames/{$opname->id}/items/{$item->id}", [
            'counted_quantity' => 95,
            'notes' => 'Some items damaged',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.actual_quantity', 95)
            ->assertJsonPath('data.difference_quantity', -5)
            ->assertJsonPath('data.notes', 'Some items damaged');
    });

    it('can remove an item in draft status', function () {
        $opname = StockOpname::factory()->draft()->create();
        $item = StockOpnameItem::factory()->forStockOpname($opname)->create();

        $response = $this->deleteJson("/api/v1/stock-opnames/{$opname->id}/items/{$item->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('stock_opname_items', ['id' => $item->id]);
    });

});

describe('Stock Opname Workflow', function () {

    it('can start counting', function () {
        $opname = StockOpname::factory()->draft()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->count(3)->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/start-counting");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'counting');
    });

    it('cannot start counting without items', function () {
        $opname = StockOpname::factory()->draft()->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/start-counting");

        $response->assertStatus(422);
    });

    it('can submit for review when all items counted', function () {
        $opname = StockOpname::factory()->counting()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->counted()->count(3)->create();
        $opname->updateTotals();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/submit-review");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'reviewed');
    });

    it('cannot submit for review with uncounted items', function () {
        $opname = StockOpname::factory()->counting()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->counted()->count(2)->create();
        StockOpnameItem::factory()->forStockOpname($opname)->create(); // Uncounted
        $opname->updateTotals();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/submit-review");

        $response->assertStatus(409); // Business rule violation (items missing counts)
    });

    it('can approve and apply adjustments', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        // Create initial stock
        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'average_cost' => 50000,
            'total_value' => 5000000,
        ]);

        $opname = StockOpname::factory()->forWarehouse($warehouse)->reviewed()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->forProduct($product)->create([
            'system_quantity' => 100,
            'system_cost' => 50000,
            'counted_quantity' => 95,
            'variance_quantity' => -5,
            'variance_value' => -250000,
            'counted_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        // Verify stock was adjusted
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(95);
    });

    it('can reject and return to counting', function () {
        $opname = StockOpname::factory()->reviewed()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->counted()->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/reject", [
            'reason' => 'Need to recount items in section B',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'counting');
    });

    it('can cancel a stock opname', function () {
        $opname = StockOpname::factory()->counting()->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel a completed stock opname', function () {
        $opname = StockOpname::factory()->completed()->create();

        $response = $this->postJson("/api/v1/stock-opnames/{$opname->id}/cancel");

        $response->assertStatus(422);
    });
});

describe('Stock Opname Reports', function () {

    it('can get variance report', function () {
        $opname = StockOpname::factory()->reviewed()->create();
        StockOpnameItem::factory()->forStockOpname($opname)->withSurplus(10)->count(2)->create();
        StockOpnameItem::factory()->forStockOpname($opname)->withShortage(5)->count(3)->create();
        $opname->updateTotals();

        $response = $this->getJson("/api/v1/stock-opnames/{$opname->id}/variance-report");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'stock_opname',
                    'summary' => [
                        'total_items',
                        'items_with_variance',
                        'items_with_surplus',
                        'items_with_shortage',
                    ],
                    'variances',
                ],
            ]);
    });

});
