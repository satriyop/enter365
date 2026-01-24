<?php

use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Work Order CRUD', function () {

    it('can list all work orders', function () {
        WorkOrder::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/work-orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter work orders by status', function () {
        WorkOrder::factory()->draft()->count(2)->create();
        WorkOrder::factory()->confirmed()->count(3)->create();

        $response = $this->getJson('/api/v1/work-orders?status=confirmed');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter work orders by type', function () {
        WorkOrder::factory()->production()->count(2)->create();
        WorkOrder::factory()->installation()->count(3)->create();

        $response = $this->getJson('/api/v1/work-orders?type=installation');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter work orders by priority', function () {
        WorkOrder::factory()->create(['priority' => WorkOrder::PRIORITY_NORMAL]);
        WorkOrder::factory()->highPriority()->count(2)->create();
        WorkOrder::factory()->urgent()->count(1)->create();

        $response = $this->getJson('/api/v1/work-orders?priority=high');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter work orders by project', function () {
        $project = Project::factory()->create();
        WorkOrder::factory()->forProject($project)->count(2)->create();
        WorkOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/work-orders?project_id={$project->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter work orders by warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        WorkOrder::factory()->withWarehouse($warehouse)->count(2)->create();
        WorkOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/work-orders?warehouse_id={$warehouse->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter parent only work orders', function () {
        $parent = WorkOrder::factory()->create();
        WorkOrder::factory()->subWorkOrder($parent)->count(2)->create();
        WorkOrder::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/work-orders?parent_only=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can search work orders', function () {
        WorkOrder::factory()->create(['name' => 'Panel Listrik Assembly']);
        WorkOrder::factory()->create(['name' => 'Solar Panel Installation']);
        WorkOrder::factory()->create(['wo_number' => 'WO-PANEL-001']);

        $response = $this->getJson('/api/v1/work-orders?search=panel');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a work order', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/work-orders', [
            'name' => 'Assembly Panel MDP',
            'description' => 'Assembly panel utama 100A',
            'type' => 'production',
            'product_id' => $product->id,
            'quantity_ordered' => 5,
            'priority' => 'high',
            'planned_start_date' => '2025-01-01',
            'planned_end_date' => '2025-01-15',
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.name', 'Assembly Panel MDP')
            ->assertJsonPath('data.type', 'production')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.quantity_ordered', 5);
    });

    it('can create a work order with items', function () {
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/work-orders', [
            'name' => 'Assembly Panel MDP',
            'items' => [
                [
                    'type' => 'material',
                    'product_id' => $product->id,
                    'description' => 'Material A',
                    'quantity' => 10,
                    'unit' => 'pcs',
                    'unit_cost' => 50000,
                ],
                [
                    'type' => 'labor',
                    'description' => 'Assembly Labor',
                    'quantity' => 8,
                    'unit' => 'jam',
                    'unit_cost' => 75000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.items');

        $workOrder = WorkOrder::find($response->json('data.id'));
        expect($workOrder->items)->toHaveCount(2);
    });

    it('validates required fields when creating work order', function () {
        $response = $this->postJson('/api/v1/work-orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates date range when creating work order', function () {
        $response = $this->postJson('/api/v1/work-orders', [
            'name' => 'Test WO',
            'planned_start_date' => '2025-06-01',
            'planned_end_date' => '2025-01-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['planned_end_date']);
    });

    it('can show a single work order with items', function () {
        $workOrder = WorkOrder::factory()->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->count(3)->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->labor()->count(2)->create();

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $workOrder->id)
            ->assertJsonCount(5, 'data.items');
    });

    it('can update a draft work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->putJson("/api/v1/work-orders/{$workOrder->id}", [
            'name' => 'Updated Work Order Name',
            'priority' => 'urgent',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Work Order Name')
            ->assertJsonPath('data.priority', 'urgent')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update confirmed work order', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();

        $response = $this->putJson("/api/v1/work-orders/{$workOrder->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertOk();
        $this->assertSoftDeleted('work_orders', ['id' => $workOrder->id]);
    });

    it('cannot delete non-draft work order', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();

        $response = $this->deleteJson("/api/v1/work-orders/{$workOrder->id}");

        $response->assertUnprocessable();
    });
});

describe('Work Order from Project', function () {

    it('can create work order for a project', function () {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/work-orders", [
            'name' => 'Project Work Order',
            'type' => 'installation',
            'quantity_ordered' => 1,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.type', 'installation');

        // Verify WO number format
        $woNumber = $response->json('data.wo_number');
        expect($woNumber)->toContain($project->project_number.'-WO-');
    });
});

describe('Work Order from BOM', function () {

    it('can create work order from active BOM', function () {
        $product = Product::factory()->create();
        $bom = Bom::factory()->active()->create(['product_id' => $product->id]);
        BomItem::factory()->forBom($bom)->material()->count(3)->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/create-work-order", [
            'quantity' => 10,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.bom_id', $bom->id)
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.quantity_ordered', 10)
            ->assertJsonCount(3, 'data.items');
    });

    it('cannot create work order from inactive BOM', function () {
        $bom = Bom::factory()->draft()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/create-work-order", [
            'quantity' => 5,
        ]);

        $response->assertUnprocessable();
    });

    it('validates quantity is required when creating from BOM', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson("/api/v1/boms/{$bom->id}/create-work-order", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    });
});

describe('Sub-Work Orders', function () {

    it('can list sub-work orders', function () {
        $parent = WorkOrder::factory()->create();
        WorkOrder::factory()->subWorkOrder($parent)->count(3)->create();

        $response = $this->getJson("/api/v1/work-orders/{$parent->id}/sub-work-orders");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create sub-work order', function () {
        $parent = WorkOrder::factory()->create();

        $response = $this->postJson("/api/v1/work-orders/{$parent->id}/sub-work-orders", [
            'name' => 'Sub Assembly',
            'type' => 'production',
            'quantity_ordered' => 2,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_work_order_id', $parent->id)
            ->assertJsonPath('data.project_id', $parent->project_id);
    });
});

describe('Work Order Workflow', function () {

    it('can confirm work order with items and reserve materials', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create stock
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 0,
        ]);

        $workOrder = WorkOrder::factory()->draft()->withWarehouse($warehouse)->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/confirm");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'confirmed');

        // Check stock reservation
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->reserved_quantity)->toBe(10);
    });

    it('cannot confirm work order without items', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/confirm");

        $response->assertUnprocessable();
    });

    it('cannot confirm work order with insufficient stock', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create stock with insufficient quantity
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reserved_quantity' => 0,
        ]);

        $workOrder = WorkOrder::factory()->draft()->withWarehouse($warehouse)->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
        ]);

                $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/confirm");
         
                $response->assertStatus(409);
                expect($response->json('message'))->toContain('Stok tidak cukup');
            });
    it('can start confirmed work order', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');

        $workOrder->refresh();
        expect($workOrder->actual_start_date)->not->toBeNull();
    });

    it('cannot start draft work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/start");

        $response->assertUnprocessable();
    });

    it('can complete in-progress work order and consume materials', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create stock (already reserved)
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 10,
        ]);

        $workOrder = WorkOrder::factory()->inProgress()->withWarehouse($warehouse)->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 10,
            'unit_cost' => 50000,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        // Check stock was consumed
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->quantity)->toBe(90);
        expect($stock->reserved_quantity)->toBe(0);

        $workOrder->refresh();
        expect($workOrder->actual_end_date)->not->toBeNull();
        expect((float) $workOrder->quantity_completed)->toBe((float) $workOrder->quantity_ordered);
    });

    it('cannot complete confirmed work order (must be in progress)', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/complete");

        $response->assertUnprocessable();
    });

    it('cannot complete work order with incomplete sub-work orders', function () {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrder::factory()->subWorkOrder($workOrder)->confirmed()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/complete");

        $response->assertUnprocessable();
    });

    it('can cancel draft work order', function () {
        $workOrder = WorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/cancel", [
            'reason' => 'Pesanan dibatalkan klien',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Pesanan dibatalkan klien');
    });

    it('can cancel confirmed work order and release reservations', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        // Create stock with reservation
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 100,
            'reserved_quantity' => 10,
        ]);

        $workOrder = WorkOrder::factory()->confirmed()->withWarehouse($warehouse)->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 10,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/cancel", [
            'reason' => 'Material tidak tersedia',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');

        // Check reservation was released
        $stock = ProductStock::where('product_id', $product->id)
            ->where('warehouse_id', $warehouse->id)
            ->first();
        expect($stock->reserved_quantity)->toBe(0);
    });

    it('cannot cancel completed work order', function () {
        $workOrder = WorkOrder::factory()->completed()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Work Order Output', function () {

    it('can record output quantity', function () {
        $workOrder = WorkOrder::factory()->inProgress()->create([
            'quantity_ordered' => 10,
            'quantity_completed' => 0,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/record-output", [
            'quantity' => 3,
            'scrapped' => 1,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.quantity_completed', 3)
            ->assertJsonPath('data.quantity_scrapped', 1);
    });

    it('cannot record output for non-in-progress work order', function () {
        $workOrder = WorkOrder::factory()->confirmed()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/record-output", [
            'quantity' => 5,
        ]);

        $response->assertUnprocessable();
    });

    it('validates output quantity is required', function () {
        $workOrder = WorkOrder::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/record-output", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity']);
    });
});

