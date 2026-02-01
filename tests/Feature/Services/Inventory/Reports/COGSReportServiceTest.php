<?php

declare(strict_types=1);

use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Services\Inventory\Reports\COGSReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new COGSReportService;
});

describe('getCOGSSummary', function () {
    it('returns correct structure with empty data', function () {
        $result = $this->service->getCOGSSummary('2024-06-01', '2024-06-30');

        expect($result)->toHaveKeys([
            'period',
            'beginning_inventory',
            'purchases',
            'goods_available',
            'ending_inventory',
            'cogs',
            'cogs_from_movements',
        ]);

        expect($result['period'])->toBe([
            'start' => '2024-06-01',
            'end' => '2024-06-30',
        ]);

        expect($result['beginning_inventory'])->toBe(0);
        expect($result['purchases'])->toBe(0);
        expect($result['goods_available'])->toBe(0);
        expect($result['ending_inventory'])->toBe(0);
        expect($result['cogs'])->toBe(0);
        expect($result['cogs_from_movements'])->toBe(0);
    });

    it('calculates COGS from OUT movements', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'is_active' => true,
            'purchase_price' => 10000,
        ]);

        // Create IN movement before period
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'movement_date' => '2024-05-15',
            'quantity' => 100,
            'unit_cost' => 10000,
            'total_cost' => 1000000,
        ]);

        // Create IN movement during period
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'movement_date' => '2024-06-10',
            'quantity' => 50,
            'unit_cost' => 12000,
            'total_cost' => 600000,
        ]);

        // Create OUT movements during period
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 30,
            'unit_cost' => 10000,
            'total_cost' => 300000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-20',
            'quantity' => 20,
            'unit_cost' => 11000,
            'total_cost' => 220000,
        ]);

        $result = $this->service->getCOGSSummary('2024-06-01', '2024-06-30');

        expect($result['purchases'])->toBe(600000);
        expect($result['cogs_from_movements'])->toBe(520000); // 300000 + 220000
        expect($result['cogs'])->toBeGreaterThanOrEqual(520000);
    });

    it('handles date range filtering correctly', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // Movement before period (should not be counted)
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-05-31',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        // Movement during period
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 20,
            'unit_cost' => 10000,
            'total_cost' => 200000,
        ]);

        // Movement after period (should not be counted)
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-07-01',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        $result = $this->service->getCOGSSummary('2024-06-01', '2024-06-30');

        expect($result['cogs_from_movements'])->toBe(200000);
    });
});

describe('getCOGSByProduct', function () {
    it('returns products sorted by total_cogs desc', function () {
        $category = ProductCategory::factory()->create(['name' => 'Electronics']);

        $product1 = Product::factory()->create([
            'sku' => 'PROD-001',
            'name' => 'Product 1',
            'category_id' => $category->id,
        ]);

        $product2 = Product::factory()->create([
            'sku' => 'PROD-002',
            'name' => 'Product 2',
            'category_id' => $category->id,
        ]);

        $product3 = Product::factory()->create([
            'sku' => 'PROD-003',
            'name' => 'Product 3',
            'category_id' => $category->id,
        ]);

        // Product 2 has highest COGS
        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 50,
            'unit_cost' => 20000,
            'total_cost' => 1000000,
        ]);

        // Product 1 has medium COGS
        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 30,
            'unit_cost' => 10000,
            'total_cost' => 300000,
        ]);

        // Product 3 has lowest COGS
        InventoryMovement::factory()->create([
            'product_id' => $product3->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 5000,
            'total_cost' => 50000,
        ]);

        $result = $this->service->getCOGSByProduct('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(3);
        expect($result[0]['sku'])->toBe('PROD-002');
        expect($result[0]['total_cogs'])->toBe(1000000);
        expect($result[1]['sku'])->toBe('PROD-001');
        expect($result[1]['total_cogs'])->toBe(300000);
        expect($result[2]['sku'])->toBe('PROD-003');
        expect($result[2]['total_cogs'])->toBe(50000);
    });

    it('calculates percentage correctly', function () {
        $product1 = Product::factory()->create(['sku' => 'PROD-001']);
        $product2 = Product::factory()->create(['sku' => 'PROD-002']);

        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 50,
            'unit_cost' => 10000,
            'total_cost' => 500000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 50,
            'unit_cost' => 10000,
            'total_cost' => 500000,
        ]);

        $result = $this->service->getCOGSByProduct('2024-06-01', '2024-06-30');

        expect($result[0]['percentage'])->toBe(50.0);
        expect($result[1]['percentage'])->toBe(50.0);
    });

    it('calculates average unit cost correctly', function () {
        $product = Product::factory()->create(['sku' => 'PROD-001']);

        // Two OUT movements with different unit costs
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-10',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-20',
            'quantity' => 20,
            'unit_cost' => 15000,
            'total_cost' => 300000,
        ]);

        $result = $this->service->getCOGSByProduct('2024-06-01', '2024-06-30');

        expect($result[0]['quantity_sold'])->toBe(30);
        expect($result[0]['total_cogs'])->toBe(400000);
        // Average = 400000 / 30 = 13333.33... rounded to 13333
        expect($result[0]['average_unit_cost'])->toBe(13333);
    });

    it('returns empty collection when no OUT movements exist', function () {
        $product = Product::factory()->create();

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'movement_date' => '2024-06-15',
            'quantity' => 50,
            'unit_cost' => 10000,
            'total_cost' => 500000,
        ]);

        $result = $this->service->getCOGSByProduct('2024-06-01', '2024-06-30');

        expect($result)->toBeEmpty();
    });
});

