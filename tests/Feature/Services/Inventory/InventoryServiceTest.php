<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(InventoryService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['track_inventory' => true]);
});

describe('InventoryService stock movements', function () {
    it('records stock in', function () {
        $movement = $this->service->stockIn(
            $this->product,
            $this->warehouse,
            100,
            10000,
            'Purchase order receipt'
        );

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->type)->toBe(InventoryMovement::TYPE_IN);
        expect($movement->quantity)->toBe(100);
        expect($movement->unit_cost)->toBe(10000);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(100);
        expect($stock->average_cost)->toBe(10000);
    });

    it('records stock out', function () {
        // First add stock
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        // Then remove some
        $movement = $this->service->stockOut(
            $this->product,
            $this->warehouse,
            30,
            'Sales order delivery'
        );

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->type)->toBe(InventoryMovement::TYPE_OUT);
        expect($movement->quantity)->toBe(-30);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(70);
    });

    it('adjusts stock quantity', function () {
        // Create initial stock
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        // Adjust to different quantity
        $movement = $this->service->adjust(
            $this->product,
            $this->warehouse,
            120,
            null,
            'Stock correction'
        );

        expect($movement)->toBeInstanceOf(InventoryMovement::class);
        expect($movement->type)->toBe(InventoryMovement::TYPE_ADJUSTMENT);
        expect($movement->quantity)->toBe(20);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(120);
    });

    it('transfers stock between warehouses', function () {
        $warehouse2 = Warehouse::factory()->create();

        // Add stock to source warehouse
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        // Transfer to another warehouse
        $result = $this->service->transfer(
            $this->product,
            $this->warehouse,
            $warehouse2,
            40,
            'Transfer to warehouse 2'
        );

        expect($result)->toHaveKeys(['out', 'in']);
        expect($result['out'])->toBeInstanceOf(InventoryMovement::class);
        expect($result['in'])->toBeInstanceOf(InventoryMovement::class);

        $sourceStock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $destStock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $warehouse2->id)
            ->first();

        expect($sourceStock->quantity)->toBe(60);
        expect($destStock->quantity)->toBe(40);
    });

    it('records production receipt with production movement type and syncs current stock', function () {
        $movement = $this->service->stockIn(
            $this->product,
            $this->warehouse,
            8,
            25000,
            'Hasil produksi WO #WO-1',
            'App\\Models\\Manufacturing\\WorkOrder',
            99,
            InventoryMovement::TYPE_PRODUCTION,
        );

        expect($movement->type)->toBe(InventoryMovement::TYPE_PRODUCTION)
            ->and($movement->quantity)->toBe(8)
            ->and($movement->unit_cost)->toBe(25000)
            ->and($movement->total_cost)->toBe(200000)
            ->and($movement->reference_id)->toBe(99);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(8)
            ->and($stock->average_cost)->toBe(25000);

        $this->product->refresh();
        expect($this->product->current_stock)->toBe(8);
    });

    it('rebuilds current_stock from locked warehouse sums so a transfer does not drift the cache', function () {
        $warehouse2 = Warehouse::factory()->create();

        $this->service->stockIn($this->product, $this->warehouse, 10, 10000);
        $this->service->stockIn($this->product, $warehouse2, 10, 10000);

        expect((int) $this->product->fresh()->current_stock)->toBe(20);

        $this->service->transfer($this->product, $this->warehouse, $warehouse2, 4);

        expect((int) $this->product->fresh()->current_stock)->toBe(20);
    });

    it('lockForStock always returns a persisted row', function () {
        \Illuminate\Support\Facades\DB::transaction(function () {
            $stock = ProductStock::lockForStock($this->product, $this->warehouse);

            expect($stock)->toBeInstanceOf(ProductStock::class)
                ->and($stock->id)->not->toBeNull()
                ->and($stock->product_id)->toBe($this->product->id)
                ->and($stock->warehouse_id)->toBe($this->warehouse->id);
        });
    });
});

describe('InventoryService reporting', function () {
    it('gets stock card history', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
        $this->service->stockOut($this->product, $this->warehouse, 30);
        $this->service->adjust($this->product, $this->warehouse, 80);

        $stockCard = $this->service->getStockCard($this->product, $this->warehouse);

        expect($stockCard)->toHaveCount(3);
        expect($stockCard->first()->type)->toBe(InventoryMovement::TYPE_IN);
    });

    it('gets stock valuation report', function () {
        $product2 = Product::factory()->create(['track_inventory' => true]);

        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
        $this->service->stockIn($product2, $this->warehouse, 50, 20000);

        $valuation = $this->service->getStockValuation($this->warehouse);

        expect($valuation)->toHaveCount(2);
        expect($valuation->first()->total_value)->toBe(1000000);
    });

    it('includes negative on-hand rows in stock valuation', function () {
        $this->service->stockIn($this->product, $this->warehouse, 10, 10000);

        $stock = ProductStock::query()
            ->where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();
        $stock->forceFill([
            'quantity' => -3,
            'total_value' => -30000,
        ])->save();

        $valuation = $this->service->getStockValuation($this->warehouse);

        expect($valuation->firstWhere('product_id', $this->product->id)->quantity)->toBe(-3);
    });

    it('refuses to clamp an oversell to zero', function () {
        $stock = ProductStock::getOrCreate($this->product, $this->warehouse);
        $stock->forceFill([
            'quantity' => 2,
            'average_cost' => 1000,
            'total_value' => 2000,
        ])->save();

        expect(fn () => $stock->removeStock(5))
            ->toThrow(BusinessRuleException::class, 'Stok tidak mencukupi');

        expect((int) $stock->fresh()->quantity)->toBe(2);
    });

    it('gets inventory summary', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        $summary = $this->service->getInventorySummary($this->warehouse);

        expect($summary)->toHaveKeys(['total_value', 'total_items', 'total_quantity']);
        expect($summary['total_quantity'])->toBe(100);
        expect($summary['total_value'])->toBe(1000000);
    });

    it('gets movement summary for period', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
        $this->service->stockOut($this->product, $this->warehouse, 30);

        $summary = $this->service->getMovementSummary(
            now()->subDay()->toDateString(),
            now()->toDateString(),
            $this->warehouse
        );

        expect($summary)->toHaveKeys(['stock_in', 'stock_out', 'adjustments', 'transfers']);
        expect($summary['stock_in']['count'])->toBe(1);
        expect($summary['stock_out']['count'])->toBe(1);
    });

    it('counts a warehouse-filtered transfer as one transfer not a half', function () {
        $toWarehouse = Warehouse::factory()->create();
        $this->service->stockIn($this->product, $this->warehouse, 40, 10000);
        $this->service->transfer($this->product, $this->warehouse, $toWarehouse, 10);

        $fromSummary = $this->service->getMovementSummary(
            now()->subDay()->toDateString(),
            now()->toDateString(),
            $this->warehouse
        );
        $toSummary = $this->service->getMovementSummary(
            now()->subDay()->toDateString(),
            now()->toDateString(),
            $toWarehouse
        );

        $all = $this->service->getMovementSummary(
            now()->subDay()->toDateString(),
            now()->toDateString()
        );

        expect($fromSummary['transfers']['count'])->toBe(1)
            ->and($toSummary['transfers']['count'])->toBe(1)
            ->and($all['transfers']['count'])->toBe(1);
    });
});
