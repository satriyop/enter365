<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\WorkOrderServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\AccountingPolicy;
use App\Models\Accounting\JournalEntry;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductStock;
use App\Models\Inventory\Warehouse;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\User;
use App\Services\Manufacturing\WorkOrderMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Once;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->warehouse = Warehouse::factory()->create();
    $this->product = Product::factory()->create([
        'name' => 'Steel Plate',
        'unit' => 'pcs',
        'purchase_price' => 50000,
        'track_inventory' => true,
    ]);

    $this->workOrderService = app(WorkOrderServiceInterface::class);
    $this->materialService = app(WorkOrderMaterialService::class);
});

function makeInProgressWorkOrderWithMaterial(
    Warehouse $warehouse,
    Product $product,
    int $quantityRequired = 10,
    int $unitCost = 50000,
): WorkOrder {
    $wo = WorkOrder::factory()->create([
        'status' => DocumentStatus::InProgress,
        'warehouse_id' => $warehouse->id,
        'type' => WorkOrder::TYPE_PRODUCTION,
        'quantity_ordered' => 5,
    ]);

    WorkOrderItem::factory()->create([
        'work_order_id' => $wo->id,
        'type' => WorkOrderItem::TYPE_MATERIAL,
        'product_id' => $product->id,
        'description' => $product->name,
        'quantity_required' => $quantityRequired,
        'quantity_reserved' => $quantityRequired,
        'quantity_consumed' => 0,
        'unit' => $product->unit,
        'unit_cost' => $unitCost,
    ]);

    ProductStock::factory()->create([
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'reserved_quantity' => $quantityRequired,
        'average_cost' => $unitCost,
    ]);

    return $wo->fresh(['items']);
}

describe('project_based (default)', function () {
    test('complete creates material consumptions but no manufacturing journal entries', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product);

        $completed = $this->workOrderService->complete($wo, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Completed);

        $consumptions = MaterialConsumption::where('work_order_id', $completed->id)->get();
        expect($consumptions)->toHaveCount(1)
            ->and((float) $consumptions->first()->quantity_consumed)->toEqual(10.0)
            ->and((int) $consumptions->first()->total_cost)->toBe(500000);

        // Default project_based strategy is a no-JE stub
        expect(JournalEntry::where('source_type', MaterialConsumption::class)->count())->toBe(0)
            ->and(JournalEntry::where('source_type', WorkOrder::class)
                ->where('source_id', $completed->id)
                ->count())->toBe(0);
    });

    test('recordConsumption does not create journal entries under project_based', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product);
        $item = $wo->items->first();

        $this->materialService->recordConsumption($wo, [
            [
                'work_order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'quantity_consumed' => 4,
                'unit_cost' => 50000,
            ],
        ]);

        expect(MaterialConsumption::where('work_order_id', $wo->id)->count())->toBe(1)
            ->and(JournalEntry::where('source_type', MaterialConsumption::class)->count())->toBe(0);
    });
});

describe('job_costing', function () {
    beforeEach(function () {
        AccountingPolicy::query()->update(['manufacturing_costing' => 'job_costing']);
        Once::flush();
    });

    test('complete posts Inventory→WIP on consume and WIP→FG on complete', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product, 10, 50000);

        $completed = $this->workOrderService->complete($wo, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Completed);

        $consumption = MaterialConsumption::where('work_order_id', $completed->id)->first();
        expect($consumption)->not->toBeNull()
            ->and((int) $consumption->total_cost)->toBe(500000);

        $consumptionJe = JournalEntry::where('source_type', MaterialConsumption::class)
            ->where('source_id', $consumption->id)
            ->first();

        expect($consumptionJe)->not->toBeNull()
            ->and($consumptionJe->description)->toContain('Job Costing')
            ->and((int) $consumptionJe->lines()->sum('debit'))->toBe(500000)
            ->and((int) $consumptionJe->lines()->sum('credit'))->toBe(500000);

        $completeJe = JournalEntry::where('source_type', WorkOrder::class)
            ->where('source_id', $completed->id)
            ->first();

        expect($completeJe)->not->toBeNull()
            ->and($completeJe->description)->toContain('Job Costing')
            ->and((int) $completeJe->lines()->sum('debit'))->toBe(500000)
            ->and((int) $completeJe->lines()->sum('credit'))->toBe(500000);
    });

    test('recordConsumption creates Inventory→WIP journal entry', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product);
        $item = $wo->items->first();

        $this->materialService->recordConsumption($wo, [
            [
                'work_order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'quantity_consumed' => 3,
                'unit_cost' => 50000,
            ],
        ]);

        $consumption = MaterialConsumption::where('work_order_id', $wo->id)->first();
        $je = JournalEntry::where('source_type', MaterialConsumption::class)
            ->where('source_id', $consumption->id)
            ->first();

        expect($je)->not->toBeNull()
            ->and((int) $je->lines()->sum('debit'))->toBe(150000);
    });

    test('complete does not double-count prior progressive consumptions', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product, 10, 50000);
        $item = $wo->items->first();

        // Progressive consumption of 4 units during production
        $this->materialService->recordConsumption($wo, [
            [
                'work_order_item_id' => $item->id,
                'product_id' => $this->product->id,
                'quantity_consumed' => 4,
                'unit_cost' => 50000,
            ],
        ]);

        $completed = $this->workOrderService->complete($wo->fresh(['items']), $this->user->id);

        // 1 progressive + 1 remaining (6) at complete
        $consumptions = MaterialConsumption::where('work_order_id', $completed->id)->get();
        expect($consumptions)->toHaveCount(2)
            ->and((int) $consumptions->sum('total_cost'))->toBe(500000);

        // 2 consumption JEs + 1 completion JE
        expect(JournalEntry::where('source_type', MaterialConsumption::class)->count())->toBe(2);

        $completeJe = JournalEntry::where('source_type', WorkOrder::class)
            ->where('source_id', $completed->id)
            ->first();

        expect($completeJe)->not->toBeNull()
            ->and((int) $completeJe->lines()->sum('debit'))->toBe(500000);
    });
});

describe('wip_accounting', function () {
    beforeEach(function () {
        AccountingPolicy::query()->update(['manufacturing_costing' => 'wip_accounting']);
        Once::flush();
    });

    test('complete creates posted WIP journal entries', function () {
        $wo = makeInProgressWorkOrderWithMaterial($this->warehouse, $this->product, 5, 20000);

        $completed = $this->workOrderService->complete($wo, $this->user->id);

        $consumption = MaterialConsumption::where('work_order_id', $completed->id)->first();
        expect($consumption)->not->toBeNull()
            ->and((int) $consumption->total_cost)->toBe(100000);

        $consumptionJe = JournalEntry::where('source_type', MaterialConsumption::class)
            ->where('source_id', $consumption->id)
            ->first();

        expect($consumptionJe)->not->toBeNull()
            ->and($consumptionJe->is_posted)->toBeTrue();

        $completeJe = JournalEntry::where('source_type', WorkOrder::class)
            ->where('source_id', $completed->id)
            ->first();

        expect($completeJe)->not->toBeNull()
            ->and($completeJe->is_posted)->toBeTrue()
            ->and((int) $completeJe->lines()->sum('debit'))->toBe(100000)
            ->and((int) $completeJe->lines()->sum('credit'))->toBe(100000);
    });
});
