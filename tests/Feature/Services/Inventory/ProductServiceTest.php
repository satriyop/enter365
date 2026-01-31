<?php

declare(strict_types=1);

use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\ProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(ProductService::class);
});

describe('ProductService CRUD operations', function () {
    it('creates product with auto-generated SKU', function () {
        $product = $this->service->create([
            'name' => 'Test Product',
            'type' => Product::TYPE_PRODUCT,
        ]);

        expect($product)->toBeInstanceOf(Product::class);
        expect($product->sku)->not->toBeNull();
        expect($product->is_active)->toBeTrue();
    });

    it('creates service product without inventory tracking', function () {
        $product = $this->service->create([
            'name' => 'Consulting Service',
            'type' => Product::TYPE_SERVICE,
        ]);

        expect($product->type)->toBe(Product::TYPE_SERVICE);
        expect($product->track_inventory)->toBeFalse();
    });

    it('updates product', function () {
        $product = Product::factory()->create();

        $updated = $this->service->update($product, [
            'name' => 'Updated Product Name',
            'purchase_price' => 150000,
        ]);

        expect($updated->name)->toBe('Updated Product Name');
        expect($updated->purchase_price)->toBe(150000);
    });

    it('duplicates product with new SKU', function () {
        $original = Product::factory()->create([
            'name' => 'Original Product',
            'sku' => 'ORIG-001',
        ]);

        $duplicate = $this->service->duplicate($original);

        expect($duplicate->name)->toBe('Original Product (Copy)');
        expect($duplicate->sku)->not->toBe('ORIG-001');
        expect($duplicate->current_stock)->toBe(0);
    });

    it('activates product', function () {
        $product = Product::factory()->create(['is_active' => false]);

        $activated = $this->service->activate($product);

        expect($activated->is_active)->toBeTrue();
    });

    it('deactivates product', function () {
        $product = Product::factory()->create(['is_active' => true]);

        $deactivated = $this->service->deactivate($product);

        expect($deactivated->is_active)->toBeFalse();
    });
});

describe('ProductService stock adjustment', function () {
    it('adjusts stock using inventory service', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 0,
        ]);
        $warehouse = Warehouse::factory()->create();

        $result = $this->service->adjustStock($product, $warehouse, [
            'quantity' => 100,
            'reason' => 'Initial stock',
        ]);

        expect($result)->toHaveKeys(['previous_stock', 'adjustment', 'current_stock', 'movement_id']);
        expect($result['adjustment'])->toBe(100);
    });

    it('prevents negative stock adjustment', function () {
        $product = Product::factory()->create([
            'track_inventory' => true,
            'current_stock' => 50,
        ]);
        $warehouse = Warehouse::factory()->create();

        expect(function () use ($product, $warehouse) {
            $this->service->adjustStock($product, $warehouse, [
                'quantity' => -60, // Would result in -10
            ]);
        })->toThrow(\App\Exceptions\Domain\ValidationException::class);
    });
});
