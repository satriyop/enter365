<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Project;
use App\Models\Accounting\ProjectCost;
use App\Models\Accounting\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Project Profitability Report', function () {

    it('can get project profitability summary', function () {
        $customer = Contact::factory()->customer()->create();

        Project::factory()
            ->forContact($customer)
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/reports/project-profitability');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'projects',
                'totals' => [
                    'total_contract',
                    'total_revenue',
                    'total_costs',
                    'total_profit',
                    'average_margin',
                    'projects_count',
                    'profitable_count',
                    'loss_count',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Profitabilitas Proyek')
            ->assertJsonPath('totals.projects_count', 3);
    });

    it('can filter by status', function () {
        $customer = Contact::factory()->customer()->create();

        Project::factory()->forContact($customer)->inProgress()->count(2)->create();
        Project::factory()->forContact($customer)->completed()->count(1)->create();

        $response = $this->getJson('/api/v1/reports/project-profitability?status=in_progress');

        $response->assertOk()
            ->assertJsonPath('totals.projects_count', 2);
    });

    it('can filter by date range', function () {
        $customer = Contact::factory()->customer()->create();

        // Projects starting in 2024
        Project::factory()->forContact($customer)->create([
            'start_date' => '2024-03-01',
        ]);
        Project::factory()->forContact($customer)->create([
            'start_date' => '2024-06-01',
        ]);

        // Project starting in 2023
        Project::factory()->forContact($customer)->create([
            'start_date' => '2023-06-01',
        ]);

        $response = $this->getJson('/api/v1/reports/project-profitability?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('totals.projects_count', 2)
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');
    });

    it('includes cost breakdown for each project', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        // Add different cost types
        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'total_cost' => 5000000,
        ]);
        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_LABOR,
            'total_cost' => 3000000,
        ]);

        $response = $this->getJson('/api/v1/reports/project-profitability');

        $response->assertOk();

        $projects = $response->json('projects');
        expect($projects)->toHaveCount(1);

        $projectData = $projects[0];
        expect($projectData['costs'])->toHaveKeys(['material', 'labor', 'subcontractor', 'equipment', 'overhead', 'other', 'total']);
        expect($projectData['costs']['material'])->toBe(5000000);
        expect($projectData['costs']['labor'])->toBe(3000000);
    });

    it('calculates totals correctly', function () {
        $customer = Contact::factory()->customer()->create();

        $project1 = Project::factory()->forContact($customer)->create([
            'contract_amount' => 100000000,
            'total_revenue' => 80000000,
            'total_cost' => 60000000,
            'gross_profit' => 20000000,
        ]);

        $project2 = Project::factory()->forContact($customer)->create([
            'contract_amount' => 50000000,
            'total_revenue' => 40000000,
            'total_cost' => 35000000,
            'gross_profit' => 5000000,
        ]);

        $response = $this->getJson('/api/v1/reports/project-profitability');

        $response->assertOk()
            ->assertJsonPath('totals.total_contract', 150000000)
            ->assertJsonPath('totals.total_revenue', 120000000)
            ->assertJsonPath('totals.total_costs', 95000000)
            ->assertJsonPath('totals.total_profit', 25000000)
            ->assertJsonPath('totals.profitable_count', 2)
            ->assertJsonPath('totals.loss_count', 0);
    });

});

