<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Project;
use App\Models\Accounting\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Work Order Cost Summary Report', function () {

    it('can get work order cost summary', function () {
        WorkOrder::factory()
            ->withEstimatedCosts(1000000, 500000, 200000)
            ->withActualCosts(1100000, 550000, 220000)
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/reports/work-order-costs');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'work_orders',
                'totals' => [
                    'work_orders_count',
                    'total_estimated',
                    'total_actual',
                    'total_variance',
                    'variance_percent',
                    'completed_count',
                    'in_progress_count',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Biaya Work Order')
            ->assertJsonPath('totals.work_orders_count', 3);
    });

    it('can filter by status', function () {
        WorkOrder::factory()->inProgress()->count(2)->create();
        WorkOrder::factory()->completed()->count(1)->create();
        WorkOrder::factory()->draft()->count(1)->create();

        $response = $this->getJson('/api/v1/reports/work-order-costs?status=in_progress');

        $response->assertOk()
            ->assertJsonPath('totals.work_orders_count', 2);
    });

    it('can filter by project', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        WorkOrder::factory()->forProject($project)->count(2)->create();
        WorkOrder::factory()->count(1)->create(); // Not associated with project

        $response = $this->getJson("/api/v1/reports/work-order-costs?project_id={$project->id}");

        $response->assertOk()
            ->assertJsonPath('totals.work_orders_count', 2);
    });

    it('can filter by date range', function () {
        // Work orders in 2024
        WorkOrder::factory()->create([
            'planned_start_date' => '2024-03-15',
        ]);
        WorkOrder::factory()->create([
            'planned_start_date' => '2024-06-15',
        ]);

        // Work order in 2023
        WorkOrder::factory()->create([
            'planned_start_date' => '2023-06-15',
        ]);

        $response = $this->getJson('/api/v1/reports/work-order-costs?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('totals.work_orders_count', 2)
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');
    });

    it('includes estimated and actual costs breakdown', function () {
        WorkOrder::factory()
            ->withEstimatedCosts(2000000, 1000000, 500000)
            ->withActualCosts(2200000, 1100000, 550000)
            ->create();

        $response = $this->getJson('/api/v1/reports/work-order-costs');

        $response->assertOk();

        $workOrders = $response->json('work_orders');
        expect($workOrders)->toHaveCount(1);

        $wo = $workOrders[0];
        expect($wo['estimated_costs']['material'])->toBe(2000000);
        expect($wo['estimated_costs']['labor'])->toBe(1000000);
        expect($wo['estimated_costs']['overhead'])->toBe(500000);
        expect($wo['estimated_costs']['total'])->toBe(3500000);

        expect($wo['actual_costs']['material'])->toBe(2200000);
        expect($wo['actual_costs']['labor'])->toBe(1100000);
        expect($wo['actual_costs']['overhead'])->toBe(550000);
        expect($wo['actual_costs']['total'])->toBe(3850000);
    });

    it('calculates variance correctly', function () {
        WorkOrder::factory()
            ->withEstimatedCosts(1000000, 500000, 200000)
            ->withActualCosts(1200000, 600000, 250000)
            ->create();

        $response = $this->getJson('/api/v1/reports/work-order-costs');

        $response->assertOk();

        $workOrders = $response->json('work_orders');
        $wo = $workOrders[0];

        // Variance = estimated - actual (negative means over budget)
        expect($wo['variance']['material'])->toBe(-200000);
        expect($wo['variance']['labor'])->toBe(-100000);
        expect($wo['variance']['overhead'])->toBe(-50000);
    });

});

