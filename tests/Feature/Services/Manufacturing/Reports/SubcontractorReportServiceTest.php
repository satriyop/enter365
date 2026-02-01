<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Projects\Project;
use App\Models\Shared\SubcontractorInvoice;
use App\Services\Manufacturing\Reports\SubcontractorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new SubcontractorReportService;
});

describe('getSubcontractorSummary', function () {
    test('returns correct structure when empty', function () {
        $result = $this->service->getSubcontractorSummary();

        expect($result)->toHaveKeys(['report_name', 'period', 'subcontractors', 'totals'])
            ->and($result['report_name'])->toBe('Laporan Subkontraktor')
            ->and($result['period'])->toBe(['start' => null, 'end' => null])
            ->and($result['subcontractors'])->toBeEmpty()
            ->and($result['totals'])->toHaveKeys([
                'total_subcontractors',
                'total_agreed',
                'total_paid',
                'total_outstanding',
                'total_retention',
            ])
            ->and($result['totals']['total_subcontractors'])->toBe(0)
            ->and($result['totals']['total_agreed'])->toEqual(0)
            ->and($result['totals']['total_paid'])->toEqual(0)
            ->and($result['totals']['total_outstanding'])->toEqual(0)
            ->and($result['totals']['total_retention'])->toEqual(0);
    });

    test('includes subcontractor with work orders', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor A',
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'sc_wo_number' => 'SCWO-001',
            'status' => DocumentStatus::InProgress,
            'agreed_amount' => 10000000,
            'actual_amount' => 9500000,
            'retention_amount' => 500000,
            'amount_invoiced' => 8000000,
            'amount_paid' => 7000000,
        ]);

        $result = $this->service->getSubcontractorSummary();

        expect($result['subcontractors'])->toHaveCount(1);

        $sub = $result['subcontractors']->first();
        expect($sub)->toHaveKeys(['id', 'code', 'name', 'work_orders', 'financials', 'performance'])
            ->and($sub['code'])->toBe('SC001')
            ->and($sub['name'])->toBe('Subkontraktor A')
            ->and($sub['work_orders'])->toBe([
                'total' => 1,
                'completed' => 0,
                'in_progress' => 1,
                'draft' => 0,
            ]);
    });

    test('excludes subcontractors with zero work orders', function () {
        Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor Tanpa WO',
        ]);

        $subcontractorWithWO = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC002',
            'name' => 'Subkontraktor Dengan WO',
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractorWithWO->id,
            'project_id' => $project->id,
        ]);

        $result = $this->service->getSubcontractorSummary();

        expect($result['subcontractors'])->toHaveCount(1)
            ->and($result['subcontractors']->first()['code'])->toBe('SC002');
    });

    test('calculates financials correctly', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor A',
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'agreed_amount' => 10000000,
            'actual_amount' => 9500000,
            'retention_amount' => 500000,
            'amount_invoiced' => 8000000,
            'amount_paid' => 7000000,
        ]);

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'agreed_amount' => 5000000,
            'actual_amount' => 4800000,
            'retention_amount' => 250000,
            'amount_invoiced' => 4000000,
            'amount_paid' => 3500000,
        ]);

        $result = $this->service->getSubcontractorSummary();

        $financials = $result['subcontractors']->first()['financials'];
        expect($financials)->toHaveKeys([
            'total_agreed', 'total_actual', 'total_invoiced',
            'total_paid', 'outstanding', 'retention_held',
        ])
            ->and($financials['total_agreed'])->toEqual(15000000)
            ->and($financials['total_actual'])->toEqual(14300000)
            ->and($financials['total_invoiced'])->toEqual(12000000)
            ->and($financials['total_paid'])->toEqual(10500000)
            ->and($financials['outstanding'])->toEqual(1500000)
            ->and($financials['retention_held'])->toEqual(750000);

        expect($result['totals']['total_subcontractors'])->toBe(1)
            ->and($result['totals']['total_agreed'])->toEqual(15000000)
            ->and($result['totals']['total_paid'])->toEqual(10500000)
            ->and($result['totals']['total_outstanding'])->toEqual(1500000)
            ->and($result['totals']['total_retention'])->toEqual(750000);
    });

    test('filters by date range', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'scheduled_start_date' => '2024-02-01',
            'agreed_amount' => 10000000,
        ]);

        // Outside range
        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'scheduled_start_date' => '2024-01-15',
            'agreed_amount' => 5000000,
        ]);

        $result = $this->service->getSubcontractorSummary('2024-02-01', '2024-02-28');

        expect($result['period'])->toBe(['start' => '2024-02-01', 'end' => '2024-02-28'])
            ->and($result['subcontractors']->first()['work_orders']['total'])->toBe(1)
            ->and($result['subcontractors']->first()['financials']['total_agreed'])->toEqual(10000000);
    });
});

