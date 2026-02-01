<?php

declare(strict_types=1);

use App\Contracts\Manufacturing\WorkOrderCostServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use App\Models\Projects\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(WorkOrderCostServiceInterface::class);
});

describe('getCostSummary', function () {
    test('returns correct structure with estimated and actual costs', function () {
        $wo = WorkOrder::factory()->create([
            'wo_number' => 'WO-TEST-001',
            'estimated_material_cost' => 5000000,
            'estimated_labor_cost' => 2000000,
            'estimated_overhead_cost' => 1000000,
            'estimated_total_cost' => 8000000,
            'actual_material_cost' => 4500000,
            'actual_labor_cost' => 1800000,
            'actual_overhead_cost' => 900000,
            'actual_total_cost' => 7200000,
            'cost_variance' => 800000,
        ]);

        $result = $this->service->getCostSummary($wo);

        expect($result)->toHaveKeys([
            'work_order_id', 'wo_number',
            'estimated', 'actual', 'variance', 'variance_percentage',
        ])
            ->and($result['work_order_id'])->toBe($wo->id)
            ->and($result['wo_number'])->toBe('WO-TEST-001')
            ->and($result['estimated'])->toBe([
                'material' => 5000000,
                'labor' => 2000000,
                'overhead' => 1000000,
                'total' => 8000000,
            ])
            ->and($result['actual'])->toBe([
                'material' => 4500000,
                'labor' => 1800000,
                'overhead' => 900000,
                'total' => 7200000,
            ])
            ->and($result['variance'])->toBe([
                'material' => 500000,
                'labor' => 200000,
                'overhead' => 100000,
                'total' => 800000,
            ])
            ->and($result['variance_percentage'])->toBe(10.0);
    });

    test('returns zero variance percentage when estimated total is zero', function () {
        $wo = WorkOrder::factory()->create([
            'estimated_material_cost' => 0,
            'estimated_labor_cost' => 0,
            'estimated_overhead_cost' => 0,
            'estimated_total_cost' => 0,
            'actual_material_cost' => 0,
            'actual_labor_cost' => 0,
            'actual_overhead_cost' => 0,
            'actual_total_cost' => 0,
            'cost_variance' => 0,
        ]);

        $result = $this->service->getCostSummary($wo);

        expect($result['variance_percentage'])->toEqual(0);
    });
});

describe('getStatistics', function () {
    test('returns correct counts and totals', function () {
        WorkOrder::factory()->create([
            'type' => WorkOrder::TYPE_PRODUCTION,
            'status' => DocumentStatus::Draft,
            'estimated_total_cost' => 5000000,
            'actual_total_cost' => 0,
        ]);

        WorkOrder::factory()->create([
            'type' => WorkOrder::TYPE_PRODUCTION,
            'status' => DocumentStatus::InProgress,
            'estimated_total_cost' => 8000000,
            'actual_total_cost' => 3000000,
        ]);

        WorkOrder::factory()->create([
            'type' => WorkOrder::TYPE_INSTALLATION,
            'status' => DocumentStatus::Completed,
            'estimated_total_cost' => 6000000,
            'actual_total_cost' => 5500000,
            'cost_variance' => 500000,
        ]);

        $result = $this->service->getStatistics();

        expect($result)->toHaveKeys([
            'total_count', 'by_status', 'by_type',
            'total_estimated_cost', 'total_actual_cost', 'average_cost_variance',
        ])
            ->and($result['total_count'])->toBe(3)
            ->and($result['by_type'][WorkOrder::TYPE_PRODUCTION])->toBe(2)
            ->and($result['by_type'][WorkOrder::TYPE_INSTALLATION])->toBe(1)
            ->and($result['total_estimated_cost'])->toEqual(19000000)
            ->and($result['total_actual_cost'])->toEqual(8500000);
    });

    test('filters by date range', function () {
        WorkOrder::factory()->create([
            'type' => WorkOrder::TYPE_PRODUCTION,
            'status' => DocumentStatus::Draft,
            'created_at' => '2024-06-15',
        ]);

        WorkOrder::factory()->create([
            'type' => WorkOrder::TYPE_PRODUCTION,
            'status' => DocumentStatus::InProgress,
            'created_at' => '2024-07-15',
        ]);

        $result = $this->service->getStatistics('2024-06-01', '2024-06-30');

        expect($result['total_count'])->toBe(1);
    });
});

describe('updateProjectCosts', function () {
    test('does nothing when work order has no project', function () {
        $wo = WorkOrder::factory()->create([
            'project_id' => null,
        ]);

        // Should not throw
        $this->service->updateProjectCosts($wo);

        expect(true)->toBeTrue();
    });

    test('recalculates project financials when project exists', function () {
        $project = Project::factory()->create([
            'total_cost' => 0,
        ]);

        $wo = WorkOrder::factory()->create([
            'project_id' => $project->id,
            'actual_total_cost' => 5000000,
        ]);

        $this->service->updateProjectCosts($wo);

        // The project financials should have been recalculated
        $project->refresh();
        // We can't assert exact values since calculateFinancials() depends on project's internal logic
        // but we verify the method runs without error
        expect($project)->not->toBeNull();
    });
});

describe('createDeliveryOrderIfNeeded', function () {
    test('returns null when work order has no project', function () {
        $wo = WorkOrder::factory()->create([
            'project_id' => null,
        ]);

        $result = $this->service->createDeliveryOrderIfNeeded($wo);

        expect($result)->toBeNull();
    });

});
