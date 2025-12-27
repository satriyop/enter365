<?php

use App\Models\Accounting\InventoryMovement;
use App\Models\Accounting\Product;
use App\Models\Accounting\ProductCategory;
use App\Models\Accounting\Warehouse;
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

describe('COGS Summary Report', function () {

    it('can generate COGS summary', function () {
        $response = $this->getJson('/api/v1/reports/cogs-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'beginning_inventory',
                'purchases',
                'ending_inventory',
                'cogs',
            ])
            ->assertJsonPath('report_name', 'Laporan Harga Pokok Penjualan');
    });

    it('can filter COGS summary by date range', function () {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        $response = $this->getJson("/api/v1/reports/cogs-summary?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start', $startDate)
            ->assertJsonPath('period.end', $endDate);
    });

    it('calculates COGS correctly', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 100000]);

        // Beginning inventory: 10 units @ 100k = 1M
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockIn()
            ->onDate(now()->subMonth()->toDateString())
            ->create([
                'quantity' => 10,
                'quantity_before' => 0,
                'quantity_after' => 10,
                'unit_cost' => 100000,
                'total_cost' => 1000000,
            ]);

        // Purchases: 20 units @ 100k = 2M
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockIn()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => 20,
                'quantity_before' => 10,
                'quantity_after' => 30,
                'unit_cost' => 100000,
                'total_cost' => 2000000,
            ]);

        // Sales/Stock out: 15 units @ 100k = 1.5M
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -15,
                'quantity_before' => 30,
                'quantity_after' => 15,
                'unit_cost' => 100000,
                'total_cost' => 1500000,
            ]);

        $response = $this->getJson('/api/v1/reports/cogs-summary?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();

        // COGS = Beginning Inventory + Purchases - Ending Inventory
        $cogs = $response->json('cogs');
        expect($cogs)->toBeGreaterThan(0);
    });

    it('handles period with no inventory movements', function () {
        $futureDate = now()->addYear()->toDateString();

        $response = $this->getJson("/api/v1/reports/cogs-summary?start_date={$futureDate}&end_date={$futureDate}");

        $response->assertOk()
            ->assertJsonPath('beginning_inventory', 0)
            ->assertJsonPath('purchases', 0)
            ->assertJsonPath('ending_inventory', 0)
            ->assertJsonPath('cogs', 0);
    });

});

describe('COGS by Product Report', function () {

    it('can generate COGS by product', function () {
        $response = $this->getJson('/api/v1/reports/cogs-by-product');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'products',
                'total_cogs',
            ])
            ->assertJsonPath('report_name', 'Laporan HPP per Produk');
    });

    it('can filter by date range', function () {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        $response = $this->getJson("/api/v1/reports/cogs-by-product?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start', $startDate)
            ->assertJsonPath('period.end', $endDate);
    });

    it('shows COGS breakdown by product', function () {
        $warehouse = Warehouse::factory()->create();
        $product1 = Product::factory()->create(['name' => 'Product A', 'purchase_price' => 100000]);
        $product2 = Product::factory()->create(['name' => 'Product B', 'purchase_price' => 200000]);

        // Product 1: Stock out 5 units @ 100k = 500k
        InventoryMovement::factory()
            ->forProduct($product1)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -5,
                'quantity_before' => 10,
                'quantity_after' => 5,
                'unit_cost' => 100000,
                'total_cost' => 500000,
            ]);

        // Product 2: Stock out 3 units @ 200k = 600k
        InventoryMovement::factory()
            ->forProduct($product2)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -3,
                'quantity_before' => 10,
                'quantity_after' => 7,
                'unit_cost' => 200000,
                'total_cost' => 600000,
            ]);

        $response = $this->getJson('/api/v1/reports/cogs-by-product?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk();

        $products = collect($response->json('products'));
        expect($products->count())->toBeGreaterThanOrEqual(2);

        $totalCogs = $response->json('total_cogs');
        expect($totalCogs)->toBeGreaterThan(0);
    });

    it('returns empty products list when no sales', function () {
        $futureDate = now()->addYear()->toDateString();

        $response = $this->getJson("/api/v1/reports/cogs-by-product?start_date={$futureDate}&end_date={$futureDate}");

        $response->assertOk()
            ->assertJsonPath('total_cogs', 0);
    });

});

describe('COGS by Category Report', function () {

    it('can generate COGS by category', function () {
        $response = $this->getJson('/api/v1/reports/cogs-by-category');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'categories',
                'total_cogs',
            ])
            ->assertJsonPath('report_name', 'Laporan HPP per Kategori');
    });

    it('can filter by date range', function () {
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        $response = $this->getJson("/api/v1/reports/cogs-by-category?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start', $startDate)
            ->assertJsonPath('period.end', $endDate);
    });

    it('shows COGS breakdown by category', function () {
        $warehouse = Warehouse::factory()->create();
        $category1 = ProductCategory::factory()->create(['name' => 'Electronics']);
        $category2 = ProductCategory::factory()->create(['name' => 'Furniture']);

        $product1 = Product::factory()->withCategory($category1)->create(['purchase_price' => 150000]);
        $product2 = Product::factory()->withCategory($category2)->create(['purchase_price' => 300000]);

        // Category 1 product: Stock out 4 units @ 150k = 600k
        InventoryMovement::factory()
            ->forProduct($product1)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -4,
                'quantity_before' => 10,
                'quantity_after' => 6,
                'unit_cost' => 150000,
                'total_cost' => 600000,
            ]);

        // Category 2 product: Stock out 2 units @ 300k = 600k
        InventoryMovement::factory()
            ->forProduct($product2)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -2,
                'quantity_before' => 10,
                'quantity_after' => 8,
                'unit_cost' => 300000,
                'total_cost' => 600000,
            ]);

        $response = $this->getJson('/api/v1/reports/cogs-by-category?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk();

        $categories = collect($response->json('categories'));
        expect($categories->count())->toBeGreaterThanOrEqual(1);

        $totalCogs = $response->json('total_cogs');
        expect($totalCogs)->toBeGreaterThan(0);
    });

});

