<?php

use App\Models\Contacts\Contact;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
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

describe('Project CRUD', function () {

    it('can list all projects', function () {
        Project::factory()->count(10)->create();

        $this->assertMaxQueries(15, function () {
            $response = $this->getJson('/api/v1/projects');
            $response->assertOk();
        });

        $response = $this->getJson('/api/v1/projects');
        $response->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'project_number',
                        'name',
                        'status' => ['value', 'label', 'color', 'is_terminal', 'is_editable'],
                        'priority',
                        'progress_percentage',
                    ],
                ],
            ]);
    });

    it('can filter projects by status', function () {
        Project::factory()->draft()->count(2)->create();
        Project::factory()->inProgress()->count(3)->create();

        $response = $this->getJson('/api/v1/projects?status=in_progress');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter projects by priority', function () {
        Project::factory()->create(['priority' => Project::PRIORITY_NORMAL]);
        Project::factory()->highPriority()->count(2)->create();
        Project::factory()->urgent()->count(1)->create();

        $response = $this->getJson('/api/v1/projects?priority=high');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter projects by contact', function () {
        $customer = Contact::factory()->customer()->create();
        Project::factory()->forContact($customer)->count(2)->create();
        Project::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/projects?contact_id={$customer->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter projects by manager', function () {
        $manager = User::factory()->create();
        Project::factory()->count(2)->create(['manager_id' => $manager->id]);
        Project::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/projects?manager_id={$manager->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter overdue projects', function () {
        Project::factory()->overdue()->count(2)->create();
        Project::factory()->inProgress()->count(3)->create();

        $response = $this->getJson('/api/v1/projects?overdue_only=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can search projects', function () {
        Project::factory()->create(['name' => 'Solar Panel Installation']);
        Project::factory()->create(['name' => 'Panel Listrik Workshop']);
        Project::factory()->create(['project_number' => 'PRJ-PANEL-001']);

        $response = $this->getJson('/api/v1/projects?search=panel');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a project', function () {
        $customer = Contact::factory()->customer()->create();
        $manager = User::factory()->create();

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Solar EPC Project',
            'description' => 'Installation of 100kWp solar power system',
            'contact_id' => $customer->id,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
            'budget_amount' => 500000000,
            'contract_amount' => 600000000,
            'priority' => 'high',
            'location' => 'Jakarta',
            'manager_id' => $manager->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonPath('data.name', 'Solar EPC Project')
            ->assertJsonPath('data.priority', 'high');
    });

    it('validates required fields when creating project', function () {
        $response = $this->postJson('/api/v1/projects', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'contact_id']);
    });

    it('validates end_date is after start_date', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Test Project',
            'contact_id' => $customer->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-01-01',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end_date']);
    });

    it('can show a single project with costs and revenues', function () {
        $project = Project::factory()->create();
        ProjectCost::factory()->forProject($project)->count(3)->create();
        ProjectRevenue::factory()->forProject($project)->count(2)->create();

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $project->id)
            ->assertJsonCount(3, 'data.costs')
            ->assertJsonCount(2, 'data.revenues');
    });

    it('can update a draft project', function () {
        $project = Project::factory()->draft()->create();

        $response = $this->putJson("/api/v1/projects/{$project->id}", [
            'name' => 'Updated Project Name',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Project Name')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('can delete a draft project', function () {
        $project = Project::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertOk();
        $this->assertSoftDeleted('projects', ['id' => $project->id]);
    });

    it('cannot delete non-draft/planning project', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->deleteJson("/api/v1/projects/{$project->id}");

        $response->assertUnprocessable();
    });
});