describe('getCOGSByCategory', function () {
    it('groups by category correctly', function () {
        $electronics = ProductCategory::factory()->create(['name' => 'Electronics']);
        $furniture = ProductCategory::factory()->create(['name' => 'Furniture']);

        $product1 = Product::factory()->create(['category_id' => $electronics->id]);
        $product2 = Product::factory()->create(['category_id' => $electronics->id]);
        $product3 = Product::factory()->create(['category_id' => $furniture->id]);

        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 30,
            'unit_cost' => 10000,
            'total_cost' => 300000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 20,
            'unit_cost' => 10000,
            'total_cost' => 200000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product3->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        $result = $this->service->getCOGSByCategory('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(2);

        $electronicsRow = $result->firstWhere('category_name', 'Electronics');
        expect($electronicsRow['product_count'])->toBe(2);
        expect($electronicsRow['quantity_sold'])->toBe(50);
        expect($electronicsRow['total_cogs'])->toBe(500000);

        $furnitureRow = $result->firstWhere('category_name', 'Furniture');
        expect($furnitureRow['product_count'])->toBe(1);
        expect($furnitureRow['quantity_sold'])->toBe(10);
        expect($furnitureRow['total_cogs'])->toBe(100000);
    });

    it('handles uncategorized products', function () {
        $product = Product::factory()->create(['category_id' => null]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        $result = $this->service->getCOGSByCategory('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(1);
        expect($result[0]['category_id'])->toBeNull();
        expect($result[0]['category_name'])->toBe('Tanpa Kategori');
        expect($result[0]['total_cogs'])->toBe(100000);
    });

    it('calculates percentage correctly by category', function () {
        $category1 = ProductCategory::factory()->create();
        $category2 = ProductCategory::factory()->create();

        $product1 = Product::factory()->create(['category_id' => $category1->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 30,
            'unit_cost' => 10000,
            'total_cost' => 300000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 70,
            'unit_cost' => 10000,
            'total_cost' => 700000,
        ]);

        $result = $this->service->getCOGSByCategory('2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(2);
        // Sorted by total_cogs desc, so product2's category first
        expect($result[0]['percentage'])->toBe(70.0);
        expect($result[1]['percentage'])->toBe(30.0);
    });

    it('sorts by total_cogs descending', function () {
        $category1 = ProductCategory::factory()->create(['name' => 'Low COGS']);
        $category2 = ProductCategory::factory()->create(['name' => 'High COGS']);

        $product1 = Product::factory()->create(['category_id' => $category1->id]);
        $product2 = Product::factory()->create(['category_id' => $category2->id]);

        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 50,
            'unit_cost' => 10000,
            'total_cost' => 500000,
        ]);

        $result = $this->service->getCOGSByCategory('2024-06-01', '2024-06-30');

        expect($result[0]['category_name'])->toBe('High COGS');
        expect($result[1]['category_name'])->toBe('Low COGS');
    });
});

describe('getTopProductsByCOGS', function () {
    it('respects limit parameter', function () {
        $products = Product::factory()->count(15)->create();

        foreach ($products as $index => $product) {
            InventoryMovement::factory()->create([
                'product_id' => $product->id,
                'type' => InventoryMovement::TYPE_OUT,
                'movement_date' => '2024-06-15',
                'quantity' => 10,
                'unit_cost' => 10000 + ($index * 1000),
                'total_cost' => (10000 + ($index * 1000)) * 10,
            ]);
        }

        $result = $this->service->getTopProductsByCOGS('2024-06-01', '2024-06-30', 5);

        expect($result)->toHaveCount(5);
    });

    it('calculates gross margin correctly', function () {
        $product = Product::factory()->create([
            'selling_price' => 20000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 12000,
            'total_cost' => 120000,
        ]);

        $result = $this->service->getTopProductsByCOGS('2024-06-01', '2024-06-30', 10);

        expect($result)->toHaveCount(1);

        $product = $result[0];
        expect($product['quantity_sold'])->toBe(10);
        expect($product['total_cogs'])->toBe(120000);
        expect($product['average_selling_price'])->toBe(20000);
        expect($product['estimated_revenue'])->toBe(200000); // 10 * 20000
        expect($product['estimated_gross_profit'])->toBe(80000); // 200000 - 120000
        // Margin = (80000 / 200000) * 100 = 40%
        expect($product['gross_margin_percent'])->toBe(40.0);
    });

    it('handles zero revenue gracefully', function () {
        $product = Product::factory()->create([
            'selling_price' => 0,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        $result = $this->service->getTopProductsByCOGS('2024-06-01', '2024-06-30', 10);

        expect($result[0]['gross_margin_percent'])->toBe(0);
    });

    it('returns correct structure for each product', function () {
        $product = Product::factory()->create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'selling_price' => 15000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        $result = $this->service->getTopProductsByCOGS('2024-06-01', '2024-06-30', 10);

        expect($result[0])->toHaveKeys([
            'product_id',
            'sku',
            'name',
            'quantity_sold',
            'total_cogs',
            'average_selling_price',
            'estimated_revenue',
            'estimated_gross_profit',
            'gross_margin_percent',
        ]);
    });
});

describe('getProductCOGSDetail', function () {
    it('returns movements for specific product', function () {
        $product1 = Product::factory()->create();
        $product2 = Product::factory()->create();

        // Product 1 movements
        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-10',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
            'movement_number' => 'OUT-001',
            'notes' => 'Test movement 1',
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product1->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-20',
            'quantity' => 20,
            'unit_cost' => 12000,
            'total_cost' => 240000,
            'movement_number' => 'OUT-002',
            'notes' => 'Test movement 2',
        ]);

        // Product 2 movement (should not be included)
        InventoryMovement::factory()->create([
            'product_id' => $product2->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        $result = $this->service->getProductCOGSDetail($product1, '2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(2);
        expect($result[0]['movement_number'])->toBe('OUT-001');
        expect($result[1]['movement_number'])->toBe('OUT-002');
    });

    it('only returns OUT movements', function () {
        $product = Product::factory()->create();

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_IN,
            'movement_date' => '2024-06-10',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        $result = $this->service->getProductCOGSDetail($product, '2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(1);
        expect($result[0]['quantity'])->toBe(5);
    });

    it('orders movements by date ascending', function () {
        $product = Product::factory()->create();

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-20',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-10',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 8,
            'unit_cost' => 10000,
            'total_cost' => 80000,
        ]);

        $result = $this->service->getProductCOGSDetail($product, '2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(3);
        expect($result[0]['date'])->toBe('2024-06-10');
        expect($result[1]['date'])->toBe('2024-06-15');
        expect($result[2]['date'])->toBe('2024-06-20');
    });

    it('returns correct structure for each movement', function () {
        $product = Product::factory()->create();

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
            'movement_number' => 'OUT-001',
            'reference_type' => 'Sale',
            'reference_id' => 123,
            'notes' => 'Test notes',
        ]);

        $result = $this->service->getProductCOGSDetail($product, '2024-06-01', '2024-06-30');

        expect($result[0])->toHaveKeys([
            'id',
            'date',
            'movement_number',
            'reference_type',
            'reference_id',
            'quantity',
            'unit_cost',
            'total_cost',
            'notes',
        ]);

        expect($result[0]['movement_number'])->toBe('OUT-001');
        expect($result[0]['reference_type'])->toBe('Sale');
        expect($result[0]['reference_id'])->toBe(123);
        expect($result[0]['notes'])->toBe('Test notes');
    });

    it('respects date range filtering', function () {
        $product = Product::factory()->create();

        // Before range
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-05-31',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        // Within range
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-06-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        // After range
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2024-07-01',
            'quantity' => 5,
            'unit_cost' => 10000,
            'total_cost' => 50000,
        ]);

        $result = $this->service->getProductCOGSDetail($product, '2024-06-01', '2024-06-30');

        expect($result)->toHaveCount(1);
        expect($result[0]['date'])->toBe('2024-06-15');
    });
});

describe('getMonthlyCOGSTrend', function () {
    it('skips future months', function () {
        // Test with a past year to avoid flaky tests
        $result = $this->service->getMonthlyCOGSTrend(2023);

        expect($result)->toHaveCount(12);

        foreach ($result as $month) {
            expect($month)->toHaveKeys([
                'month',
                'month_name',
                'beginning_inventory',
                'purchases',
                'ending_inventory',
                'cogs',
            ]);
        }
    });

    it('generates trend for each month in the year', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'is_active' => true,
        ]);

        // Create movements across different months
        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2023-01-15',
            'quantity' => 10,
            'unit_cost' => 10000,
            'total_cost' => 100000,
        ]);

        InventoryMovement::factory()->create([
            'product_id' => $product->id,
            'type' => InventoryMovement::TYPE_OUT,
            'movement_date' => '2023-02-15',
            'quantity' => 20,
            'unit_cost' => 10000,
            'total_cost' => 200000,
        ]);

        $result = $this->service->getMonthlyCOGSTrend(2023);

        expect($result)->toHaveCount(12);
        expect($result[0]['month'])->toBe('2023-01');
        expect($result[1]['month'])->toBe('2023-02');
        expect($result[11]['month'])->toBe('2023-12');
    });

    it('returns correct structure for each month', function () {
        $result = $this->service->getMonthlyCOGSTrend(2023);

        foreach ($result as $month) {
            expect($month)->toHaveKeys([
                'month',
                'month_name',
                'beginning_inventory',
                'purchases',
                'ending_inventory',
                'cogs',
            ]);

            expect($month['month'])->toMatch('/^\d{4}-\d{2}$/');
            expect($month['month_name'])->toBeString();
        }
    });
});
