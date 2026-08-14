<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\MaterialRequisitionServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Inventory\InventoryMovement;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->service = app(MaterialRequisitionServiceInterface::class);

    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create([
        'name' => 'Test Material',
        'purchase_price' => 50000,
        'unit' => 'pcs',
    ]);
});

describe('MaterialRequisitionService - create', function () {
    test('creates material requisition from work order', function () {
        $wo = WorkOrder::factory()
            ->withWarehouse($this->warehouse)
            ->create(['quantity_ordered' => 10]);

        $mr = $this->service->create($wo);

        expect($mr)
            ->toBeInstanceOf(MaterialRequisition::class)
            ->status->toBe(DocumentStatus::Draft)
            ->requisition_number->not->toBeEmpty()
            ->work_order_id->toBe($wo->id)
            ->warehouse_id->toBe($this->warehouse->id)
            ->total_items->toBe(0);

        expect((float) $mr->total_quantity)->toBe(0.0);
    });

    test('creates material requisition with custom data', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();
        $requiredDate = now()->addDays(5)->toDateString();

        $mr = $this->service->create($wo, [
            'required_date' => $requiredDate,
            'notes' => 'Urgent materials needed',
        ]);

        expect($mr)
            ->required_date->toDateString()->toBe($requiredDate)
            ->notes->toBe('Urgent materials needed');
    });

    test('populates material requisition items from work order material items', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($this->product)
            ->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'quantity_required' => 100,
                'quantity_consumed' => 0,
            ])
            ->create();

        $mr = $this->service->create($wo);

        expect($mr)
            ->items->toHaveCount(1)
            ->total_items->toBe(1);

        expect((float) $mr->total_quantity)->toBe(100.0);

        $item = $mr->items->first();
        expect($item)
            ->product_id->toBe($this->product->id);

        expect((float) $item->quantity_requested)->toBe(100.0);
        expect((float) $item->quantity_approved)->toBe(0.0);
        expect((float) $item->quantity_issued)->toBe(0.0);
        expect((float) $item->quantity_pending)->toBe(0.0);
    });

    test('populates only remaining quantity when materials partially consumed', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($this->product)
            ->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'quantity_required' => 100,
                'quantity_consumed' => 30,
            ])
            ->create();

        $mr = $this->service->create($wo);

        $item = $mr->items->first();
        expect((float) $item->quantity_requested)->toBe(70.0);
    });

    test('skips work order items without product', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'product_id' => null,
                'quantity_required' => 100,
            ])
            ->create();

        $mr = $this->service->create($wo);

        expect($mr)
            ->items->toHaveCount(0)
            ->total_items->toBe(0);
    });

    test('skips fully consumed material items', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($this->product)
            ->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'quantity_required' => 100,
                'quantity_consumed' => 100,
            ])
            ->create();

        $mr = $this->service->create($wo);

        expect($mr)
            ->items->toHaveCount(0)
            ->total_items->toBe(0);
    });

    test('populates multiple material items', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();

        $product2 = Product::factory()->create();
        $product3 = Product::factory()->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($this->product)
            ->state(['type' => WorkOrderItem::TYPE_MATERIAL, 'quantity_required' => 100])
            ->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($product2)
            ->state(['type' => WorkOrderItem::TYPE_MATERIAL, 'quantity_required' => 50])
            ->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($product3)
            ->state(['type' => WorkOrderItem::TYPE_MATERIAL, 'quantity_required' => 75])
            ->create();

        $mr = $this->service->create($wo);

        expect($mr)
            ->items->toHaveCount(3)
            ->total_items->toBe(3);

        expect((float) $mr->total_quantity)->toBe(225.0);
    });
});

describe('MaterialRequisitionService - update', function () {
    test('updates draft material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->withWarehouse($this->warehouse)
            ->create(['notes' => 'Old notes']);

        $updated = $this->service->update($mr, [
            'notes' => 'Updated notes',
            'required_date' => now()->addDays(7)->toDateString(),
        ]);

        expect($updated)
            ->notes->toBe('Updated notes')
            ->required_date->toDateString()->toBe(now()->addDays(7)->toDateString());
    });

    test('updates material requisition with new items', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();
        MaterialRequisitionItem::factory()->forRequisition($mr)->count(2)->create();

        $product2 = Product::factory()->create();

        $updated = $this->service->update($mr, [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_requested' => 150,
                    'unit' => 'pcs',
                ],
                [
                    'product_id' => $product2->id,
                    'quantity_requested' => 200,
                    'unit' => 'kg',
                ],
            ],
        ]);

        expect($updated)
            ->items->toHaveCount(2)
            ->total_items->toBe(2);

        expect((float) $updated->total_quantity)->toBe(350.0);
    });

    test('replaces existing items when updating with items array', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();
        $oldItem = MaterialRequisitionItem::factory()->forRequisition($mr)->create();

        $updated = $this->service->update($mr, [
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'quantity_requested' => 100,
                    'unit' => 'pcs',
                ],
            ],
        ]);

        expect($updated->items->contains($oldItem))->toBeFalse()
            ->and(MaterialRequisitionItem::find($oldItem->id))->toBeNull();
    });

    test('throws exception when updating non-draft material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $this->service->update($mr, ['notes' => 'Test']);
    })->throws(DocumentLockedException::class);
});