describe('Work Order Material Consumption', function () {

    it('can record material consumption', function () {
        $product = Product::factory()->create(['purchase_price' => 50000]);
        $workOrder = WorkOrder::factory()->inProgress()->create();
        $item = WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/record-consumption", [
            'consumptions' => [
                [
                    'product_id' => $product->id,
                    'work_order_item_id' => $item->id,
                    'quantity_consumed' => 5,
                    'quantity_scrapped' => 1,
                    'scrap_reason' => 'Rusak saat assembly',
                ],
            ],
        ]);

        $response->assertOk();

        // Verify consumption was recorded
        $workOrder->refresh();
        expect($workOrder->consumptions)->toHaveCount(1);

        $item->refresh();
        expect((float) $item->quantity_consumed)->toBe(6.0); // 5 + 1 scrapped
    });

    it('validates consumption data', function () {
        $workOrder = WorkOrder::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/record-consumption", [
            'consumptions' => [
                [
                    // Missing required fields
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['consumptions.0.product_id', 'consumptions.0.quantity_consumed']);
    });
});

describe('Work Order Cost Summary', function () {

    it('returns cost summary', function () {
        $workOrder = WorkOrder::factory()
            ->withEstimatedCosts(1000000, 500000, 200000)
            ->withActualCosts(1100000, 550000, 220000)
            ->create();

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}/cost-summary");

        $response->assertOk()
            ->assertJsonPath('data.work_order_id', $workOrder->id)
            ->assertJsonPath('data.estimated.material', 1000000)
            ->assertJsonPath('data.estimated.labor', 500000)
            ->assertJsonPath('data.estimated.overhead', 200000)
            ->assertJsonPath('data.estimated.total', 1700000)
            ->assertJsonPath('data.actual.total', 1870000)
            ->assertJsonPath('data.variance.total', -170000);
    });
});

