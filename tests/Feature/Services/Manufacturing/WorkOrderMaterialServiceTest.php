<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Services\Manufacturing\WorkOrderMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(WorkOrderMaterialService::class);

    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create([
        'name' => 'MCB 16A',
        'unit' => 'pcs',
        'purchase_price' => 150000,
    ]);
});

describe('getMaterialStatus', function () {
    test('returns correct structure', function () {
        $wo = WorkOrder::factory()->create([
            'wo_number' => 'WO-MAT-001',
            'warehouse_id' => $this->warehouse->id,
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'description' => 'MCB 16A',
            'quantity_required' => 10,
            'quantity_reserved' => 5,
            'quantity_consumed' => 0,
            'unit' => 'pcs',
        ]);

        $result = $this->service->getMaterialStatus($wo);

        expect($result)->toHaveKeys(['work_order_id', 'wo_number', 'materials', 'summary'])
            ->and($result['work_order_id'])->toBe($wo->id)
            ->and($result['wo_number'])->toBe('WO-MAT-001')
            ->and($result['materials'])->toHaveCount(1);

        $material = $result['materials'][0];
        expect($material)->toHaveKeys([
            'item_id', 'product_id', 'product_name',
            'quantity_required', 'quantity_reserved', 'quantity_consumed',
            'quantity_remaining', 'unit', 'status',
        ])
            ->and($material['product_name'])->toBe('MCB 16A')
            ->and($material['status'])->toBe('pending');
    });

    test('correctly classifies item statuses in summary', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Fully consumed item
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
            'quantity_consumed' => 10,
        ]);

        // Partially consumed item
        $product2 = Product::factory()->create();
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $product2->id,
            'quantity_required' => 20,
            'quantity_consumed' => 8,
        ]);

        // Pending item
        $product3 = Product::factory()->create();
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $product3->id,
            'quantity_required' => 5,
            'quantity_consumed' => 0,
        ]);

        $result = $this->service->getMaterialStatus($wo);

        expect($result['summary'])->toBe([
            'total_items' => 3,
            'fully_consumed' => 1,
            'partially_consumed' => 1,
            'pending' => 1,
        ]);
    });

    test('excludes labor and overhead items', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        // Material item
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
        ]);

        // Labor item — should be excluded
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_LABOR,
            'quantity_required' => 8,
        ]);

        // Overhead item — should be excluded
        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_OVERHEAD,
            'quantity_required' => 1,
        ]);

        $result = $this->service->getMaterialStatus($wo);

        expect($result['materials'])->toHaveCount(1)
            ->and($result['summary']['total_items'])->toBe(1);
    });
});

describe('reserveMaterials', function () {
    test('reserves stock for material items', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 0,
        ]);

        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $this->service->reserveMaterials($wo);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(10);

        $item = WorkOrderItem::where('work_order_id', $wo->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect((float) $item->quantity_reserved)->toEqual(10.0);
    });

    test('throws exception when insufficient stock', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 100,
            'quantity_reserved' => 0,
        ]);

        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        expect(fn () => $this->service->reserveMaterials($wo))
            ->toThrow(BusinessRuleException::class);
    });
});

describe('releaseMaterials', function () {
    test('releases previously reserved stock', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 10,
        ]);

        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 10,
        ]);

        $this->service->releaseMaterials($wo);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->reserved_quantity)->toBe(0);

        $item = WorkOrderItem::where('work_order_id', $wo->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect((float) $item->quantity_reserved)->toEqual(0.0);
    });
});

describe('consumeMaterials', function () {
    test('deducts from inventory and creates movement record', function () {
        $wo = WorkOrder::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'wo_number' => 'WO-CONSUME-001',
        ]);

        WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 10,
            'unit_cost' => 150000,
        ]);

        ProductStock::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 10,
            'average_cost' => 150000,
        ]);

        $this->service->consumeMaterials($wo);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        // quantity: 100 - 10 = 90, reserved: 10 - 10 = 0
        expect($stock->quantity)->toBe(90)
            ->and($stock->reserved_quantity)->toBe(0);

        // Verify inventory movement created
        $movement = InventoryMovement::where('reference_type', WorkOrder::class)
            ->where('reference_id', $wo->id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and($movement->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movement->quantity)->toBe(10)
            ->and($movement->product_id)->toBe($this->product->id);

        // Verify WO item updated
        $item = WorkOrderItem::where('work_order_id', $wo->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect((float) $item->quantity_consumed)->toEqual(10.0);
    });
});

describe('recordConsumption', function () {
    test('creates material consumption records', function () {
        $wo = WorkOrder::factory()->create([
            'status' => DocumentStatus::InProgress,
            'warehouse_id' => $this->warehouse->id,
        ]);

        $woItem = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'type' => WorkOrderItem::TYPE_MATERIAL,
            'product_id' => $this->product->id,
            'quantity_required' => 10,
            'quantity_consumed' => 0,
            'unit_cost' => 150000,
        ]);

        $this->service->recordConsumption($wo, [
            [
                'work_order_item_id' => $woItem->id,
                'product_id' => $this->product->id,
                'quantity_consumed' => 8,
                'quantity_scrapped' => 1,
                'scrap_reason' => 'Rusak saat pemasangan',
                'unit' => 'pcs',
                'unit_cost' => 150000,
            ],
        ]);

        $consumption = MaterialConsumption::where('work_order_id', $wo->id)->first();

        expect($consumption)->not->toBeNull()
            ->and($consumption->product_id)->toBe($this->product->id)
            ->and((float) $consumption->quantity_consumed)->toEqual(8.0)
            ->and((float) $consumption->quantity_scrapped)->toEqual(1.0)
            ->and($consumption->scrap_reason)->toBe('Rusak saat pemasangan');

        // WO item consumed should update: 0 + 8 + 1 = 9
        $woItem->refresh();
        expect((float) $woItem->quantity_consumed)->toEqual(9.0);
    });

    test('throws exception when work order is not in progress', function () {
        $wo = WorkOrder::factory()->create([
            'status' => DocumentStatus::Draft,
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect(fn () => $this->service->recordConsumption($wo, [
            [
                'product_id' => $this->product->id,
                'quantity_consumed' => 5,
            ],
        ]))->toThrow(StateTransitionException::class);
    });
});
