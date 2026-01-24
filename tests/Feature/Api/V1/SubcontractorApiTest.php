<?php

use App\Models\Contacts\Contact;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Projects\Project;
use App\Models\Purchasing\Bill;
use App\Models\Shared\SubcontractorInvoice;
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

describe('Subcontractor Work Order CRUD', function () {

    it('can list all subcontractor work orders', function () {
        SubcontractorWorkOrder::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/subcontractor-work-orders');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by status', function () {
        SubcontractorWorkOrder::factory()->draft()->count(2)->create();
        SubcontractorWorkOrder::factory()->inProgress()->count(3)->create();

        $response = $this->getJson('/api/v1/subcontractor-work-orders?status=in_progress');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter by subcontractor', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();
        SubcontractorWorkOrder::factory()->forSubcontractor($subcontractor)->count(2)->create();
        SubcontractorWorkOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/subcontractor-work-orders?subcontractor_id={$subcontractor->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter by project', function () {
        $project = Project::factory()->create();
        SubcontractorWorkOrder::factory()->forProject($project)->count(2)->create();
        SubcontractorWorkOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/subcontractor-work-orders?project_id={$project->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter active work orders', function () {
        SubcontractorWorkOrder::factory()->draft()->count(2)->create();
        SubcontractorWorkOrder::factory()->inProgress()->count(1)->create();
        SubcontractorWorkOrder::factory()->completed()->count(3)->create();

        $response = $this->getJson('/api/v1/subcontractor-work-orders?active=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a subcontractor work order', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();

        $response = $this->postJson('/api/v1/subcontractor-work-orders', [
            'subcontractor_id' => $subcontractor->id,
            'name' => 'Instalasi Panel Listrik',
            'description' => 'Instalasi panel listrik 3 phase',
            'agreed_amount' => 50000000,
            'retention_percent' => 5,
            'scheduled_start_date' => now()->addDays(7)->toDateString(),
            'scheduled_end_date' => now()->addDays(30)->toDateString(),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.name', 'Instalasi Panel Listrik')
            ->assertJsonPath('data.agreed_amount', 50000000)
            ->assertJsonPath('data.retention_percent', 5);
    });

    it('can create with project', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();
        $project = Project::factory()->create();

        $response = $this->postJson('/api/v1/subcontractor-work-orders', [
            'subcontractor_id' => $subcontractor->id,
            'project_id' => $project->id,
            'name' => 'Project Subcontract',
            'agreed_amount' => 25000000,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.project_id', $project->id);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/subcontractor-work-orders', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['subcontractor_id', 'name', 'agreed_amount']);
    });

    it('can show a subcontractor work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->create();

        $response = $this->getJson("/api/v1/subcontractor-work-orders/{$scWo->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $scWo->id);
    });

    it('can update a draft work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->draft()->create();

        $response = $this->putJson("/api/v1/subcontractor-work-orders/{$scWo->id}", [
            'name' => 'Updated Name',
            'agreed_amount' => 60000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name')
            ->assertJsonPath('data.agreed_amount', 60000000);
    });

    it('can delete a draft work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/subcontractor-work-orders/{$scWo->id}");

        $response->assertOk();
        expect(SubcontractorWorkOrder::find($scWo->id))->toBeNull();
    });

    it('cannot delete a completed work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->completed()->create();

        $response = $this->deleteJson("/api/v1/subcontractor-work-orders/{$scWo->id}");

        $response->assertStatus(422);
    });
});

describe('Subcontractor Work Order Workflow', function () {

    it('can assign a draft work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/assign");

        $response->assertOk()
            ->assertJsonPath('data.status', 'assigned');
    });

    it('cannot assign an already assigned work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->assigned()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/assign");

        $response->assertStatus(422);
    });

    it('can start an assigned work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->assigned()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress');
    });

    it('cannot start a draft work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->draft()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/start");

        $response->assertStatus(422);
    });

    it('can update progress', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/update-progress", [
            'completion_percentage' => 75,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.completion_percentage', 75);
    });

    it('validates progress percentage', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/update-progress", [
            'completion_percentage' => 150,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('completion_percentage');
    });

    it('can complete a work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create([
            'agreed_amount' => 50000000,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/complete", [
            'actual_amount' => 52000000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actual_amount', 52000000)
            ->assertJsonPath('data.completion_percentage', 100);
    });

    it('can complete with default actual amount', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create([
            'agreed_amount' => 50000000,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.actual_amount', 50000000);
    });

    it('can cancel a work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/cancel", [
            'reason' => 'Proyek dibatalkan',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Proyek dibatalkan');
    });

    it('cannot cancel a completed work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->completed()->create();

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/cancel");

        $response->assertStatus(422);
    });
});

