<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\StockOpname;
use App\Models\Inventory\StockOpnameItem;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\StockOpnameService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(StockOpnameService::class);
    $this->inventory = app(InventoryService::class);
    $this->warehouse = Warehouse::factory()->create();
});

describe('StockOpnameService create and manage', function () {
    it('creates stock opname', function () {
        $opname = $this->service->create([
            'warehouse_id' => $this->warehouse->id,
            'opname_date' => now()->toDateString(),
            'name' => 'Stock Count 2024',
        ]);

        expect($opname)->toBeInstanceOf(StockOpname::class);
        expect($opname->status)->toBe(DocumentStatus::Draft);
        expect($opname->warehouse_id)->toBe($this->warehouse->id);
    });

    it('generates items from warehouse stock', function () {
        $product1 = Product::factory()->create(['track_inventory' => true]);
        $product2 = Product::factory()->create(['track_inventory' => true]);

        ProductStock::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 10000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'average_cost' => 20000,
        ]);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $opname = $this->service->generateItems($opname);

        expect($opname->items)->toHaveCount(2);
        expect($opname->items->first()->system_quantity)->toBe(100);
    });

    it('adds item manually', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);

        $item = $this->service->addItem($opname, ['product_id' => $product->id]);

        expect($item)->toBeInstanceOf(StockOpnameItem::class);
        expect($item->product_id)->toBe($product->id);
    });

    it('records count on item', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);

        $updated = $this->service->updateItem($item, [
            'counted_quantity' => 95,
            'notes' => 'Missing 5 units',
        ]);

        expect($updated->counted_quantity)->toBe(95);
        expect($updated->variance_quantity)->toBe(-5);
    });
});

describe('StockOpnameService workflow', function () {
    it('starts counting workflow', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
        ]);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $this->service->addItem($opname, ['product_id' => $product->id]);

        $opname = $this->service->startCounting($opname, $this->user->id);

        expect($opname->status)->toBe(DocumentStatus::Counting);
    });

    it('submits for review after counting', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);

        $opname = $this->service->startCounting($opname, $this->user->id);
        $this->service->updateItem($item->fresh(), ['counted_quantity' => 100]);

        $opname = $this->service->submitForReview($opname->fresh(), $this->user->id);

        expect($opname->status)->toBe(DocumentStatus::Reviewed);
    });

    it('generates variance report', function () {
        $product = Product::factory()->create(['track_inventory' => true]);
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'average_cost' => 10000,
        ]);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);
        $this->service->updateItem($item, ['counted_quantity' => 95]);

        $report = $this->service->getVarianceReport($opname->fresh());

        expect($report['summary']['total_items'])->toBe(1);
        expect($report['summary']['items_with_variance'])->toBe(1);
        expect($report['summary']['items_with_shortage'])->toBe(1);
        expect($report['variances'])->toHaveCount(1);
    });

    it('applies count-time variance as a delta so intervening sales are kept', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $this->inventory->stockIn($product, $this->warehouse, 40, 10000);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);
        $opname = $this->service->startCounting($opname, $this->user->id);
        $this->service->updateItem($item->fresh(), ['counted_quantity' => 38]);
        $opname = $this->service->submitForReview($opname->fresh(), $this->user->id);

        $this->inventory->stockOut($product, $this->warehouse, 15);

        $opname = $this->service->approve($opname->fresh(), $this->user->id);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($opname->status)->toBe(DocumentStatus::Completed)
            ->and($stock->quantity)->toBe(23);

        $movement = InventoryMovement::where('reference_type', StockOpname::class)
            ->where('reference_id', $opname->id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and($movement->quantity)->toBe(-2)
            ->and($movement->total_cost)->toBe(20000);

        $entry = JournalEntry::where('source_type', StockOpname::class)
            ->where('source_id', $opname->id)
            ->with('lines')
            ->first();

        expect($entry)->not->toBeNull()
            ->and((int) $entry->lines->sum('debit'))->toBe(20000)
            ->and((int) $entry->lines->sum('credit'))->toBe(20000);
    });

    it('does not move stock when the count matches the snapshot after intervening sales', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $this->inventory->stockIn($product, $this->warehouse, 40, 10000);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);
        $opname = $this->service->startCounting($opname, $this->user->id);
        $this->service->updateItem($item->fresh(), ['counted_quantity' => 40]);
        $opname = $this->service->submitForReview($opname->fresh(), $this->user->id);

        $this->inventory->stockOut($product, $this->warehouse, 15);
        $this->service->approve($opname->fresh(), $this->user->id);

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(25);

        expect(InventoryMovement::where('reference_type', StockOpname::class)
            ->where('reference_id', $opname->id)
            ->exists())->toBeFalse();
    });

    it('keeps FIFO layers in sync with an opname surplus', function () {
        AccountingPolicy::query()->update(['costing_method' => 'fifo']);
        Once::flush();

        $product = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);
        $this->inventory->stockIn($product, $this->warehouse, 40, 10000);

        $opname = $this->service->create(['warehouse_id' => $this->warehouse->id]);
        $item = $this->service->addItem($opname, ['product_id' => $product->id]);
        $opname = $this->service->startCounting($opname, $this->user->id);
        $this->service->updateItem($item->fresh(), ['counted_quantity' => 45]);
        $opname = $this->service->submitForReview($opname->fresh(), $this->user->id);
        $this->service->approve($opname->fresh(), $this->user->id);

        $layers = InventoryCostLayer::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->orderBy('id')
            ->get();

        expect($layers)->toHaveCount(2)
            ->and($layers[0]->quantity)->toBe(40)
            ->and($layers[1]->quantity)->toBe(5)
            ->and($layers[1]->unit_cost)->toBe(10000)
            ->and($layers->sum('quantity'))->toBe(45);
    });
});