describe('getSubcontractorDetail', function () {
    test('returns correct structure', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor Detail',
        ]);

        $result = $this->service->getSubcontractorDetail($subcontractor);

        expect($result)->toHaveKeys(['report_name', 'subcontractor', 'period', 'work_orders', 'invoices', 'summary'])
            ->and($result['report_name'])->toBe('Laporan Detail Subkontraktor')
            ->and($result['subcontractor'])->toHaveKeys(['id', 'code', 'name', 'phone', 'email', 'hourly_rate', 'daily_rate'])
            ->and($result['period'])->toBe(['start' => null, 'end' => null])
            ->and($result['work_orders'])->toBeEmpty()
            ->and($result['invoices'])->toBeEmpty()
            ->and($result['summary'])->toHaveKeys([
                'total_work_orders', 'completed_work_orders',
                'total_agreed', 'total_actual', 'total_invoiced',
                'total_paid', 'outstanding', 'retention_held',
            ]);
    });

    test('includes work order data and invoices', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor Detail',
        ]);

        $project = Project::factory()->create([
            'project_number' => 'PRJ001',
            'name' => 'Project Test',
        ]);

        $workOrder = SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'sc_wo_number' => 'SCWO-001',
            'name' => 'Pekerjaan Instalasi',
            'status' => DocumentStatus::InProgress,
            'agreed_amount' => 10000000,
            'actual_amount' => 9500000,
            'retention_amount' => 500000,
            'amount_invoiced' => 8000000,
            'amount_paid' => 7000000,
            'scheduled_start_date' => '2024-02-01',
            'scheduled_end_date' => '2024-02-28',
            'completion_percentage' => 75.5,
        ]);

        SubcontractorInvoice::factory()->create([
            'subcontractor_work_order_id' => $workOrder->id,
            'invoice_number' => 'INV-SC-001',
            'invoice_date' => '2024-02-15',
            'gross_amount' => 8000000,
            'status' => DocumentStatus::InProgress,
        ]);

        $result = $this->service->getSubcontractorDetail($subcontractor);

        expect($result['work_orders'])->toHaveCount(1);

        $wo = $result['work_orders']->first();
        expect($wo)->toHaveKeys([
            'id', 'sc_wo_number', 'name', 'project_number', 'project_name',
            'status', 'agreed_amount', 'actual_amount', 'retention_amount',
            'amount_invoiced', 'amount_paid', 'scheduled_start', 'scheduled_end',
            'actual_start', 'actual_end', 'completion_percentage',
        ])
            ->and($wo['sc_wo_number'])->toBe('SCWO-001')
            ->and($wo['name'])->toBe('Pekerjaan Instalasi')
            ->and($wo['project_number'])->toBe('PRJ001')
            ->and($wo['project_name'])->toBe('Project Test');

        expect($result['invoices'])->toHaveCount(1);

        $inv = $result['invoices']->first();
        expect($inv)->toHaveKeys(['id', 'invoice_number', 'invoice_date', 'amount', 'status', 'sc_wo_number'])
            ->and($inv['invoice_number'])->toBe('INV-SC-001');

        expect($result['summary']['total_work_orders'])->toBe(1)
            ->and($result['summary']['total_agreed'])->toEqual(10000000)
            ->and($result['summary']['total_actual'])->toEqual(9500000)
            ->and($result['summary']['retention_held'])->toEqual(500000)
            ->and($result['summary']['total_invoiced'])->toEqual(8000000)
            ->and($result['summary']['total_paid'])->toEqual(7000000)
            ->and($result['summary']['outstanding'])->toEqual(1000000);
    });
});

