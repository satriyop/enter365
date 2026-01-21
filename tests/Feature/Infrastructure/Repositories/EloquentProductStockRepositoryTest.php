<?php

declare(strict_types=1);

use App\Contracts\Repositories\Inventory\ProductStockRepositoryInterface;
use App\Infrastructure\Repositories\Inventory\EloquentProductStockRepository;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->repository = app(ProductStockRepositoryInterface::class);
});

test('repository is bound to eloquent implementation', function () {
    expect($this->repository)->toBeInstanceOf(EloquentProductStockRepository::class);
});

test('getStock returns stock for product and warehouse', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $stock = ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
    ]);

    $found = $this->repository->getStock($product->id, $warehouse->id);

    expect($found)->not->toBeNull()
        ->and($found->id)->toBe($stock->id)
        ->and((float) $found->quantity)->toBe(100.0);
});

test('getStock returns null when stock does not exist', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $found = $this->repository->getStock($product->id, $warehouse->id);

    expect($found)->toBeNull();
});

test('getOrCreateStock creates stock if not exists', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $stock = $this->repository->getOrCreateStock($product->id, $warehouse->id);

    expect($stock)->not->toBeNull()
        ->and($stock->product_id)->toBe($product->id)
        ->and($stock->warehouse_id)->toBe($warehouse->id)
        ->and((float) $stock->quantity)->toBe(0.0);

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
    ]);
});

test('getOrCreateStock returns existing stock', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $existing = ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
    ]);

    $stock = $this->repository->getOrCreateStock($product->id, $warehouse->id);

    expect($stock->id)->toBe($existing->id)
        ->and((float) $stock->quantity)->toBe(50.0);
});

test('getStockByProduct returns all stock for product', function () {
    $product = Product::factory()->create();
    $warehouses = Warehouse::factory()->count(3)->create();

    foreach ($warehouses as $warehouse) {
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
        ]);
    }

    // Create stock for different product
    ProductStock::factory()->create();

    $stocks = $this->repository->getStockByProduct($product->id);

    expect($stocks)->toHaveCount(3)
        ->each(fn ($stock) => $stock->product_id->toBe($product->id));
});

test('getStockByWarehouse returns stock with positive quantity', function () {
    $warehouse = Warehouse::factory()->create();

    // Stock with quantity > 0
    ProductStock::factory()->count(2)->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
    ]);

    // Stock with zero quantity (should not be included)
    ProductStock::factory()->create([
        'warehouse_id' => $warehouse->id,
        'quantity' => 0,
    ]);

    $stocks = $this->repository->getStockByWarehouse($warehouse->id);

    expect($stocks)->toHaveCount(2);
});

test('getTotalAvailable returns sum of available quantities', function () {
    $product = Product::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'quantity' => 100,
        'reserved_quantity' => 20,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'quantity' => 50,
        'reserved_quantity' => 10,
    ]);

    $total = $this->repository->getTotalAvailable($product->id);

    // (100 - 20) + (50 - 10) = 120
    expect($total)->toBe(120.0);
});

test('hasSufficientStock returns true when enough available', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 20,
    ]);

    $result = $this->repository->hasSufficientStock($product->id, $warehouse->id, 50);

    expect($result)->toBeTrue();
});

test('hasSufficientStock returns false when not enough available', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 80,
    ]);

    $result = $this->repository->hasSufficientStock($product->id, $warehouse->id, 50);

    expect($result)->toBeFalse();
});

test('hasSufficientStock returns false when stock does not exist', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    $result = $this->repository->hasSufficientStock($product->id, $warehouse->id, 10);

    expect($result)->toBeFalse();
});

test('findLowStock returns products below min stock level', function () {
    $productWithMinStock = Product::factory()->create(['min_stock' => 50]);
    $productNoMinStock = Product::factory()->create(['min_stock' => 0]);

    // Low stock (below min_stock level)
    ProductStock::factory()->create([
        'product_id' => $productWithMinStock->id,
        'quantity' => 30,
        'reserved_quantity' => 0,
    ]);

    // Product without min_stock set (should not be included)
    ProductStock::factory()->create([
        'product_id' => $productNoMinStock->id,
        'quantity' => 5,
        'reserved_quantity' => 0,
    ]);

    $lowStock = $this->repository->findLowStock();

    expect($lowStock)->toHaveCount(1)
        ->and($lowStock->first()->product_id)->toBe($productWithMinStock->id);
});

test('reserve increases reserved quantity', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 10,
    ]);

    $result = $this->repository->reserve($product->id, $warehouse->id, 30);

    expect($result)->toBeTrue();

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'reserved_quantity' => 40,
    ]);
});

test('reserve returns false when insufficient stock', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 50,
        'reserved_quantity' => 40,
    ]);

    $result = $this->repository->reserve($product->id, $warehouse->id, 20);

    expect($result)->toBeFalse();
});

test('releaseReservation decreases reserved quantity', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 50,
    ]);

    $result = $this->repository->releaseReservation($product->id, $warehouse->id, 20);

    expect($result)->toBeTrue();

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'reserved_quantity' => 30,
    ]);
});

test('releaseReservation does not go below zero', function () {
    $product = Product::factory()->create();
    $warehouse = Warehouse::factory()->create();

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 10,
    ]);

    $result = $this->repository->releaseReservation($product->id, $warehouse->id, 50);

    expect($result)->toBeTrue();

    $this->assertDatabaseHas('product_stocks', [
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'reserved_quantity' => 0,
    ]);
});
