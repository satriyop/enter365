<?php

declare(strict_types=1);

use App\Domain\Inventory\Movements\Events\InventoryAdjusted;
use App\Domain\Inventory\Movements\Events\InventoryIssued;
use App\Domain\Inventory\Movements\Events\InventoryReceived;
use App\Domain\Inventory\Movements\Events\InventoryTransferred;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(InventoryService::class);
    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create(['track_inventory' => true]);
});

describe('InventoryService event dispatching', function () {
    it('dispatches InventoryReceived on stock in', function () {
        Event::fake([InventoryReceived::class]);

        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        Event::assertDispatched(InventoryReceived::class, function (InventoryReceived $event) {
            return $event->productId === $this->product->id
                && $event->warehouseId === $this->warehouse->id
                && $event->quantity === 100.0;
        });
    });

    it('dispatches InventoryReceived on production receipt', function () {
        Event::fake([InventoryReceived::class]);

        $this->service->stockIn(
            $this->product,
            $this->warehouse,
            5,
            20000,
            'Hasil produksi',
            null,
            null,
            InventoryMovement::TYPE_PRODUCTION,
        );

        Event::assertDispatched(InventoryReceived::class, function (InventoryReceived $event) {
            return $event->productId === $this->product->id
                && $event->quantity === 5.0;
        });
    });

    it('dispatches InventoryIssued on stock out', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        Event::fake([InventoryIssued::class]);

        $this->service->stockOut($this->product, $this->warehouse, 30);

        Event::assertDispatched(InventoryIssued::class, function (InventoryIssued $event) {
            return $event->productId === $this->product->id
                && $event->warehouseId === $this->warehouse->id
                && $event->quantity === 30.0;
        });
    });

    it('dispatches InventoryAdjusted on stock adjustment', function () {
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        Event::fake([InventoryAdjusted::class]);

        $this->service->adjust($this->product, $this->warehouse, 150);

        Event::assertDispatched(InventoryAdjusted::class, function (InventoryAdjusted $event) {
            return $event->productId === $this->product->id
                && $event->previousQuantity === 100.0
                && $event->newQuantity === 150.0
                && $event->adjustmentQuantity === 50.0;
        });
    });

    it('dispatches InventoryTransferred on stock transfer', function () {
        $toWarehouse = Warehouse::factory()->create();
        $this->service->stockIn($this->product, $this->warehouse, 100, 10000);

        Event::fake([InventoryTransferred::class]);

        $this->service->transfer($this->product, $this->warehouse, $toWarehouse, 40);

        Event::assertDispatched(InventoryTransferred::class, function (InventoryTransferred $event) use ($toWarehouse) {
            return $event->productId === $this->product->id
                && $event->fromWarehouseId === $this->warehouse->id
                && $event->toWarehouseId === $toWarehouse->id
                && $event->quantity === 40.0;
        });
    });

    it('includes movement ID in dispatched events', function () {
        Event::fake([InventoryReceived::class]);

        $movement = $this->service->stockIn($this->product, $this->warehouse, 50, 10000);

        Event::assertDispatched(InventoryReceived::class, function (InventoryReceived $event) use ($movement) {
            return $event->movementId === $movement->id;
        });
    });

    it('includes user ID in dispatched events', function () {
        Event::fake([InventoryReceived::class]);

        $this->service->stockIn($this->product, $this->warehouse, 50, 10000);

        Event::assertDispatched(InventoryReceived::class, function (InventoryReceived $event) {
            return $event->userId === $this->user->id;
        });
    });
});