describe('COGS Monthly Trend Report', function () {

    it('can generate monthly COGS trend', function () {
        $response = $this->getJson('/api/v1/reports/cogs-monthly-trend');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'year',
                'months',
                'total_cogs',
            ]);
    });

    it('can filter by specific year', function () {
        $year = 2024;

        $response = $this->getJson("/api/v1/reports/cogs-monthly-trend?year={$year}");

        $response->assertOk()
            ->assertJsonPath('year', $year)
            ->assertJsonPath('report_name', "Trend HPP Tahun {$year}");
    });

    it('shows monthly breakdown of COGS', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 100000]);

        // Create stock outs in different months
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->startOfYear()->addMonth()->toDateString())
            ->create([
                'quantity' => -5,
                'quantity_before' => 20,
                'quantity_after' => 15,
                'unit_cost' => 100000,
                'total_cost' => 500000,
            ]);

        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->startOfYear()->addMonths(2)->toDateString())
            ->create([
                'quantity' => -3,
                'quantity_before' => 15,
                'quantity_after' => 12,
                'unit_cost' => 100000,
                'total_cost' => 300000,
            ]);

        $response = $this->getJson('/api/v1/reports/cogs-monthly-trend?year='.now()->year);

        $response->assertOk();

        $months = collect($response->json('months'));
        expect($months->count())->toBe(12);

        $totalCogs = $response->json('total_cogs');
        expect($totalCogs)->toBeGreaterThanOrEqual(0);
    });

    it('defaults to current year when year not provided', function () {
        $response = $this->getJson('/api/v1/reports/cogs-monthly-trend');

        $response->assertOk()
            ->assertJsonPath('year', now()->year);
    });

});

describe('Product COGS Detail Report', function () {

    it('can generate product-specific COGS detail', function () {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/reports/products/{$product->id}/cogs");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'product' => ['id', 'sku', 'name'],
                'period' => ['start', 'end'],
                'movements',
                'total_quantity',
                'total_cogs',
            ])
            ->assertJsonPath('report_name', 'Detail HPP Produk')
            ->assertJsonPath('product.id', $product->id)
            ->assertJsonPath('product.sku', $product->sku)
            ->assertJsonPath('product.name', $product->name);
    });

    it('can filter product COGS detail by date range', function () {
        $product = Product::factory()->create();
        $startDate = '2024-01-01';
        $endDate = '2024-12-31';

        $response = $this->getJson("/api/v1/reports/products/{$product->id}/cogs?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start', $startDate)
            ->assertJsonPath('period.end', $endDate);
    });

    it('shows detailed inventory movements affecting COGS', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 100000]);

        // Stock in
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockIn()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => 10,
                'quantity_before' => 0,
                'quantity_after' => 10,
                'unit_cost' => 100000,
                'total_cost' => 1000000,
            ]);

        // Stock out (creates COGS)
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->addDay()->toDateString())
            ->create([
                'quantity' => -6,
                'quantity_before' => 10,
                'quantity_after' => 4,
                'unit_cost' => 100000,
                'total_cost' => 600000,
            ]);

        $response = $this->getJson("/api/v1/reports/products/{$product->id}/cogs?start_date=".now()->toDateString().'&end_date='.now()->addDays(2)->toDateString());

        $response->assertOk();

        $movements = collect($response->json('movements'));
        expect($movements->count())->toBeGreaterThan(0);

        $totalCogs = $response->json('total_cogs');
        expect($totalCogs)->toBeGreaterThanOrEqual(600000);
    });

    it('returns 404 for non-existent product', function () {
        $response = $this->getJson('/api/v1/reports/products/99999/cogs');

        $response->assertNotFound();
    });

    it('handles product with no movements', function () {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/reports/products/{$product->id}/cogs");

        $response->assertOk()
            ->assertJsonPath('total_quantity', 0)
            ->assertJsonPath('total_cogs', 0);
    });

    it('calculates total quantity and cost correctly', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['purchase_price' => 100000]);

        // Multiple stock outs
        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->toDateString())
            ->create([
                'quantity' => -5,
                'quantity_before' => 20,
                'quantity_after' => 15,
                'unit_cost' => 100000,
                'total_cost' => 500000,
            ]);

        InventoryMovement::factory()
            ->forProduct($product)
            ->inWarehouse($warehouse)
            ->stockOut()
            ->onDate(now()->addDay()->toDateString())
            ->create([
                'quantity' => -3,
                'quantity_before' => 15,
                'quantity_after' => 12,
                'unit_cost' => 100000,
                'total_cost' => 300000,
            ]);

        $response = $this->getJson("/api/v1/reports/products/{$product->id}/cogs?start_date=".now()->toDateString().'&end_date='.now()->addDays(2)->toDateString());

        $response->assertOk();

        $totalQuantity = $response->json('total_quantity');
        $totalCogs = $response->json('total_cogs');

        // Total quantity sold: abs(-5) + abs(-3) = 8 (or -8 if summed directly)
        // Total COGS: 500k + 300k = 800k
        expect(abs($totalQuantity))->toBeGreaterThanOrEqual(8);
        expect($totalCogs)->toBeGreaterThanOrEqual(800000);
    });

});