describe('Project Workflow', function () {

    it('can start a draft project', function () {
        $project = Project::factory()->draft()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');

        $project->refresh();
        expect($project->actual_start_date)->not->toBeNull();
    });

    it('can start a planning project', function () {
        $project = Project::factory()->planning()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');
    });

    it('cannot start in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/start");

        $response->assertUnprocessable();
    });

    it('can put in-progress project on hold', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/hold", [
            'reason' => 'Material belum tersedia',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'on_hold');

        $project->refresh();
        expect($project->notes)->toContain('Ditunda: Material belum tersedia');
    });

    it('cannot put non-in-progress project on hold', function () {
        $project = Project::factory()->draft()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/hold");

        $response->assertUnprocessable();
    });

    it('can resume on-hold project', function () {
        $project = Project::factory()->onHold()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/resume");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');
    });

    it('cannot resume non-on-hold project', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/resume");

        $response->assertUnprocessable();
    });

    it('can complete in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed')
            ->assertJsonPath('data.progress_percentage', 100);

        $project->refresh();
        expect($project->actual_end_date)->not->toBeNull();
    });

    it('cannot complete non-in-progress project', function () {
        $project = Project::factory()->onHold()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/complete");

        $response->assertUnprocessable();
    });

    it('can cancel a project', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/cancel", [
            'reason' => 'Proyek dibatalkan oleh klien',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');

        $project->refresh();
        expect($project->notes)->toContain('Dibatalkan: Proyek dibatalkan oleh klien');
    });

    it('cannot cancel completed project', function () {
        $project = Project::factory()->completed()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Project Progress', function () {

    it('can update project progress', function () {
        $project = Project::factory()->inProgress()->create(['progress_percentage' => 10]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/update-progress", [
            'progress' => 50,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.progress_percentage', 50);
    });

    it('validates progress percentage range', function () {
        $project = Project::factory()->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/update-progress", [
            'progress' => 150,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['progress']);
    });

    it('cannot update progress for non-in-progress project', function () {
        $project = Project::factory()->draft()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/update-progress", [
            'progress' => 50,
        ]);

        $response->assertUnprocessable();
    });
});

describe('Project from Quotation', function () {

    it('can create project from approved quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'subject' => 'Solar Panel Installation',
            'total_amount' => 100000000,
        ]);
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/create-project", [
            'name' => 'Solar Project Phase 1',
            'budget_amount' => 80000000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-06-30',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'planning')
            ->assertJsonPath('data.quotation_id', $quotation->id)
            ->assertJsonPath('data.contract_amount', 100000000);

        // Verify quotation is linked to project
        $quotation->refresh();
        $projectId = $response->json('data.id');
        expect($quotation->project_id)->toBe($projectId);
    });

    it('cannot create project from non-approved quotation', function () {
        $quotation = Quotation::factory()->draft()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/create-project");

        $response->assertUnprocessable();
    });

    it('can create project from converted quotation', function () {
        $quotation = Quotation::factory()->converted()->create([
            'total_amount' => 150000000,
        ]);
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/create-project");

        $response->assertCreated()
            ->assertJsonPath('data.quotation_id', $quotation->id);
    });
});

