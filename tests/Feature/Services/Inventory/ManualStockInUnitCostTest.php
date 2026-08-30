<?php

declare(strict_types=1);

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\ManualStockInUnitCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('keeps an explicit positive unit cost', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 11000]);

    expect(ManualStockInUnitCost::resolve($product, $warehouse, 15000))->toBe(15000);
});

it('uses warehouse average cost when entered cost is zero', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 5000]);
    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'average_cost' => 11000,
    ]);

    expect(ManualStockInUnitCost::resolve($product, $warehouse, 0))->toBe(11000);
});

it('uses purchase price when entered cost is zero and no stock exists', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 11000]);

    expect(ManualStockInUnitCost::resolve($product, $warehouse, 0))->toBe(11000);
});

it('rejects zero cost when there is no current average or purchase price', function () {
    $warehouse = Warehouse::factory()->create();
    $product = Product::factory()->create(['purchase_price' => 0]);

    expect(fn () => ManualStockInUnitCost::resolve($product, $warehouse, 0))
        ->toThrow(ValidationException::class);
});
