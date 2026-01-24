<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialRequisition;
use App\Models\Manufacturing\MaterialRequisitionItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Material Requisition CRUD', function () {

    it('can list all material requisitions', function () {
        MaterialRequisition::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/material-requisitions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter material requisitions by status', function () {
        MaterialRequisition::factory()->draft()->count(2)->create();
        MaterialRequisition::factory()->approved()->count(3)->create();

        $response = $this->getJson('/api/v1/material-requisitions?status=approved');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter material requisitions by work order', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();
        MaterialRequisition::factory()->forWorkOrder($workOrder)->count(2)->create();
        MaterialRequisition::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/material-requisitions?work_order_id={$workOrder->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search material requisitions', function () {
        MaterialRequisition::factory()->create(['requisition_number' => 'MR-TEST-001']);
        MaterialRequisition::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/material-requisitions?search=TEST');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can show a single material requisition with items', function () {
        $requisition = MaterialRequisition::factory()->create();
        MaterialRequisitionItem::factory()->forRequisition($requisition)->count(3)->create();

        $response = $this->getJson("/api/v1/material-requisitions/{$requisition->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $requisition->id)
            ->assertJsonCount(3, 'data.items');
    });
});

describe('Material Requisition from Work Order', function () {

    it('can create material requisition for confirmed work order', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $workOrder = WorkOrder::factory()
            ->confirmed()
            ->withWarehouse($warehouse)
            ->create();

        WorkOrderItem::factory()
            ->forWorkOrder($workOrder)
            ->material()
            ->create([
                'product_id' => $product->id,
                'quantity_required' => 10,
            ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/material-requisitions");

        $response->assertCreated()
            ->assertJsonPath('data.work_order_id', $workOrder->id)
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(1, 'data.items');
    });

    it('can create material requisition for in-progress work order', function () {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/material-requisitions");

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft');
    });

    it('cannot create material requisition for draft work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/material-requisitions");

        $response->assertUnprocessable();
    });

    it('can update draft material requisition', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();

        $response = $this->putJson("/api/v1/material-requisitions/{$requisition->id}", [
            'required_date' => '2025-02-01',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update approved material requisition', function () {
        $requisition = MaterialRequisition::factory()->approved()->create();

        $response = $this->putJson("/api/v1/material-requisitions/{$requisition->id}", [
            'notes' => 'Updated notes',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete draft material requisition', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/material-requisitions/{$requisition->id}");

        $response->assertOk();
        $this->assertSoftDeleted('material_requisitions', ['id' => $requisition->id]);
    });

    it('cannot delete approved material requisition', function () {
        $requisition = MaterialRequisition::factory()->approved()->create();

        $response = $this->deleteJson("/api/v1/material-requisitions/{$requisition->id}");

        $response->assertUnprocessable();
    });
});

describe('Material Requisition Workflow', function () {

    it('can approve material requisition', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();
        MaterialRequisitionItem::factory()->forRequisition($requisition)->count(2)->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        // Verify items have approved quantities
        $requisition->refresh();
        foreach ($requisition->items as $item) {
            expect((float) $item->quantity_approved)->toBe((float) $item->quantity_requested);
            expect((float) $item->quantity_pending)->toBe((float) $item->quantity_requested);
        }
    });

    it('cannot approve material requisition without items', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/approve");

        $response->assertUnprocessable();
    });

    it('can issue materials from approved requisition', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create stock
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $requisition = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($requisition)
            ->approved()
            ->create([
                'product_id' => $product->id,
                'quantity_requested' => 10,
                'quantity_approved' => 10,
                'quantity_pending' => 10,
            ]);

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/issue", [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 10,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'issued');

        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(10.0);
        expect((float) $item->quantity_pending)->toBe(0.0);
    });

    it('can issue materials partially', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $requisition = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($requisition)
            ->approved()
            ->create([
                'product_id' => $product->id,
                'quantity_requested' => 10,
                'quantity_approved' => 10,
                'quantity_pending' => 10,
            ]);

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/issue", [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'partial');

        $item->refresh();
        expect((float) $item->quantity_issued)->toBe(5.0);
        expect((float) $item->quantity_pending)->toBe(5.0);
    });

    it('cannot issue more than pending quantity', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $requisition = MaterialRequisition::factory()
            ->approved()
            ->withWarehouse($warehouse)
            ->create();

        $item = MaterialRequisitionItem::factory()
            ->forRequisition($requisition)
            ->approved()
            ->create([
                'product_id' => $product->id,
                'quantity_requested' => 10,
                'quantity_approved' => 10,
                'quantity_pending' => 10,
            ]);

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/issue", [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 15, // Exceeds pending
                ],
            ],
        ]);

        $response->assertStatus(409);
    });

    it('cannot issue from draft requisition', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();
        $item = MaterialRequisitionItem::factory()->forRequisition($requisition)->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/issue", [
            'items' => [
                [
                    'item_id' => $item->id,
                    'quantity' => 5,
                ],
            ],
        ]);

        $response->assertUnprocessable();
    });

    it('can cancel draft material requisition', function () {
        $requisition = MaterialRequisition::factory()->draft()->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('can cancel approved material requisition', function () {
        $requisition = MaterialRequisition::factory()->approved()->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/cancel");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel issued material requisition', function () {
        $requisition = MaterialRequisition::factory()->issued()->create();

        $response = $this->postJson("/api/v1/material-requisitions/{$requisition->id}/cancel");

        $response->assertUnprocessable();
    });
});