describe('getRetentionSummary', function () {
    test('returns work orders with retention greater than zero', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'retention_amount' => 500000,
            'status' => DocumentStatus::InProgress,
        ]);

        // Zero retention — excluded
        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'retention_amount' => 0,
            'status' => DocumentStatus::InProgress,
        ]);

        $result = $this->service->getRetentionSummary();

        expect($result)->toHaveKeys(['report_name', 'retentions', 'by_subcontractor', 'totals'])
            ->and($result['report_name'])->toBe('Laporan Retensi Subkontraktor')
            ->and($result['retentions'])->toHaveCount(1);
    });

    test('identifies releasable retention for completed work orders', function () {
        $subcontractor = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor A',
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'sc_wo_number' => 'SCWO-001',
            'retention_amount' => 500000,
            'status' => DocumentStatus::Completed,
        ]);

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'sc_wo_number' => 'SCWO-002',
            'retention_amount' => 300000,
            'status' => DocumentStatus::InProgress,
        ]);

        $result = $this->service->getRetentionSummary();

        expect($result['retentions'])->toHaveCount(2);

        $completedWO = $result['retentions']->firstWhere('sc_wo_number', 'SCWO-001');
        $inProgressWO = $result['retentions']->firstWhere('sc_wo_number', 'SCWO-002');

        expect($completedWO['is_releasable'])->toBeTrue()
            ->and($inProgressWO['is_releasable'])->toBeFalse();

        expect($result['totals']['total_retention_held'])->toEqual(800000)
            ->and($result['totals']['releasable_amount'])->toEqual(500000)
            ->and($result['totals']['pending_amount'])->toEqual(300000)
            ->and($result['totals']['work_orders_count'])->toBe(2);
    });

    test('groups by subcontractor in by_subcontractor key', function () {
        $subcontractor1 = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC001',
            'name' => 'Subkontraktor A',
        ]);

        $subcontractor2 = Contact::factory()->create([
            'is_subcontractor' => true,
            'code' => 'SC002',
            'name' => 'Subkontraktor B',
        ]);

        $project = Project::factory()->create();

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor1->id,
            'project_id' => $project->id,
            'retention_amount' => 500000,
            'status' => DocumentStatus::Completed,
        ]);

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor1->id,
            'project_id' => $project->id,
            'retention_amount' => 300000,
            'status' => DocumentStatus::InProgress,
        ]);

        SubcontractorWorkOrder::factory()->create([
            'subcontractor_id' => $subcontractor2->id,
            'project_id' => $project->id,
            'retention_amount' => 250000,
            'status' => DocumentStatus::Completed,
        ]);

        $result = $this->service->getRetentionSummary();

        // by_subcontractor groups by subcontractor_name
        expect($result['by_subcontractor'])->toHaveCount(2);

        $sc1 = $result['by_subcontractor']->firstWhere('subcontractor', 'Subkontraktor A');
        $sc2 = $result['by_subcontractor']->firstWhere('subcontractor', 'Subkontraktor B');

        expect($sc1['work_orders_count'])->toBe(2)
            ->and($sc1['total_retention'])->toEqual(800000)
            ->and($sc1['releasable_amount'])->toEqual(500000);

        expect($sc2['work_orders_count'])->toBe(1)
            ->and($sc2['total_retention'])->toEqual(250000)
            ->and($sc2['releasable_amount'])->toEqual(250000);

        expect($result['totals']['total_retention_held'])->toEqual(1050000)
            ->and($result['totals']['releasable_amount'])->toEqual(750000)
            ->and($result['totals']['pending_amount'])->toEqual(300000);
    });
});
