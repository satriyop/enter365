<?php

use App\Enums\MrpSuggestionStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\Bom;
use App\Models\Manufacturing\BomItem;
use App\Models\Manufacturing\MrpDemand;
use App\Models\Manufacturing\MrpRun;
use App\Models\Manufacturing\MrpSuggestion;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Authenticate as admin (has all permissions)
    authenticatedAdmin();
});

describe('MRP Run CRUD', function () {

    it('can list all MRP runs', function () {
        MrpRun::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/mrp-runs');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter MRP runs by status', function () {
        MrpRun::factory()->draft()->count(2)->create();
        MrpRun::factory()->completed()->count(3)->create();

        $response = $this->getJson('/api/v1/mrp-runs?status=completed');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create an MRP run', function () {
        $response = $this->postJson('/api/v1/mrp-runs', [
            'name' => 'Weekly MRP Run',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(4)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.name', 'Weekly MRP Run');
    });

    it('can create an MRP run with warehouse filter', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->postJson('/api/v1/mrp-runs', [
            'name' => 'Warehouse Specific MRP',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(4)->toDateString(),
            'warehouse_id' => $warehouse->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse_id', $warehouse->id);
    });

    it('validates planning horizon dates', function () {
        $response = $this->postJson('/api/v1/mrp-runs', [
            'planning_horizon_start' => now()->addWeeks(4)->toDateString(),
            'planning_horizon_end' => now()->toDateString(),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('planning_horizon_end');
    });

    it('can show an MRP run with demands and suggestions', function () {
        $run = MrpRun::factory()->completed()->create();
        MrpDemand::factory()->forMrpRun($run)->count(3)->create();
        MrpSuggestion::factory()->forMrpRun($run)->count(2)->create();

        $response = $this->getJson("/api/v1/mrp-runs/{$run->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $run->id)
            ->assertJsonCount(3, 'data.demands')
            ->assertJsonCount(2, 'data.suggestions');
    });

    it('can update an MRP run in draft status', function () {
        $run = MrpRun::factory()->draft()->create();

        $response = $this->putJson("/api/v1/mrp-runs/{$run->id}", [
            'name' => 'Updated MRP Run',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(6)->toDateString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated MRP Run');
    });

    it('cannot update a completed MRP run', function () {
        $run = MrpRun::factory()->completed()->create();

        $response = $this->putJson("/api/v1/mrp-runs/{$run->id}", [
            'name' => 'Updated Name',
            'planning_horizon_start' => now()->toDateString(),
            'planning_horizon_end' => now()->addWeeks(6)->toDateString(),
        ]);

        $response->assertStatus(500);
    });

    it('can delete a draft MRP run', function () {
        $run = MrpRun::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/mrp-runs/{$run->id}");

        $response->assertOk();
        expect(MrpRun::find($run->id))->toBeNull();
    });

    it('cannot delete an applied MRP run', function () {
        $run = MrpRun::factory()->applied()->create();

        $response = $this->deleteJson("/api/v1/mrp-runs/{$run->id}");

        $response->assertStatus(500);
    });
});

describe('MRP Execution', function () {

    it('can execute an MRP run', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create([
            'procurement_type' => 'buy',
            'lead_time_days' => 7,
        ]);

        // Create work order with material requirement
        $wo = WorkOrder::factory()->confirmed()->withWarehouse($warehouse)->create([
            'planned_end_date' => now()->addWeeks(2),
        ]);
        WorkOrderItem::factory()->material()->create([
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'quantity_required' => 50,
            'quantity_consumed' => 0,
        ]);

        // No stock available
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        $run = MrpRun::factory()->draft()->withWarehouse($warehouse)->create([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addWeeks(4),
        ]);

        $response = $this->postJson("/api/v1/mrp-runs/{$run->id}/execute");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed')
            ->assertJsonPath('data.total_demands', 1)
            ->assertJsonPath('data.total_shortages', 1);
    });

    it('detects shortages and generates suggestions', function () {
        $warehouse = Warehouse::factory()->create();
        $supplier = Contact::factory()->supplier()->create();
        $product = Product::factory()->create([
            'procurement_type' => 'buy',
            'lead_time_days' => 5,
            'min_order_qty' => 10,
            'default_supplier_id' => $supplier->id,
            'purchase_price' => 100000,
        ]);

        $wo = WorkOrder::factory()->confirmed()->withWarehouse($warehouse)->create([
            'planned_end_date' => now()->addWeeks(2),
        ]);
        WorkOrderItem::factory()->material()->create([
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'quantity_required' => 100,
            'quantity_consumed' => 0,
        ]);

        // Only 20 in stock
        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
        ]);

        $run = MrpRun::factory()->draft()->withWarehouse($warehouse)->create([
            'planning_horizon_start' => now(),
            'planning_horizon_end' => now()->addWeeks(4),
        ]);

        $response = $this->postJson("/api/v1/mrp-runs/{$run->id}/execute");

        $response->assertOk()
            ->assertJsonPath('data.total_purchase_suggestions', 1);

        // Check suggestion was created correctly
        $suggestion = MrpSuggestion::where('mrp_run_id', $run->id)->first();
        expect($suggestion)->not->toBeNull();
        expect($suggestion->suggestion_type)->toBe('purchase');
        expect($suggestion->suggested_supplier_id)->toBe($supplier->id);
        expect((float) $suggestion->quantity_required)->toBe(80.0); // 100 - 20
    });

    it('can get demands for an MRP run', function () {
        $run = MrpRun::factory()->completed()->create();
        MrpDemand::factory()->forMrpRun($run)->count(5)->create();

        $response = $this->getJson("/api/v1/mrp-runs/{$run->id}/demands");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can get suggestions for an MRP run', function () {
        $run = MrpRun::factory()->completed()->create();
        MrpSuggestion::factory()->forMrpRun($run)->purchase()->count(3)->create();
        MrpSuggestion::factory()->forMrpRun($run)->workOrder()->count(2)->create();

        $response = $this->getJson("/api/v1/mrp-runs/{$run->id}/suggestions");

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter suggestions by type', function () {
        $run = MrpRun::factory()->completed()->create();
        MrpSuggestion::factory()->forMrpRun($run)->purchase()->count(3)->create();
        MrpSuggestion::factory()->forMrpRun($run)->workOrder()->count(2)->create();

        $response = $this->getJson("/api/v1/mrp-runs/{$run->id}/suggestions?type=purchase");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter suggestions by status', function () {
        $run = MrpRun::factory()->completed()->create();
        MrpSuggestion::factory()->forMrpRun($run)->pending()->count(3)->create();
        MrpSuggestion::factory()->forMrpRun($run)->accepted()->count(2)->create();

        $response = $this->getJson("/api/v1/mrp-runs/{$run->id}/suggestions?status=pending");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('MRP Suggestion Management', function () {

    it('can accept a suggestion', function () {
        $suggestion = MrpSuggestion::factory()->pending()->create();

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/accept");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'accepted');
    });

    it('can reject a suggestion', function () {
        $suggestion = MrpSuggestion::factory()->pending()->create();

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/reject", [
            'reason' => 'Tidak diperlukan saat ini',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'rejected');
    });

    it('can update suggestion quantity', function () {
        $suggestion = MrpSuggestion::factory()->pending()->create([
            'suggested_quantity' => 100,
        ]);

        $response = $this->putJson("/api/v1/mrp-suggestions/{$suggestion->id}", [
            'adjusted_quantity' => 150,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.adjusted_quantity', 150);
    });

    it('can bulk accept suggestions', function () {
        $suggestions = MrpSuggestion::factory()->pending()->count(3)->create();

        $response = $this->postJson('/api/v1/mrp-suggestions/bulk-accept', [
            'suggestion_ids' => $suggestions->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertJsonPath('accepted_count', 3);
    });

    it('can bulk reject suggestions', function () {
        $suggestions = MrpSuggestion::factory()->pending()->count(3)->create();

        $response = $this->postJson('/api/v1/mrp-suggestions/bulk-reject', [
            'suggestion_ids' => $suggestions->pluck('id')->toArray(),
            'reason' => 'Batch rejection',
        ]);

        $response->assertOk()
            ->assertJsonPath('rejected_count', 3);
    });
});

describe('MRP Suggestion Conversion', function () {

    it('can convert purchase suggestion to PO', function () {
        $supplier = Contact::factory()->supplier()->create();
        $product = Product::factory()->create([
            'purchase_price' => 100000,
        ]);
        $suggestion = MrpSuggestion::factory()
            ->purchase()
            ->accepted()
            ->forProduct($product)
            ->withSupplier($supplier)
            ->create([
                'suggested_quantity' => 50,
            ]);

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/convert-to-po");

        $response->assertOk()
            ->assertJsonStructure(['message', 'purchase_order']);

        $suggestion->refresh();
        expect($suggestion->status)->toBe(MrpSuggestionStatus::Converted);
        expect($suggestion->converted_to_type)->toBe(PurchaseOrder::class);
    });

    it('cannot convert suggestion that is not accepted', function () {
        $suggestion = MrpSuggestion::factory()->pending()->create();

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/convert-to-po");

        $response->assertUnprocessable();
    });

    it('can convert work order suggestion to WO', function () {
        $product = Product::factory()->create();
        $bom = Bom::factory()->active()->forProduct($product)->create();
        BomItem::factory()->material()->forBom($bom)->create();

        $suggestion = MrpSuggestion::factory()
            ->workOrder()
            ->accepted()
            ->forProduct($product)
            ->create([
                'suggested_quantity' => 10,
            ]);

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/convert-to-wo");

        $response->assertOk()
            ->assertJsonStructure(['message', 'work_order']);

        $suggestion->refresh();
        expect($suggestion->status)->toBe(MrpSuggestionStatus::Converted);
        expect($suggestion->converted_to_type)->toBe(WorkOrder::class);
    });

    it('can convert subcontract suggestion to SC WO', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();
        $product = Product::factory()->create();

        $suggestion = MrpSuggestion::factory()
            ->subcontract()
            ->accepted()
            ->forProduct($product)
            ->create([
                'suggested_quantity' => 5,
            ]);

        $response = $this->postJson("/api/v1/mrp-suggestions/{$suggestion->id}/convert-to-sc-wo", [
            'subcontractor_id' => $subcontractor->id,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message', 'subcontractor_work_order']);

        $suggestion->refresh();
        expect($suggestion->status)->toBe(MrpSuggestionStatus::Converted);
    });
});

describe('MRP Reports', function () {

    it('can get shortage report', function () {
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create();

        $wo = WorkOrder::factory()->confirmed()->withWarehouse($warehouse)->create([
            'planned_end_date' => now()->addWeeks(2),
        ]);
        WorkOrderItem::factory()->material()->create([
            'work_order_id' => $wo->id,
            'product_id' => $product->id,
            'quantity_required' => 100,
            'quantity_consumed' => 0,
        ]);

        ProductStock::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $warehouse->id,
            'quantity' => 20,
        ]);

        $response = $this->getJson('/api/v1/mrp/shortage-report?'.http_build_query([
            'horizon_start' => now()->toDateString(),
            'horizon_end' => now()->addWeeks(4)->toDateString(),
            'warehouse_id' => $warehouse->id,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'horizon_start',
                'horizon_end',
                'total_shortages',
                'shortages',
            ])
            ->assertJsonPath('total_shortages', 1);
    });

    it('can get MRP statistics', function () {
        MrpRun::factory()->draft()->count(2)->create();
        MrpRun::factory()->completed()->count(3)->create();
        MrpRun::factory()->applied()->count(1)->create();

        $response = $this->getJson('/api/v1/mrp/statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_runs',
                'by_status',
                'last_completed_run',
                'last_applied_run',
            ])
            ->assertJsonPath('total_runs', 6);
    });
});