describe('Work Order Cost Detail Report', function () {

    it('can get work order cost detail', function () {
        $workOrder = WorkOrder::factory()
            ->withEstimatedCosts(1000000, 500000, 200000)
            ->withActualCosts(1100000, 550000, 220000)
            ->create();

        $response = $this->getJson("/api/v1/reports/work-orders/{$workOrder->id}/costs");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'work_order' => [
                    'id',
                    'wo_number',
                    'name',
                    'type',
                    'status',
                    'project',
                    'product',
                    'quantity_ordered',
                    'quantity_completed',
                    'quantity_scrapped',
                    'completion_percentage',
                ],
                'cost_summary' => [
                    'estimated',
                    'actual',
                    'variance',
                    'cost_per_unit',
                ],
                'item_breakdown',
                'timeline',
            ])
            ->assertJsonPath('report_name', 'Laporan Detail Biaya Work Order')
            ->assertJsonPath('work_order.id', $workOrder->id);
    });

    it('includes project information when available', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create([
            'project_number' => 'PRJ-TEST-001',
            'name' => 'Test Project',
        ]);

        $workOrder = WorkOrder::factory()->forProject($project)->create();

        $response = $this->getJson("/api/v1/reports/work-orders/{$workOrder->id}/costs");

        $response->assertOk()
            ->assertJsonPath('work_order.project.project_number', 'PRJ-TEST-001')
            ->assertJsonPath('work_order.project.name', 'Test Project');
    });

    it('includes timeline information', function () {
        $workOrder = WorkOrder::factory()->completed()->create([
            'planned_start_date' => '2024-01-15',
            'planned_end_date' => '2024-02-15',
        ]);

        $response = $this->getJson("/api/v1/reports/work-orders/{$workOrder->id}/costs");

        $response->assertOk()
            ->assertJsonPath('timeline.planned_start', '2024-01-15')
            ->assertJsonPath('timeline.planned_end', '2024-02-15');
    });

    it('calculates cost per unit for completed work orders', function () {
        $workOrder = WorkOrder::factory()
            ->completed()
            ->withActualCosts(3000000, 1500000, 500000)
            ->create([
                'quantity_ordered' => 5,
                'quantity_completed' => 5,
            ]);

        $response = $this->getJson("/api/v1/reports/work-orders/{$workOrder->id}/costs");

        $response->assertOk();

        // Total actual = 5,000,000 / 5 units = 1,000,000 per unit
        $costPerUnit = $response->json('cost_summary.cost_per_unit');
        expect($costPerUnit)->toBe(1000000);
    });

});

describe('Cost Variance Report', function () {

    it('can get cost variance report', function () {
        // Over budget work order
        WorkOrder::factory()
            ->inProgress()
            ->withEstimatedCosts(1000000, 500000, 200000)
            ->withActualCosts(1500000, 700000, 300000)
            ->create();

        // Under budget work order
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(2000000, 1000000, 500000)
            ->withActualCosts(1500000, 800000, 400000)
            ->create();

        $response = $this->getJson('/api/v1/reports/cost-variance');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'over_budget',
                'under_budget',
                'on_budget',
                'summary' => [
                    'total_work_orders',
                    'over_budget_count',
                    'under_budget_count',
                    'on_budget_count',
                    'total_estimated',
                    'total_actual',
                    'total_variance',
                    'overall_variance_percent',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Variansi Biaya');
    });

    it('categorizes work orders by variance threshold', function () {
        // Over budget (>5% over)
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->withActualCosts(1200000, 0, 0)
            ->create();

        // Under budget (>5% under)
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->withActualCosts(800000, 0, 0)
            ->create();

        // On budget (within 5%)
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->withActualCosts(1020000, 0, 0)
            ->create();

        $response = $this->getJson('/api/v1/reports/cost-variance');

        $response->assertOk()
            ->assertJsonPath('summary.over_budget_count', 1)
            ->assertJsonPath('summary.under_budget_count', 1)
            ->assertJsonPath('summary.on_budget_count', 1);
    });

    it('can filter by date range', function () {
        // Work order in 2024
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create([
                'planned_start_date' => '2024-06-15',
            ]);

        // Work order in 2023
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create([
                'planned_start_date' => '2023-06-15',
            ]);

        $response = $this->getJson('/api/v1/reports/cost-variance?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('summary.total_work_orders', 1)
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');
    });

    it('only includes in progress and completed work orders', function () {
        WorkOrder::factory()
            ->completed()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create();

        WorkOrder::factory()
            ->inProgress()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create();

        WorkOrder::factory()
            ->draft()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create();

        WorkOrder::factory()
            ->cancelled()
            ->withEstimatedCosts(1000000, 0, 0)
            ->create();

        $response = $this->getJson('/api/v1/reports/cost-variance');

        $response->assertOk()
            ->assertJsonPath('summary.total_work_orders', 2);
    });

});
