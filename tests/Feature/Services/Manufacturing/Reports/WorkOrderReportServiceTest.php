<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Inventory\Product;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Manufacturing\WorkOrderItem;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Manufacturing\Reports\WorkOrderReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = new WorkOrderReportService;
});

describe('WorkOrderReportService cost summary', function () {
    it('generates work order cost summary', function () {
        $project = Project::factory()->create();
        $product = Product::factory()->create();

        WorkOrder::factory()->create([
            'project_id' => $project->id,
            'product_id' => $product->id,
            'status' => DocumentStatus::Completed,
            'quantity_ordered' => 100,
            'quantity_completed' => 100,
            'estimated_material_cost' => 5000000,
            'estimated_labor_cost' => 3000000,
            'estimated_overhead_cost' => 2000000,
            'estimated_total_cost' => 10000000,
            'actual_material_cost' => 4800000,
            'actual_labor_cost' => 2900000,
            'actual_overhead_cost' => 1900000,
            'actual_total_cost' => 9600000,
        ]);

        $report = $this->service->getWorkOrderCostSummary();

        expect($report)->toHaveKeys(['report_name', 'period', 'work_orders', 'totals']);
        expect($report['work_orders'])->toHaveCount(1);
        expect($report['totals']['total_estimated'])->toBe(10000000);
        expect($report['totals']['total_actual'])->toBe(9600000);
        expect($report['totals']['total_variance'])->toBe(400000);
    });

    it('filters by date range', function () {
        WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'planned_start_date' => now()->subDays(10),
        ]);

        WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'planned_start_date' => now()->subDays(60),
        ]);

        $report = $this->service->getWorkOrderCostSummary(
            now()->subDays(30)->toDateString(),
            now()->toDateString()
        );

        expect($report['work_orders'])->toHaveCount(1);
    });

    it('filters by status', function () {
        WorkOrder::factory()->create(['status' => DocumentStatus::Completed]);
        WorkOrder::factory()->create(['status' => DocumentStatus::InProgress]);

        $report = $this->service->getWorkOrderCostSummary(
            status: DocumentStatus::Completed->value
        );

        expect($report['work_orders'])->toHaveCount(1);
    });

    it('handles empty results', function () {
        $report = $this->service->getWorkOrderCostSummary();

        expect($report['work_orders'])->toHaveCount(0);
        expect($report['totals']['total_estimated'])->toBe(0);
    });
});

describe('WorkOrderReportService cost detail', function () {
    it('generates detailed cost breakdown', function () {
        $product = Product::factory()->create();
        $workOrder = WorkOrder::factory()->create([
            'product_id' => $product->id,
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 10000000,
            'actual_total_cost' => 9500000,
        ]);

        WorkOrderItem::factory()->count(2)->create([
            'work_order_id' => $workOrder->id,
        ]);

        $report = $this->service->getWorkOrderCostDetail($workOrder);

        expect($report)->toHaveKeys(['report_name', 'work_order', 'cost_summary', 'item_breakdown', 'timeline']);
        expect($report['work_order']['wo_number'])->toBe($workOrder->wo_number);
        expect($report['item_breakdown'])->toHaveCount(2);
    });
});

describe('WorkOrderReportService variance report', function () {
    it('categorizes work orders by variance', function () {
        // Over budget (-10%)
        $wo1 = WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 10000000,
            'actual_total_cost' => 11000000,
        ]);
        $wo1->update(['cost_variance' => $wo1->estimated_total_cost - $wo1->actual_total_cost]);

        // Under budget (+10%)
        $wo2 = WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 10000000,
            'actual_total_cost' => 9000000,
        ]);
        $wo2->update(['cost_variance' => $wo2->estimated_total_cost - $wo2->actual_total_cost]);

        // On budget (within 5%)
        $wo3 = WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 10000000,
            'actual_total_cost' => 10200000,
        ]);
        $wo3->update(['cost_variance' => $wo3->estimated_total_cost - $wo3->actual_total_cost]);

        $report = $this->service->getCostVarianceReport();

        expect($report)->toHaveKeys(['report_name', 'period', 'over_budget', 'under_budget', 'on_budget', 'summary']);
        expect($report['over_budget'])->toHaveCount(1);
        expect($report['under_budget'])->toHaveCount(1);
        expect($report['on_budget'])->toHaveCount(1);
    });

    it('calculates summary statistics', function () {
        WorkOrder::factory()->create([
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 10000000,
            'actual_total_cost' => 11000000,
        ]);

        $report = $this->service->getCostVarianceReport();

        expect($report['summary'])->toHaveKeys([
            'total_work_orders',
            'over_budget_count',
            'under_budget_count',
            'on_budget_count',
            'total_estimated',
            'total_actual',
            'total_variance',
            'overall_variance_percent',
        ]);
    });
});