describe('Project Profitability Detail', function () {

    it('can get single project profitability detail', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        $response = $this->getJson("/api/v1/reports/projects/{$project->id}/profitability");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'project' => [
                    'id',
                    'project_number',
                    'name',
                    'customer',
                    'status',
                ],
                'financials' => [
                    'contract_amount',
                    'budget_amount',
                    'total_revenue',
                    'total_cost',
                    'gross_profit',
                    'profit_margin',
                    'budget_variance',
                    'budget_utilization',
                    'is_over_budget',
                ],
                'cost_breakdown',
                'revenue_breakdown',
                'timeline',
                'progress',
                'monthly_costs',
                'kpis',
            ])
            ->assertJsonPath('report_name', 'Laporan Detail Profitabilitas Proyek')
            ->assertJsonPath('project.id', $project->id);
    });

    it('includes customer information', function () {
        $customer = Contact::factory()->customer()->create([
            'name' => 'PT Test Customer',
            'code' => 'CUST-001',
        ]);
        $project = Project::factory()->forContact($customer)->create();

        $response = $this->getJson("/api/v1/reports/projects/{$project->id}/profitability");

        $response->assertOk()
            ->assertJsonPath('project.customer.name', 'PT Test Customer')
            ->assertJsonPath('project.customer.code', 'CUST-001');
    });

    it('includes cost breakdown by type', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'total_cost' => 10000000,
        ]);
        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_SUBCONTRACTOR,
            'total_cost' => 8000000,
        ]);

        $response = $this->getJson("/api/v1/reports/projects/{$project->id}/profitability");

        $response->assertOk();

        $costBreakdown = $response->json('cost_breakdown');
        expect($costBreakdown['material'])->toBe(10000000);
        expect($costBreakdown['subcontractor'])->toBe(8000000);
    });

    it('includes timeline information', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-06-30',
            'actual_start_date' => '2024-01-15',
        ]);

        $response = $this->getJson("/api/v1/reports/projects/{$project->id}/profitability");

        $response->assertOk()
            ->assertJsonPath('timeline.planned_start', '2024-01-01')
            ->assertJsonPath('timeline.planned_end', '2024-06-30')
            ->assertJsonPath('timeline.actual_start', '2024-01-15');
    });

    it('includes progress metrics', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create([
            'progress_percentage' => 75.50,
        ]);

        // Add work orders
        WorkOrder::factory()->forProject($project)->completed()->count(2)->create();
        WorkOrder::factory()->forProject($project)->inProgress()->count(1)->create();

        $response = $this->getJson("/api/v1/reports/projects/{$project->id}/profitability");

        $response->assertOk()
            ->assertJsonPath('progress.percentage', 75.5)
            ->assertJsonPath('progress.work_orders_count', 3)
            ->assertJsonPath('progress.work_orders_completed', 2);
    });

});

describe('Project Cost Analysis Report', function () {

    it('can get project cost analysis', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        ProjectCost::factory()->forProject($project)->count(5)->create();

        $response = $this->getJson('/api/v1/reports/project-cost-analysis');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'by_type',
                'by_project',
                'totals' => [
                    'grand_total',
                    'cost_types_count',
                    'projects_count',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Analisis Biaya Proyek');
    });

    it('groups costs by type', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'total_cost' => 10000000,
        ]);
        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'total_cost' => 5000000,
        ]);
        ProjectCost::factory()->forProject($project)->create([
            'cost_type' => ProjectCost::TYPE_LABOR,
            'total_cost' => 7000000,
        ]);

        $response = $this->getJson('/api/v1/reports/project-cost-analysis');

        $response->assertOk();

        $byType = $response->json('by_type');
        expect($byType['material']['total'])->toBe(15000000);
        expect($byType['material']['count'])->toBe(2);
        expect($byType['labor']['total'])->toBe(7000000);
        expect($byType['labor']['count'])->toBe(1);
    });

    it('groups costs by project', function () {
        $customer = Contact::factory()->customer()->create();

        $project1 = Project::factory()->forContact($customer)->create([
            'project_number' => 'PRJ-001',
        ]);
        $project2 = Project::factory()->forContact($customer)->create([
            'project_number' => 'PRJ-002',
        ]);

        ProjectCost::factory()->forProject($project1)->create(['total_cost' => 10000000]);
        ProjectCost::factory()->forProject($project2)->create(['total_cost' => 5000000]);
        ProjectCost::factory()->forProject($project2)->create(['total_cost' => 3000000]);

        $response = $this->getJson('/api/v1/reports/project-cost-analysis');

        $response->assertOk()
            ->assertJsonPath('totals.projects_count', 2)
            ->assertJsonPath('totals.grand_total', 18000000);

        $byProject = collect($response->json('by_project'));
        $prj1 = $byProject->firstWhere('project_number', 'PRJ-001');
        $prj2 = $byProject->firstWhere('project_number', 'PRJ-002');

        expect($prj1['total_cost'])->toBe(10000000);
        expect($prj2['total_cost'])->toBe(8000000);
    });

    it('can filter by date range', function () {
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create();

        // Cost in 2024
        ProjectCost::factory()->forProject($project)->create([
            'cost_date' => '2024-06-15',
            'total_cost' => 10000000,
        ]);

        // Cost in 2023
        ProjectCost::factory()->forProject($project)->create([
            'cost_date' => '2023-06-15',
            'total_cost' => 5000000,
        ]);

        $response = $this->getJson('/api/v1/reports/project-cost-analysis?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('totals.grand_total', 10000000)
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');
    });

});
