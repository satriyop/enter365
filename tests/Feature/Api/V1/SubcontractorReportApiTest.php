<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Project;
use App\Models\Accounting\SubcontractorWorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Subcontractor Summary Report', function () {

    it('can get subcontractor summary', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->withAgreedAmount(50000000)
            ->count(3)
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'subcontractors',
                'totals' => [
                    'total_subcontractors',
                    'total_agreed',
                    'total_paid',
                    'total_outstanding',
                    'total_retention',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Subkontraktor')
            ->assertJsonPath('totals.total_subcontractors', 1);
    });

    it('includes financial breakdown for each subcontractor', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->completed()
            ->withAgreedAmount(100000000)
            ->create([
                'amount_invoiced' => 80000000,
                'amount_paid' => 60000000,
            ]);

        $response = $this->getJson('/api/v1/reports/subcontractor-summary');

        $response->assertOk();

        $subcontractors = $response->json('subcontractors');
        expect($subcontractors)->toHaveCount(1);

        $sub = $subcontractors[0];
        expect($sub['financials'])->toHaveKeys(['total_agreed', 'total_actual', 'total_invoiced', 'total_paid', 'outstanding', 'retention_held']);
        expect($sub['financials']['total_invoiced'])->toBe(80000000);
        expect($sub['financials']['total_paid'])->toBe(60000000);
        expect($sub['financials']['outstanding'])->toBe(20000000); // 80M - 60M
    });

    it('includes work order counts', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()->forSubcontractor($subcontractor)->completed()->count(2)->create();
        SubcontractorWorkOrder::factory()->forSubcontractor($subcontractor)->inProgress()->count(1)->create();
        SubcontractorWorkOrder::factory()->forSubcontractor($subcontractor)->draft()->count(1)->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-summary');

        $response->assertOk();

        $subcontractors = $response->json('subcontractors');
        $sub = $subcontractors[0];

        expect($sub['work_orders']['total'])->toBe(4);
        expect($sub['work_orders']['completed'])->toBe(2);
        expect($sub['work_orders']['in_progress'])->toBe(1);
        expect($sub['work_orders']['draft'])->toBe(1);
    });

    it('can filter by date range', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        // Work order in 2024
        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->create([
                'scheduled_start_date' => '2024-06-15',
            ]);

        // Work order in 2023
        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->create([
                'scheduled_start_date' => '2023-06-15',
            ]);

        $response = $this->getJson('/api/v1/reports/subcontractor-summary?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');

        $subcontractors = $response->json('subcontractors');
        $sub = $subcontractors[0];
        expect($sub['work_orders']['total'])->toBe(1);
    });

    it('excludes subcontractors without work orders', function () {
        $subcontractorWithWO = Contact::factory()->subcontractor()->create();
        Contact::factory()->subcontractor()->create(); // No work orders

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractorWithWO)
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-summary');

        $response->assertOk()
            ->assertJsonPath('totals.total_subcontractors', 1);
    });

});

