<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->warehouse = Warehouse::factory()->create();
    $this->finishedProduct = Product::factory()->create([
        'name' => 'FG Panel',
        'track_inventory' => true,
        'purchase_price' => 100000,
    ]);
    $this->rawMaterial = Product::factory()->create([
        'name' => 'Raw Steel',
        'track_inventory' => true,
        'purchase_price' => 10000,
    ]);

    ProductStock::factory()->create([
        'product_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 20,
        'average_cost' => 10000,
        'total_value' => 1_000_000,
    ]);

    $this->workOrderService = app(WorkOrderServiceInterface::class);
});

test('complete receives finished goods stock and production movement', function () {
    $qtyOrdered = 5;
    $materialQty = 20;

    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'product_id' => $this->finishedProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_ordered' => $qtyOrdered,
        'quantity_completed' => 0,
        'quantity_scrapped' => 0,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $this->rawMaterial->id,
        'description' => $this->rawMaterial->name,
        'quantity_required' => $materialQty,
        'quantity_reserved' => $materialQty,
        'quantity_consumed' => 0,
        'unit_cost' => 10000,
    ]);

    $completed = $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

    expect($completed->status)->toBe(DocumentStatus::Completed)
        ->and((float) $completed->quantity_completed)->toBe((float) $qtyOrdered);

    $fgStock = ProductStock::where('product_id', $this->finishedProduct->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($fgStock)->not->toBeNull()
        ->and($fgStock->quantity)->toBe($qtyOrdered);

    $fgMovement = InventoryMovement::where('reference_type', WorkOrder::class)
        ->where('reference_id', $completed->id)
        ->where('product_id', $this->finishedProduct->id)
        ->first();

    expect($fgMovement)->not->toBeNull()
        ->and($fgMovement->type)->toBe(InventoryMovement::TYPE_PRODUCTION)
        ->and($fgMovement->quantity)->toBe($qtyOrdered)
        ->and($fgMovement->quantity_after)->toBe($qtyOrdered);

    $rawStock = ProductStock::where('product_id', $this->rawMaterial->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($rawStock->quantity)->toBe(80)
        ->and($rawStock->reserved_quantity)->toBe(0);
});

test('complete skips finished goods receipt without warehouse', function () {
    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'product_id' => $this->finishedProduct->id,
        'warehouse_id' => null,
        'quantity_ordered' => 2,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $this->rawMaterial->id,
        'description' => $this->rawMaterial->name,
        'quantity_required' => 1,
        'quantity_reserved' => 0,
        'quantity_consumed' => 0,
        'unit_cost' => 10000,
    ]);

    $completed = $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

    expect($completed->status)->toBe(DocumentStatus::Completed);

    $fgStock = ProductStock::where('product_id', $this->finishedProduct->id)->first();
    expect($fgStock)->toBeNull();

    $fgMovements = InventoryMovement::where('reference_type', WorkOrder::class)
        ->where('reference_id', $completed->id)
        ->where('product_id', $this->finishedProduct->id)
        ->count();

    expect($fgMovements)->toBe(0);
});

test('complete nets scrapped quantity from finished goods receipt', function () {
    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'product_id' => $this->finishedProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_ordered' => 10,
        'quantity_completed' => 10,
        'quantity_scrapped' => 3,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $this->rawMaterial->id,
        'description' => $this->rawMaterial->name,
        'quantity_required' => 5,
        'quantity_reserved' => 5,
        'quantity_consumed' => 0,
        'unit_cost' => 10000,
    ]);

    $completed = $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

    $fgStock = ProductStock::where('product_id', $this->finishedProduct->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();

    expect($fgStock->quantity)->toBe(7);

    $fgMovement = InventoryMovement::where('reference_type', WorkOrder::class)
        ->where('reference_id', $completed->id)
        ->where('product_id', $this->finishedProduct->id)
        ->first();

    expect($fgMovement->quantity)->toBe(7);
});