describe('Work Order Material Status', function () {

    it('returns material status', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderItem::factory()->forWorkOrder($workOrder)->material()->create([
            'product_id' => $product->id,
            'quantity_required' => 10,
            'quantity_reserved' => 10,
            'quantity_consumed' => 5,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}/material-status");

        $response->assertOk()
            ->assertJsonPath('data.work_order_id', $workOrder->id)
            ->assertJsonCount(1, 'data.materials')
            ->assertJsonPath('data.materials.0.status', 'partial');

        // Check quantities as floats
        $materials = $response->json('data.materials.0');
        expect((float) $materials['quantity_required'])->toBe(10.0);
        expect((float) $materials['quantity_consumed'])->toBe(5.0);
    });
});

describe('Work Order Statistics', function () {

    it('returns work order statistics', function () {
        WorkOrder::factory()->draft()->count(2)->create();
        WorkOrder::factory()->confirmed()->count(1)->create();
        WorkOrder::factory()->inProgress()->count(3)->create();
        WorkOrder::factory()->completed()->count(2)->create();

        $response = $this->getJson('/api/v1/work-orders-statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_count', 8)
            ->assertJsonPath('data.by_status.draft', 2)
            ->assertJsonPath('data.by_status.confirmed', 1)
            ->assertJsonPath('data.by_status.in_progress', 3)
            ->assertJsonPath('data.by_status.completed', 2);
    });

    it('returns statistics by type', function () {
        WorkOrder::factory()->production()->count(3)->create();
        WorkOrder::factory()->installation()->count(2)->create();

        $response = $this->getJson('/api/v1/work-orders-statistics');

        $response->assertOk()
            ->assertJsonPath('data.by_type.production', 3)
            ->assertJsonPath('data.by_type.installation', 2);
    });
});