describe('Subcontractor Detail Report', function () {

    it('can get subcontractor detail', function () {
        $subcontractor = Contact::factory()->subcontractor()->create([
            'name' => 'PT Test Subkontraktor',
            'code' => 'SUB-001',
        ]);

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->count(3)
            ->create();

        $response = $this->getJson("/api/v1/reports/subcontractors/{$subcontractor->id}/summary");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'subcontractor' => [
                    'id',
                    'code',
                    'name',
                    'phone',
                    'email',
                    'hourly_rate',
                    'daily_rate',
                ],
                'period' => ['start', 'end'],
                'work_orders',
                'invoices',
                'summary' => [
                    'total_work_orders',
                    'completed_work_orders',
                    'total_agreed',
                    'total_actual',
                    'total_invoiced',
                    'total_paid',
                    'outstanding',
                    'retention_held',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Detail Subkontraktor')
            ->assertJsonPath('subcontractor.name', 'PT Test Subkontraktor')
            ->assertJsonPath('subcontractor.code', 'SUB-001')
            ->assertJsonPath('summary.total_work_orders', 3);
    });

    it('includes project information in work orders', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();
        $customer = Contact::factory()->customer()->create();
        $project = Project::factory()->forContact($customer)->create([
            'project_number' => 'PRJ-TEST-001',
            'name' => 'Test Project',
        ]);

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->forProject($project)
            ->create();

        $response = $this->getJson("/api/v1/reports/subcontractors/{$subcontractor->id}/summary");

        $response->assertOk();

        $workOrders = $response->json('work_orders');
        expect($workOrders)->toHaveCount(1);
        expect($workOrders[0]['project_number'])->toBe('PRJ-TEST-001');
        expect($workOrders[0]['project_name'])->toBe('Test Project');
    });

    it('can filter by date range', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->create([
                'scheduled_start_date' => '2024-06-15',
            ]);

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->create([
                'scheduled_start_date' => '2023-06-15',
            ]);

        $response = $this->getJson("/api/v1/reports/subcontractors/{$subcontractor->id}/summary?start_date=2024-01-01&end_date=2024-12-31");

        $response->assertOk()
            ->assertJsonPath('summary.total_work_orders', 1);
    });

});

describe('Subcontractor Retention Report', function () {

    it('can get retention summary', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->withRetention(5)
            ->withAgreedAmount(100000000)
            ->count(2)
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-retention');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'retentions',
                'by_subcontractor',
                'totals' => [
                    'total_retention_held',
                    'releasable_amount',
                    'pending_amount',
                    'work_orders_count',
                ],
            ])
            ->assertJsonPath('report_name', 'Laporan Retensi Subkontraktor')
            ->assertJsonPath('totals.work_orders_count', 2);
    });

    it('calculates releasable vs pending retention', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        // Completed WO - retention should be releasable
        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->completed()
            ->withRetention(10)
            ->withAgreedAmount(50000000)
            ->create();

        // In progress WO - retention should be pending
        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->inProgress()
            ->withRetention(10)
            ->withAgreedAmount(50000000)
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-retention');

        $response->assertOk();

        $totals = $response->json('totals');
        // 50M * 10% = 5M retention each
        expect($totals['total_retention_held'])->toBe(10000000);
        expect($totals['releasable_amount'])->toBeGreaterThan(0); // Completed WO
        expect($totals['pending_amount'])->toBeGreaterThan(0); // In progress WO
    });

    it('groups retention by subcontractor', function () {
        $sub1 = Contact::factory()->subcontractor()->create(['name' => 'Subkontraktor A']);
        $sub2 = Contact::factory()->subcontractor()->create(['name' => 'Subkontraktor B']);

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($sub1)
            ->withRetention(10)
            ->withAgreedAmount(100000000)
            ->count(2)
            ->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($sub2)
            ->withRetention(10)
            ->withAgreedAmount(50000000)
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-retention');

        $response->assertOk();

        $bySubcontractor = $response->json('by_subcontractor');
        expect($bySubcontractor)->toHaveCount(2);

        $subA = collect($bySubcontractor)->firstWhere('subcontractor', 'Subkontraktor A');
        $subB = collect($bySubcontractor)->firstWhere('subcontractor', 'Subkontraktor B');

        expect($subA['work_orders_count'])->toBe(2);
        expect($subB['work_orders_count'])->toBe(1);
    });

    it('excludes work orders without retention', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->withRetention(10)
            ->withAgreedAmount(100000000)
            ->create();

        SubcontractorWorkOrder::factory()
            ->forSubcontractor($subcontractor)
            ->withoutRetention()
            ->create();

        $response = $this->getJson('/api/v1/reports/subcontractor-retention');

        $response->assertOk()
            ->assertJsonPath('totals.work_orders_count', 1);
    });

});
