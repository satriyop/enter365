<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Services\Accounting\AccountingPolicyManager;
use App\Services\Inventory\Costing\FIFOCostingStrategy;
use App\Services\Inventory\Costing\WeightedAverageCostingStrategy;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    authenticatedAdmin();
});

describe('WeightedAverageCostingStrategy', function () {
    it('calculates weighted average on stock in', function () {
        $strategy = new WeightedAverageCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        // First batch: 100 units at 10,000
        $strategy->recordStockIn($stock, 100, 10000);
        expect($stock->quantity)->toBe(100)
            ->and($stock->average_cost)->toBe(10000);

        // Second batch: 50 units at 16,000
        // Weighted avg = (100*10000 + 50*16000) / 150 = 1,800,000 / 150 = 12,000
        $strategy->recordStockIn($stock, 50, 16000);
        expect($stock->quantity)->toBe(150)
            ->and($stock->average_cost)->toBe(12000);
    });

    it('returns average cost on stock out', function () {
        $strategy = new WeightedAverageCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 100, 10000);
        $strategy->recordStockIn($stock, 50, 16000);

        $totalCost = $strategy->recordStockOut($stock, 30);

        expect($totalCost)->toBe(360000)
            ->and($stock->quantity)->toBe(120);
    });

    it('does not report cost for units that were never on hand', function () {
        $strategy = new WeightedAverageCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 2, 10000);

        expect(fn () => $strategy->recordStockOut($stock, 10))
            ->toThrow(BusinessRuleException::class, 'Stok tidak mencukupi');

        expect((int) $stock->fresh()->quantity)->toBe(2);
    });

    it('revalues on-hand stock when quantity is unchanged', function () {
        $strategy = new WeightedAverageCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 10, 10000);

        $valueDelta = $strategy->recordAdjustment($stock, 0, 12000);

        expect($valueDelta)->toBe(20000)
            ->and((int) $stock->fresh()->average_cost)->toBe(12000)
            ->and((int) $stock->fresh()->total_value)->toBe(120000);
    });
});

describe('FIFOCostingStrategy', function () {
    it('creates cost layers on stock in', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 100, 10000);
        $strategy->recordStockIn($stock, 50, 16000);

        expect($stock->quantity)->toBe(150);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id')
            ->get();

        expect($layers)->toHaveCount(2)
            ->and($layers[0]->quantity)->toBe(100)
            ->and($layers[0]->unit_cost)->toBe(10000)
            ->and($layers[1]->quantity)->toBe(50)
            ->and($layers[1]->unit_cost)->toBe(16000);
    });

    it('consumes oldest layers first on stock out', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        // Batch 1: 100 at 10,000
        $strategy->recordStockIn($stock, 100, 10000);
        // Batch 2: 50 at 16,000
        $strategy->recordStockIn($stock, 50, 16000);

        // Remove 80 units — should consume 80 from batch 1 (at 10,000 each)
        $totalCost = $strategy->recordStockOut($stock, 80);

        expect($totalCost)->toBe(800000)
            ->and($stock->quantity)->toBe(70);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id')
            ->get();

        expect($layers[0]->quantity)->toBe(20) // 100 - 80
            ->and($layers[1]->quantity)->toBe(50); // untouched
    });

    it('spans multiple layers on large stock out', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        // Batch 1: 100 at 10,000
        $strategy->recordStockIn($stock, 100, 10000);
        // Batch 2: 50 at 16,000
        $strategy->recordStockIn($stock, 50, 16000);

        // Remove 120 units — should consume 100 from batch 1 + 20 from batch 2
        // Exact total: 100*10000 + 20*16000 = 1,320,000
        $totalCost = $strategy->recordStockOut($stock, 120);

        expect($totalCost)->toBe(1320000)
            ->and($stock->quantity)->toBe(30);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id')
            ->get();

        expect($layers[0]->quantity)->toBe(0) // fully consumed
            ->and($layers[1]->quantity)->toBe(30); // 50 - 20
    });

    it('returns oldest layer cost as current unit cost', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 100, 10000);
        $strategy->recordStockIn($stock, 50, 16000);

        expect($strategy->getCurrentUnitCost($stock))->toBe(10000);

        // Consume all of batch 1
        $strategy->recordStockOut($stock, 100);

        expect($strategy->getCurrentUnitCost($stock))->toBe(16000);
    });

    it('creates a layer for a positive adjustment and consumes oldest on a negative one', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 100, 10000);

        expect($strategy->recordAdjustment($stock, 20, 12000))->toBe(240000)
            ->and($stock->quantity)->toBe(120);

        expect($strategy->recordAdjustment($stock, -30, 12000))->toBe(-300000)
            ->and($stock->quantity)->toBe(90);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->orderBy('id')
            ->get();

        expect($layers[0]->quantity)->toBe(70)
            ->and($layers[1]->quantity)->toBe(20);
    });

    it('returns the exact layer total when unit cost does not divide evenly', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);

        $strategy->recordStockIn($stock, 2, 333);
        $strategy->recordStockIn($stock, 1, 334);

        $totalCost = $strategy->recordStockOut($stock, 3);

        expect($totalCost)->toBe(1000);
    });

    it('fails loudly when FIFO layers cannot cover a stock-out', function () {
        $strategy = new FIFOCostingStrategy;
        $product = Product::factory()->create();
        $warehouse = Warehouse::factory()->create();
        $stock = ProductStock::getOrCreate($product, $warehouse);
        $stock->quantity = 50;
        $stock->average_cost = 10000;
        $stock->save();

        $strategy->recordStockOut($stock, 10);
    })->throws(\App\Exceptions\Domain\BusinessRuleException::class);
});

