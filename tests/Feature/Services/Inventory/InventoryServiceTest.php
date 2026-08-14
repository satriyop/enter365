<?php

declare(strict_types=1);

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
});