describe('MaterialRequisitionService - delete', function () {
    test('deletes draft material requisition with items', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();
        MaterialRequisitionItem::factory()->forRequisition($mr)->count(3)->create();

        $mrId = $mr->id;

        $result = $this->service->delete($mr);

        expect($result)->toBeTrue()
            ->and(MaterialRequisition::find($mrId))->toBeNull()
            ->and(MaterialRequisitionItem::where('material_requisition_id', $mrId)->count())->toBe(0);
    });

    test('throws exception when deleting non-draft material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $this->service->delete($mr);
    })->throws(DocumentLockedException::class);
});

describe('MaterialRequisitionService - approve', function () {
    test('approves draft material requisition', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();
        MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state(['quantity_requested' => 100])
            ->count(2)
            ->create();

        $approved = $this->service->approve($mr);

        expect($approved)
            ->status->toBe(DocumentStatus::Approved)
            ->approved_at->not->toBeNull();

        $approved->items->each(function ($item) {
            expect((float) $item->quantity_approved)->toBe((float) $item->quantity_requested);
            expect((float) $item->quantity_pending)->toBe((float) $item->quantity_requested);
            expect((float) $item->quantity_issued)->toBe(0.0);
        });
    });

    test('approves with custom user id', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();
        MaterialRequisitionItem::factory()->forRequisition($mr)->create();

        $customUser = \App\Models\User::factory()->create();
        $approved = $this->service->approve($mr, $customUser->id);

        expect($approved->approved_by)->toBe($customUser->id);
    });

    test('throws exception when approving non-draft material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $this->service->approve($mr);
    })->throws(StateTransitionException::class);
});

describe('MaterialRequisitionService - issue', function () {
    test('issues materials partially', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        $issued = $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 50],
        ]);

        expect($issued)
            ->status->toBe(DocumentStatus::Partial)
            ->issued_at->not->toBeNull();

        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(50.0);
        expect((float) $item->quantity_pending)->toBe(50.0);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(150); // 200 - 50
    });

    test('issues all materials and transitions to issued status', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        $issued = $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 100],
        ]);

        expect($issued)
            ->status->toBe(DocumentStatus::Issued);

        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(100.0);
        expect((float) $item->quantity_pending)->toBe(0.0);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(100); // 200 - 100

        expect(\App\Models\Inventory\InventoryMovement::where('reference_type', MaterialRequisition::class)
            ->where('reference_id', $mr->id)
            ->count())->toBe(1);
    });

    test('issues multiple items partially', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $product2 = Product::factory()->create();

        $item1 = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        $item2 = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $product2->id,
                'quantity_requested' => 50,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        ProductStock::create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $issued = $this->service->issue($mr, [
            ['item_id' => $item1->id, 'quantity' => 40],
            ['item_id' => $item2->id, 'quantity' => 25],
        ]);

        expect($issued->status)->toBe(DocumentStatus::Partial);

        $item1->refresh();
        $item2->refresh();

        expect((float) $item1->quantity_issued)->toBe(40.0);
        expect((float) $item1->quantity_pending)->toBe(60.0);

        expect((float) $item2->quantity_issued)->toBe(25.0);
        expect((float) $item2->quantity_pending)->toBe(25.0);
    });

    test('accumulates issued quantities on multiple issue calls', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        // First partial issue
        $this->service->issue($mr, [['item_id' => $item->id, 'quantity' => 40]]);
        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(40.0);
        expect((float) $item->quantity_pending)->toBe(60.0);

        $mr->refresh();
        expect($mr->status)->toBe(DocumentStatus::Partial);

        // Complete the remaining quantity
        $this->service->issue($mr, [['item_id' => $item->id, 'quantity' => 60]]);
        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(100.0);
        expect((float) $item->quantity_pending)->toBe(0.0);

        $mr->refresh();
        expect($mr->status)->toBe(DocumentStatus::Issued);
    });

    test('throws exception when issuing more than pending quantity', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 500,
            'reserved_quantity' => 0,
        ]);

        $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 150],
        ]);
    })->throws(BusinessRuleException::class);

    test('throws exception when insufficient stock', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 30,
            'reserved_quantity' => 0,
        ]);

        $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 50],
        ]);
    })->throws(BusinessRuleException::class);

    test('respects reserved quantity when checking stock availability', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 60,
        ]);

        $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 50],
        ]);
    })->throws(BusinessRuleException::class);

    test('issues against work order reservation and records production movement via inventory seam', function () {
        $wo = WorkOrder::factory()
            ->withWarehouse($this->warehouse)
            ->create();

        $woItem = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'product_id' => $this->product->id,
            'quantity_required' => 40,
            'quantity_reserved' => 40,
        ]);

        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create(['work_order_id' => $wo->id]);

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'work_order_item_id' => $woItem->id,
                'quantity_requested' => 40,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 40,
            'average_cost' => 50000,
            'total_value' => 5_000_000,
        ]);

        $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 15],
        ]);

        $stock = ProductStock::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        expect($stock->quantity)->toBe(85)
            ->and($stock->reserved_quantity)->toBe(25);

        $woItem->refresh();
        expect((float) $woItem->quantity_reserved)->toBe(25.0);

        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(15.0);

        $movement = InventoryMovement::where('reference_type', MaterialRequisition::class)
            ->where('reference_id', $mr->id)
            ->where('product_id', $this->product->id)
            ->first();

        expect($movement)->not->toBeNull()
            ->and($movement->type)->toBe(InventoryMovement::TYPE_OUT)
            ->and($movement->quantity)->toBe(-15);
    });

    test('throws exception when issuing from non-approved material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->withWarehouse($this->warehouse)
            ->create(['status' => DocumentStatus::Draft]);

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->create();

        $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 10],
        ]);
    })->throws(StateTransitionException::class);

    test('sets issued_by user id', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($mr)
            ->state([
                'product_id' => $this->product->id,
                'quantity_requested' => 100,
            ])
            ->approved()
            ->create();

        ProductStock::create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 200,
            'reserved_quantity' => 0,
        ]);

        $customUser = \App\Models\User::factory()->create();
        $issued = $this->service->issue($mr, [
            ['item_id' => $item->id, 'quantity' => 50],
        ], $customUser->id);

        expect($issued->issued_by)->toBe($customUser->id);
    });
});