describe('InventoryService with FIFO policy', function () {
    beforeEach(function () {
        // Switch to FIFO mode and flush once() cache so AccountingPolicy::current() re-reads
        AccountingPolicy::query()->update(['costing_method' => 'fifo']);
        Once::flush();
    });

    it('creates cost layers via stockIn', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $warehouse = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn($product, $warehouse, 100, 10000, 'Purchase 1');
        $service->stockIn($product, $warehouse, 50, 16000, 'Purchase 2');

        $layers = InventoryCostLayer::where('product_id', $product->id)->get();
        expect($layers)->toHaveCount(2);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(150);
    });

    it('creates cost layers for production receipt', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $warehouse = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn(
            $product,
            $warehouse,
            20,
            18000,
            'Hasil produksi',
            null,
            null,
            InventoryMovement::TYPE_PRODUCTION,
        );

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->get();

        expect($layers)->toHaveCount(1)
            ->and($layers[0]->quantity)->toBe(20)
            ->and($layers[0]->unit_cost)->toBe(18000);
    });

    it('uses FIFO cost on stockOut', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $warehouse = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn($product, $warehouse, 100, 10000, 'Batch 1');
        $service->stockIn($product, $warehouse, 50, 16000, 'Batch 2');

        // Stock out 80 — should use FIFO cost (10,000 from batch 1)
        $movement = $service->stockOut($product, $warehouse, 80, 'Sale');

        expect($movement->unit_cost)->toBe(10000)
            ->and($movement->total_cost)->toBe(800000);

        $unevenProduct = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 333]);
        $service->stockIn($unevenProduct, $warehouse, 2, 333);
        $service->stockIn($unevenProduct, $warehouse, 1, 334);
        $uneven = $service->stockOut($unevenProduct, $warehouse, 3, 'Uneven FIFO');

        expect($uneven->total_cost)->toBe(1000)
            ->and($uneven->unit_cost)->toBe(333);

        // Verify cost layers updated
        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->orderBy('id')
            ->get();
        expect($layers[0]->quantity)->toBe(20) // 100 - 80
            ->and($layers[1]->quantity)->toBe(50); // untouched
    });

    it('uses FIFO cost for transfers', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn($product, $warehouse1, 100, 10000, 'Batch 1');
        $service->stockIn($product, $warehouse1, 50, 20000, 'Batch 2');

        $result = $service->transfer($product, $warehouse1, $warehouse2, 30, 'Transfer');

        // Should use FIFO cost from warehouse1 (10,000 from batch 1)
        expect($result['out']->unit_cost)->toBe(10000);
        expect($result['in']->unit_cost)->toBe(10000);

        // Source warehouse layers should be consumed
        $sourceLayers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse1->id)
            ->orderBy('id')
            ->get();
        expect($sourceLayers[0]->quantity)->toBe(70); // 100 - 30

        // Destination warehouse should have a new layer
        $destLayers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse2->id)
            ->get();
        expect($destLayers)->toHaveCount(1)
            ->and($destLayers[0]->quantity)->toBe(30)
            ->and($destLayers[0]->unit_cost)->toBe(10000);
    });

    it('transfers an uneven FIFO issue without leaking a sen', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 333]);
        $from = Warehouse::factory()->create();
        $to = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn($product, $from, 2, 333);
        $service->stockIn($product, $from, 1, 334);

        $result = $service->transfer($product, $from, $to, 3);

        expect($result['out']->total_cost)->toBe(1000)
            ->and($result['in']->total_cost)->toBe(1000);

        $destValue = (int) InventoryCostLayer::query()
            ->where('product_id', $product->id)
            ->where('warehouse_id', $to->id)
            ->get()
            ->sum(fn (InventoryCostLayer $layer) => $layer->quantity * $layer->unit_cost);

        expect($destValue)->toBe(1000)
            ->and((int) $result['in']->quantity * (int) $result['in']->unit_cost)->not->toBe(1000);
    });

    it('keeps FIFO layers in sync with adjust()', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $warehouse = Warehouse::factory()->create();

        /** @var InventoryService $service */
        $service = app(InventoryService::class);

        $service->stockIn($product, $warehouse, 100, 10000);
        $service->adjust($product, $warehouse, 120);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->orderBy('id')
            ->get();

        expect($layers->sum('quantity'))->toBe(120)
            ->and($layers)->toHaveCount(2)
            ->and($layers[1]->quantity)->toBe(20);
    });
});

describe('AccountingPolicyManager costing', function () {
    it('returns weighted average strategy by default', function () {
        $manager = app(AccountingPolicyManager::class);
        expect($manager->costing())->toBeInstanceOf(WeightedAverageCostingStrategy::class);
    });

    it('returns FIFO strategy when configured', function () {
        AccountingPolicy::query()->update(['costing_method' => 'fifo']);
        Once::flush();

        $manager = app(AccountingPolicyManager::class);
        expect($manager->costing())->toBeInstanceOf(FIFOCostingStrategy::class);
    });

    it('includes costing_method in current policies', function () {
        $manager = app(AccountingPolicyManager::class);
        $policies = $manager->getCurrentPolicies();

        expect($policies)->toHaveKey('costing_method')
            ->and($policies['costing_method'])->toBe('weighted_average');
    });

    it('includes costing_method in available options', function () {
        $manager = app(AccountingPolicyManager::class);
        $options = $manager->getAvailableOptions();

        expect($options)->toHaveKey('costing_method')
            ->and($options['costing_method'])->toBe(['weighted_average', 'fifo']);
    });
});