describe('Project Costs', function () {

    it('can add cost to project', function () {
        $project = Project::factory()->inProgress()->create(['total_cost' => 0]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/costs", [
            'type' => 'material',
            'description' => 'Solar Panels',
            'quantity' => 100,
            'unit' => 'pcs',
            'unit_cost' => 500000,
            'date' => '2025-01-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.cost_type', 'material')
            ->assertJsonPath('data.total_cost', 50000000);

        $project->refresh();
        expect($project->total_cost)->toBe(50000000);
    });

    it('validates required fields when adding cost', function () {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/costs", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'description', 'unit_cost']);
    });

    it('can update project cost', function () {
        $project = Project::factory()->create(['total_cost' => 0]);
        $cost = ProjectCost::factory()->forProject($project)->create([
            'quantity' => 1,
            'unit_cost' => 100000,
            'total_cost' => 100000,
        ]);
        $project->update(['total_cost' => 100000]);

        $response = $this->putJson("/api/v1/projects/{$project->id}/costs/{$cost->id}", [
            'quantity' => 2,
            'unit_cost' => 150000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_cost', 300000);

        $project->refresh();
        expect($project->total_cost)->toBe(300000);
    });

    it('returns 404 when updating cost from wrong project', function () {
        $project1 = Project::factory()->create();
        $project2 = Project::factory()->create();
        $cost = ProjectCost::factory()->forProject($project1)->create();

        $response = $this->putJson("/api/v1/projects/{$project2->id}/costs/{$cost->id}", [
            'quantity' => 2,
        ]);

        $response->assertNotFound();
    });

    it('can delete project cost', function () {
        $project = Project::factory()->create(['total_cost' => 100000]);
        $cost = ProjectCost::factory()->forProject($project)->create([
            'total_cost' => 100000,
        ]);

        $response = $this->deleteJson("/api/v1/projects/{$project->id}/costs/{$cost->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('project_costs', ['id' => $cost->id]);

        $project->refresh();
        expect($project->total_cost)->toBe(0);
    });
});

describe('Project Revenues', function () {

    it('can add revenue to project', function () {
        $project = Project::factory()->inProgress()->create(['total_revenue' => 0]);

        $response = $this->postJson("/api/v1/projects/{$project->id}/revenues", [
            'type' => 'milestone',
            'description' => 'Phase 1 Completion',
            'amount' => 20000000,
            'date' => '2025-02-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.revenue_type', 'milestone')
            ->assertJsonPath('data.amount', 20000000);

        $project->refresh();
        expect($project->total_revenue)->toBe(20000000);
    });

    it('validates required fields when adding revenue', function () {
        $project = Project::factory()->create();

        $response = $this->postJson("/api/v1/projects/{$project->id}/revenues", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'description', 'amount']);
    });

    it('can update project revenue', function () {
        $project = Project::factory()->create(['total_revenue' => 0]);
        $revenue = ProjectRevenue::factory()->forProject($project)->create([
            'amount' => 100000,
        ]);
        $project->update(['total_revenue' => 100000]);

        $response = $this->putJson("/api/v1/projects/{$project->id}/revenues/{$revenue->id}", [
            'amount' => 200000,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.amount', 200000);

        $project->refresh();
        expect($project->total_revenue)->toBe(200000);
    });

    it('can delete project revenue', function () {
        $project = Project::factory()->create(['total_revenue' => 150000]);
        $revenue = ProjectRevenue::factory()->forProject($project)->create([
            'amount' => 150000,
        ]);

        $response = $this->deleteJson("/api/v1/projects/{$project->id}/revenues/{$revenue->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('project_revenues', ['id' => $revenue->id]);

        $project->refresh();
        expect($project->total_revenue)->toBe(0);
    });
});

describe('Project Summary', function () {

    it('returns project summary with financials', function () {
        $project = Project::factory()->inProgress()->create([
            'budget_amount' => 100000000,
            'contract_amount' => 120000000,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
        ]);
        ProjectCost::factory()->forProject($project)->withAmount(30000000)->create();
        ProjectCost::factory()->forProject($project)->withAmount(20000000)->create();
        ProjectRevenue::factory()->forProject($project)->withAmount(60000000)->create();

        $project->calculateFinancials();
        $project->save();

        $response = $this->getJson("/api/v1/projects/{$project->id}/summary");

        $response->assertOk()
            ->assertJsonPath('data.project_id', $project->id)
            ->assertJsonPath('data.financials.total_cost', 50000000)
            ->assertJsonPath('data.financials.total_revenue', 60000000)
            ->assertJsonPath('data.financials.gross_profit', 10000000)
            ->assertJsonPath('data.budget.is_over', false);
    });
});

describe('Project Statistics', function () {

    it('returns project statistics', function () {
        Project::factory()->draft()->count(2)->create();
        Project::factory()->planning()->count(1)->create();
        Project::factory()->inProgress()->count(3)->create();
        Project::factory()->completed()->count(2)->create();

        $response = $this->getJson('/api/v1/projects-statistics');

        $response->assertOk()
            ->assertJsonPath('data.total_count', 8)
            ->assertJsonPath('data.by_status.draft', 2)
            ->assertJsonPath('data.by_status.planning', 1)
            ->assertJsonPath('data.by_status.in_progress', 3)
            ->assertJsonPath('data.by_status.completed', 2);
    });

    it('can filter statistics by date range', function () {
        Project::factory()->create(['start_date' => '2024-12-01']);
        Project::factory()->create(['start_date' => '2024-12-15']);
        Project::factory()->create(['start_date' => '2024-12-25']);

        $response = $this->getJson('/api/v1/projects-statistics?start_date=2024-12-10&end_date=2024-12-20');

        $response->assertOk()
            ->assertJsonPath('data.total_count', 1);
    });
});

describe('Project Overdue Detection', function () {

    it('detects overdue projects', function () {
        $project = Project::factory()->overdue()->create();

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_overdue', true);
    });

    it('completed projects are not overdue', function () {
        $project = Project::factory()->completed()->create([
            'end_date' => now()->subMonth(),
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_overdue', false);
    });
});

describe('Project Budget', function () {

    it('detects over budget projects', function () {
        $project = Project::factory()->create([
            'budget_amount' => 50000000,
            'total_cost' => 60000000,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_over_budget', true);
    });

    it('calculates budget utilization', function () {
        $project = Project::factory()->create([
            'budget_amount' => 100000000,
            'total_cost' => 75000000,
        ]);

        $response = $this->getJson("/api/v1/projects/{$project->id}");

        $response->assertOk()
            ->assertJsonPath('data.budget_utilization', 75);
    });
});
