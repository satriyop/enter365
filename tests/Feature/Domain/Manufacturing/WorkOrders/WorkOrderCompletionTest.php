<?php

declare(strict_types=1);

/**
 * WorkOrderCompletion is the deep implementation behind complete().
 * Tests exercise behaviour only through WorkOrderServiceInterface::complete()
 * (plus a thin check that the pipeline material handler delegates).
 */

use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Domain\Manufacturing\WorkOrders\Handlers\MaterialConsumptionHandler;
use App\Enums\DocumentStatus;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use App\Services\Manufacturing\MaterialRequisitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->workOrderService = app(WorkOrderServiceInterface::class);
    $this->warehouse = Warehouse::factory()->create();
});

it('completes production WO with material consume, FG receipt, and single completion path', function () {
    $finished = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 50000]);
    $raw = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 10000]);

    ProductStock::factory()->create([
        'product_id' => $raw->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => 10,
        'average_cost' => 10000,
        'total_value' => 1_000_000,
    ]);

    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'product_id' => $finished->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_ordered' => 5,
        'quantity_completed' => 0,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $raw->id,
        'description' => $raw->name,
        'quantity_required' => 10,
        'quantity_reserved' => 10,
        'quantity_consumed' => 0,
        'unit_cost' => 10000,
    ]);

    $completed = $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

    expect($completed->status)->toBe(DocumentStatus::Completed)
        ->and((float) $completed->quantity_completed)->toBe(5.0);

    $rawStock = ProductStock::where('product_id', $raw->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($rawStock->quantity)->toBe(90)
        ->and($rawStock->reserved_quantity)->toBe(0);

    $fgStock = ProductStock::where('product_id', $finished->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($fgStock)->not->toBeNull()
        ->and($fgStock->quantity)->toBe(5);

    expect(MaterialConsumption::where('work_order_id', $completed->id)->count())->toBe(1);

    $fgMovement = InventoryMovement::where('reference_type', WorkOrder::class)
        ->where('reference_id', $completed->id)
        ->where('product_id', $finished->id)
        ->where('type', InventoryMovement::TYPE_PRODUCTION)
        ->first();
    expect($fgMovement)->not->toBeNull()
        ->and($fgMovement->quantity)->toBe(5);
});

it('does not double-deduct stock when materials were already issued via MR', function () {
    $finished = Product::factory()->create(['track_inventory' => true]);
    $raw = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 5000]);

    ProductStock::factory()->create([
        'product_id' => $raw->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'reserved_quantity' => 0,
        'average_cost' => 5000,
    ]);

    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'product_id' => $finished->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity_ordered' => 2,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $raw->id,
        'description' => $raw->name,
        'quantity_required' => 8,
        'quantity_reserved' => 0,
        'quantity_consumed' => 0,
        'unit_cost' => 5000,
    ]);

    $mrService = app(MaterialRequisitionService::class);
    $mr = $mrService->create($wo->fresh(['items']));
    $mr = $mrService->approve($mr, $this->user->id);
    $mrItem = $mr->items->firstWhere('product_id', $raw->id);
    $mrService->issue($mr, [
        ['item_id' => $mrItem->id, 'quantity' => 8],
    ], $this->user->id);

    expect(ProductStock::where('product_id', $raw->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'))
        ->toBe(42);

    $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

    expect(ProductStock::where('product_id', $raw->id)->where('warehouse_id', $this->warehouse->id)->value('quantity'))
        ->toBe(42);

    expect(
        InventoryMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $wo->id)
            ->where('product_id', $raw->id)
            ->where('type', InventoryMovement::TYPE_OUT)
            ->count()
    )->toBe(0);
});

it('MaterialConsumptionHandler delegates to WorkOrderMaterialService (no parallel stock rules)', function () {
    $raw = Product::factory()->create(['track_inventory' => true, 'purchase_price' => 1000]);
    ProductStock::factory()->create([
        'product_id' => $raw->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 20,
        'reserved_quantity' => 5,
        'average_cost' => 1000,
    ]);

    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'warehouse_id' => $this->warehouse->id,
        'quantity_ordered' => 1,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $raw->id,
        'description' => $raw->name,
        'quantity_required' => 5,
        'quantity_reserved' => 5,
        'quantity_consumed' => 0,
        'unit_cost' => 1000,
    ]);

    $handler = app(MaterialConsumptionHandler::class);
    expect($handler->shouldHandle($wo->fresh()))->toBeTrue();

    $handler->handle($wo->fresh(['items']), $this->user->id);

    $stock = ProductStock::where('product_id', $raw->id)
        ->where('warehouse_id', $this->warehouse->id)
        ->first();
    expect($stock->quantity)->toBe(15)
        ->and($stock->reserved_quantity)->toBe(0);

    expect(MaterialConsumption::where('work_order_id', $wo->id)->count())->toBe(1);
});
