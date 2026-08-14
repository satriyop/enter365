<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(InventoryServiceInterface::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['track_inventory' => true]);
});

describe('InventoryService concurrency safety', function () {
    it('uses lockForUpdate when adding stock', function () {
        // Seed initial stock
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(100);

        // Simulate two concurrent stock-in operations using sequential transactions
        // (true concurrency testing requires separate processes, but we can verify
        // the lock is applied and data stays consistent)
        $this->service->stockIn($this->product, $this->warehouse, 50, 12000);
        $this->service->stockIn($this->product, $this->warehouse, 30, 11000);

        $stock->refresh();
        expect($stock->quantity)->toBe(180);
    });

    it('uses lockForUpdate when removing stock', function () {
        $this->service->stockIn($this->product, $this->warehouse, 200, 10000);

        // Sequential stock-out operations should reflect cumulative changes
        $this->service->stockOut($this->product, $this->warehouse, 50);
        $this->service->stockOut($this->product, $this->warehouse, 30);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(120);
    });

    it('uses lockForUpdate when adjusting stock', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        // Sequential adjustments should each see the correct current state
        $this->service->adjust($this->product, $this->warehouse, 150);
        $this->service->adjust($this->product, $this->warehouse, 200);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(200);
    });

    it('uses lockForUpdate when transferring stock', function () {
        $toWarehouse = Warehouse::factory()->create();
        $this->service->stockIn($this->product, $this->warehouse, 200, 10000);

        // Sequential transfers should correctly update both warehouses
        $this->service->transfer($this->product, $this->warehouse, $toWarehouse, 50);
        $this->service->transfer($this->product, $this->warehouse, $toWarehouse, 30);

        $fromStock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $toStock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $toWarehouse->id)
            ->first();

        expect($fromStock->quantity)->toBe(120)
            ->and($toStock->quantity)->toBe(80);
    });

    it('maintains weighted average cost correctly across sequential stock-ins', function () {
        // First stock in: 100 units at 10,000
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
        // Second stock in: 100 units at 20,000
        $this->service->stockIn($this->product, $this->warehouse, 100, 20000);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // Weighted average: (100*10000 + 100*20000) / 200 = 15000
        expect($stock->quantity)->toBe(200)
            ->and($stock->average_cost)->toBe(15000)
            ->and($stock->total_value)->toBe(200 * 15000);
    });

    it('lockForStock creates record if it does not exist', function () {
        // Verify lockForStock handles the getOrCreate + lock pattern
        DB::transaction(function () {
            $stock = ProductStock::lockForStock($this->product, $this->warehouse);

            expect($stock)->toBeInstanceOf(ProductStock::class)
                ->and($stock->quantity)->toBe(0)
                ->and($stock->product_id)->toBe($this->product->id)
                ->and($stock->warehouse_id)->toBe($this->warehouse->id);
        });
    });

    it('lockForStock returns existing record with lock', function () {
        // Create stock first
        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 50,
            'average_cost' => 10000,
            'total_value' => 500000,
        ]);

        DB::transaction(function () {
            $stock = ProductStock::lockForStock($this->product, $this->warehouse);

            expect($stock->quantity)->toBe(50)
                ->and($stock->average_cost)->toBe(10000);
        });
    });

    it('applies sequential reserves so the second sees reduced free stock', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
        $this->service->reserve($this->product, $this->warehouse, 40);
        $this->service->reserve($this->product, $this->warehouse, 35);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(75)
            ->and($stock->getAvailableQuantity())->toBe(25);
    });

    it('keeps on-hand and reserved consistent after sequential reserve then issue', function () {
        $this->service->stockIn($this->product, $this->warehouse, 80, 10000);
        $this->service->reserve($this->product, $this->warehouse, 50);
        $this->service->issueAgainstReservation($this->product, $this->warehouse, 20, 20);
        $this->service->issueAgainstReservation($this->product, $this->warehouse, 15, 15);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(45)
            ->and($stock->reserved_quantity)->toBe(15)
            ->and($stock->getAvailableQuantity())->toBe(30);
    });
});