describe('Subcontractor Invoices', function () {

    it('can create an invoice for work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create([
            'agreed_amount' => 100000000,
            'retention_percent' => 5,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/invoices", [
            'gross_amount' => 50000000,
            'description' => 'Termin 1 - 50%',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.gross_amount', 50000000)
            ->assertJsonPath('data.retention_held', 2500000) // 5% of 50M
            ->assertJsonPath('data.net_amount', 47500000)
            ->assertJsonPath('data.status', 'pending');
    });

    it('cannot invoice more than remaining amount', function () {
        $scWo = SubcontractorWorkOrder::factory()->inProgress()->create([
            'agreed_amount' => 50000000,
            'amount_invoiced' => 40000000,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-work-orders/{$scWo->id}/invoices", [
            'gross_amount' => 20000000,
        ]);

        $response->assertStatus(409); // Business rule violation
    });

    it('can list invoices for work order', function () {
        $scWo = SubcontractorWorkOrder::factory()->create();
        SubcontractorInvoice::factory()->forWorkOrder($scWo)->count(3)->create();

        $response = $this->getJson("/api/v1/subcontractor-work-orders/{$scWo->id}/invoices");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can list all invoices', function () {
        SubcontractorInvoice::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/subcontractor-invoices');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter invoices by status', function () {
        SubcontractorInvoice::factory()->pending()->count(3)->create();
        SubcontractorInvoice::factory()->approved()->count(2)->create();

        $response = $this->getJson('/api/v1/subcontractor-invoices?status=pending');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can show an invoice', function () {
        $invoice = SubcontractorInvoice::factory()->create();

        $response = $this->getJson("/api/v1/subcontractor-invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $invoice->id);
    });

    it('can update a pending invoice', function () {
        $invoice = SubcontractorInvoice::factory()->pending()->create([
            'gross_amount' => 10000000,
            'retention_held' => 500000,
            'other_deductions' => 0,
            'net_amount' => 9500000,
        ]);

        $response = $this->putJson("/api/v1/subcontractor-invoices/{$invoice->id}", [
            'other_deductions' => 100000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.other_deductions', 100000)
            ->assertJsonPath('data.net_amount', 9400000);
    });

    it('can approve an invoice', function () {
        $invoice = SubcontractorInvoice::factory()->pending()->create();

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');
    });

    it('can reject an invoice', function () {
        $invoice = SubcontractorInvoice::factory()->pending()->create();

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/reject", [
            'reason' => 'Dokumen tidak lengkap',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Dokumen tidak lengkap');
    });

    it('requires reason for rejection', function () {
        $invoice = SubcontractorInvoice::factory()->pending()->create();

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/reject", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
    });

    it('can convert approved invoice to bill', function () {
        $invoice = SubcontractorInvoice::factory()->approved()->create([
            'gross_amount' => 10000000,
            'retention_held' => 500000,
            'net_amount' => 9500000,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/convert-to-bill");

        $response->assertOk()
            ->assertJsonStructure(['message', 'bill']);

        $invoice->refresh();
        expect($invoice->bill_id)->not->toBeNull();
        expect($invoice->converted_to_bill_at)->not->toBeNull();

        $bill = Bill::find($invoice->bill_id);
        expect($bill)->not->toBeNull();
        expect($bill->total_amount)->toBe(9500000);
    });

    it('cannot convert pending invoice to bill', function () {
        $invoice = SubcontractorInvoice::factory()->pending()->create();

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/convert-to-bill");

        $response->assertStatus(422);
    });

    it('cannot convert already converted invoice', function () {
        $bill = Bill::factory()->create();
        $invoice = SubcontractorInvoice::factory()->approved()->create([
            'bill_id' => $bill->id,
        ]);

        $response = $this->postJson("/api/v1/subcontractor-invoices/{$invoice->id}/convert-to-bill");

        $response->assertStatus(422);
    });
});

describe('Subcontractor Statistics', function () {

    it('can get subcontractors list', function () {
        Contact::factory()->subcontractor()->count(3)->create();
        Contact::factory()->supplier()->count(2)->create();

        $response = $this->getJson('/api/v1/subcontractors');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can get statistics', function () {
        SubcontractorWorkOrder::factory()->draft()->count(2)->create();
        SubcontractorWorkOrder::factory()->inProgress()->count(1)->create();
        $completedWOs = SubcontractorWorkOrder::factory()->completed()->count(3)->create();

        // Link invoices to existing work orders to avoid creating extra WOs
        SubcontractorInvoice::factory()->pending()->forWorkOrder($completedWOs[0])->count(2)->create();
        SubcontractorInvoice::factory()->approved()->forWorkOrder($completedWOs[1])->create();

        $response = $this->getJson('/api/v1/subcontractor-work-orders-statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'work_orders' => ['total', 'by_status', 'total_agreed_amount', 'total_actual_amount'],
                'invoices' => ['total', 'by_status', 'total_invoiced', 'pending_approval'],
            ])
            ->assertJsonPath('work_orders.total', 6)
            ->assertJsonPath('invoices.total', 3);
    });

    it('can filter statistics by subcontractor', function () {
        $subcontractor = Contact::factory()->subcontractor()->create();
        SubcontractorWorkOrder::factory()->forSubcontractor($subcontractor)->count(2)->create();
        SubcontractorWorkOrder::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/subcontractor-work-orders-statistics?subcontractor_id={$subcontractor->id}");

        $response->assertOk()
            ->assertJsonPath('work_orders.total', 2);
    });
});
