<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use App\Services\Manufacturing\WorkOrderMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(WorkOrderMaterialService::class);
    $this->warehouse = Warehouse::factory()->create();
});

describe('WorkOrderMaterialService concurrency safety', function () {
    it('correctly reserves materials with locking', function () {
        $product = Product::factory()->create(['track_inventory' => true]);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
            'average_cost' => 10000,
            'total_value' => 2000000,
        ]);

        $bom = Bom::factory()->create();

        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => DocumentStatus::Confirmed,
            'bom_id' => $bom->id,
        ]);

        WorkOrderItem::factory()->material()->withProduct($product)->create([
            'work_order_id' => $wo->id,
            'quantity_required' => 50,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'unit_cost' => 10000,
        ]);

        $this->service->reserveMaterials($wo->fresh(['items']));

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(50)
            ->and($stock->getAvailableQuantity())->toBe(150);
    });

    it('correctly releases materials with locking', function () {
        $product = Product::factory()->create(['track_inventory' => true]);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 80,
            'average_cost' => 10000,
            'total_value' => 2000000,
        ]);

        $bom = Bom::factory()->create();

        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => DocumentStatus::Confirmed,
            'bom_id' => $bom->id,
        ]);

        WorkOrderItem::factory()->material()->withProduct($product)->create([
            'work_order_id' => $wo->id,
            'quantity_required' => 80,
            'quantity_reserved' => 80,
            'quantity_consumed' => 0,
            'unit_cost' => 10000,
        ]);

        $this->service->releaseMaterials($wo->fresh(['items']));

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(0)
            ->and($stock->getAvailableQuantity())->toBe(200);
    });

    it('correctly consumes materials with locking', function () {
        $product = Product::factory()->create(['track_inventory' => true]);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 50,
            'average_cost' => 10000,
            'total_value' => 2000000,
        ]);

        $bom = Bom::factory()->create();

        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => DocumentStatus::InProgress,
            'bom_id' => $bom->id,
        ]);

        WorkOrderItem::factory()->material()->withProduct($product)->create([
            'work_order_id' => $wo->id,
            'quantity_required' => 50,
            'quantity_reserved' => 50,
            'quantity_consumed' => 0,
            'unit_cost' => 10000,
        ]);

        $this->service->consumeMaterials($wo->fresh(['items']));

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(150)
            ->and($stock->reserved_quantity)->toBe(0);
    });

    it('throws insufficient stock when trying to reserve more than available', function () {
        $product = Product::factory()->create(['track_inventory' => true, 'name' => 'Test Product']);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
            'reserved_quantity' => 10,
            'average_cost' => 10000,
            'total_value' => 300000,
        ]);

        $bom = Bom::factory()->create();

        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => DocumentStatus::Confirmed,
            'bom_id' => $bom->id,
        ]);

        WorkOrderItem::factory()->material()->withProduct($product)->create([
            'work_order_id' => $wo->id,
            'quantity_required' => 25,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'unit_cost' => 10000,
        ]);

        // Available is 30 - 10 = 20, but need 25
        expect(fn () => $this->service->reserveMaterials($wo->fresh(['items'])))
            ->toThrow(\App\Exceptions\Domain\BusinessRuleException::class);
    });

    it('handles sequential reserve and release correctly', function () {
        $product = Product::factory()->create(['track_inventory' => true]);

        ProductStock::create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
            'average_cost' => 10000,
            'total_value' => 1000000,
        ]);

        $bom = Bom::factory()->create();

        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'status' => DocumentStatus::Confirmed,
            'bom_id' => $bom->id,
        ]);

        WorkOrderItem::factory()->material()->withProduct($product)->create([
            'work_order_id' => $wo->id,
            'quantity_required' => 40,
            'quantity_reserved' => 0,
            'quantity_consumed' => 0,
            'unit_cost' => 10000,
        ]);

        // Reserve
        $this->service->reserveMaterials($wo->fresh(['items']));

        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(40);

        // Release
        $this->service->releaseMaterials($wo->fresh(['items']));

        $stock->refresh();
        expect($stock->reserved_quantity)->toBe(0);
    });
});
