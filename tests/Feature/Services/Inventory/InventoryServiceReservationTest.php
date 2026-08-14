<?php

declare(strict_types=1);

use App\Contracts\Inventory\InventoryServiceInterface;
use App\Exceptions\Domain\InsufficientStockException;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Inventory\InventoryCostLayer;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(InventoryServiceInterface::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['track_inventory' => true]);
});

it('reserves stock and reduces free stock', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

    $this->service->reserve($this->product, $this->warehouse, 40);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(100)
        ->and($stock->reserved_quantity)->toBe(40)
        ->and($stock->getAvailableQuantity())->toBe(60);
});

it('fails to reserve more than free stock', function () {
    $this->service->stockIn($this->product, $this->warehouse, 50, 10000);

    $this->service->reserve($this->product, $this->warehouse, 60);
})->throws(InsufficientStockException::class);

it('releases reserved stock without changing on-hand', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
    $this->service->reserve($this->product, $this->warehouse, 40);

    $this->service->release($this->product, $this->warehouse, 25);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(100)
        ->and($stock->reserved_quantity)->toBe(15);
});

it('does not release reserved stock below zero', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
    $this->service->reserve($this->product, $this->warehouse, 10);

    $this->service->release($this->product, $this->warehouse, 50);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->reserved_quantity)->toBe(0)
        ->and($stock->quantity)->toBe(100);
});

it('refuses stock-out when only reserved stock remains', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
    $this->service->reserve($this->product, $this->warehouse, 100);

    $this->service->stockOut($this->product, $this->warehouse, 10);
})->throws(InsufficientStockException::class);

it('issues against reservation and lowers on-hand and reserved', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
    $this->service->reserve($this->product, $this->warehouse, 40);

    $movement = $this->service->issueAgainstReservation(
        $this->product,
        $this->warehouse,
        15,
        15,
        'Issue MR line',
        'App\\Models\\Manufacturing\\MaterialRequisition',
        7,
    );

    expect($movement->type)->toBe(InventoryMovement::TYPE_OUT)
        ->and($movement->quantity)->toBe(-15)
        ->and($movement->reference_id)->toBe(7);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(85)
        ->and($stock->reserved_quantity)->toBe(25)
        ->and($stock->getAvailableQuantity())->toBe(60);
});

it('issues the full reserved amount and clears reservation', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);
    $this->service->reserve($this->product, $this->warehouse, 40);

    $this->service->issueAgainstReservation($this->product, $this->warehouse, 40, 40);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(60)
        ->and($stock->reserved_quantity)->toBe(0)
        ->and($stock->getAvailableQuantity())->toBe(60);
});

it('does not steal another reservation when issuing against own reserved amount', function () {
    $this->service->stockIn($this->product, $this->warehouse, 20, 10000);
    $this->service->reserve($this->product, $this->warehouse, 20);

    expect(fn () => $this->service->issueAgainstReservation(
        $this->product,
        $this->warehouse,
        10,
        3,
    ))->toThrow(InsufficientStockException::class);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(20)
        ->and($stock->reserved_quantity)->toBe(20);
});

it('runs costing strategy when issuing against reservation', function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
    AccountingPolicy::query()->update(['costing_method' => 'fifo']);
    Once::flush();

    $this->service->stockIn($this->product, $this->warehouse, 50, 10000);
    $this->service->reserve($this->product, $this->warehouse, 20);

    $movement = $this->service->issueAgainstReservation(
        $this->product,
        $this->warehouse,
        10,
        10,
    );

    expect($movement->unit_cost)->toBe(10000);

    $layers = InventoryCostLayer::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->get();

    expect($layers)->toHaveCount(1)
        ->and($layers[0]->quantity)->toBe(40);
});

it('keeps reserved and on-hand consistent across sequential reserve and issue', function () {
    $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

    $this->service->reserve($this->product, $this->warehouse, 30);
    $this->service->reserve($this->product, $this->warehouse, 20);
    $this->service->issueAgainstReservation($this->product, $this->warehouse, 25, 25);
    $this->service->release($this->product, $this->warehouse, 10);

    $stock = ProductStock::where('product_id', $this->product->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($stock->quantity)->toBe(75)
        ->and($stock->reserved_quantity)->toBe(15)
        ->and($stock->getAvailableQuantity())->toBe(60);
});