describe('MaterialRequisitionService - cancel', function () {
    test('cancels draft material requisition', function () {
        $mr = MaterialRequisition::factory()->withWarehouse($this->warehouse)->create();

        $cancelled = $this->service->cancel($mr, 'Not needed anymore');

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled);
    });

    test('cancels approved material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($this->warehouse)
            ->create();

        $cancelled = $this->service->cancel($mr, 'Plan changed');

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled);
    });

    test('cancels partially issued material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->partial()
            ->withWarehouse($this->warehouse)
            ->create();

        $cancelled = $this->service->cancel($mr, 'Project cancelled');

        expect($cancelled)
            ->status->toBe(DocumentStatus::Cancelled);
    });

    test('throws exception when cancelling already cancelled material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->cancelled()
            ->withWarehouse($this->warehouse)
            ->create();

        $this->service->cancel($mr, 'Test');
    })->throws(StateTransitionException::class);

    test('throws exception when cancelling fully issued material requisition', function () {
        $mr = MaterialRequisition::factory()
            ->issued()
            ->withWarehouse($this->warehouse)
            ->create();

        $this->service->cancel($mr, 'Test');
    })->throws(StateTransitionException::class);
});

describe('MaterialRequisitionService - populateFromWorkOrder', function () {
    test('populates items from work order material items directly', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();
        $mr = MaterialRequisition::factory()
            ->forWorkOrder($wo)
            ->create(['total_items' => 0, 'total_quantity' => 0]);

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->withProduct($this->product)
            ->state([
                'type' => WorkOrderItem::TYPE_MATERIAL,
                'quantity_required' => 150,
                'quantity_consumed' => 0,
            ])
            ->create();

        $this->service->populateFromWorkOrder($mr, $wo);

        $mr->refresh();
        expect($mr)
            ->items->toHaveCount(1)
            ->total_items->toBe(1);

        expect((float) $mr->total_quantity)->toBe(150.0);
    });

    test('ignores labor and overhead items', function () {
        $wo = WorkOrder::factory()->withWarehouse($this->warehouse)->create();
        $mr = MaterialRequisition::factory()
            ->forWorkOrder($wo)
            ->create(['total_items' => 0, 'total_quantity' => 0]);

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->labor()
            ->state(['quantity_required' => 40])
            ->create();

        WorkOrderItem::factory()
            ->forWorkOrder($wo)
            ->overhead()
            ->state(['quantity_required' => 20])
            ->create();

        $this->service->populateFromWorkOrder($mr, $wo);

        $mr->refresh();
        expect($mr)
            ->items->toHaveCount(0)
            ->total_items->toBe(0);
    });
});
