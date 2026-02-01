<?php

declare(strict_types=1);

use App\Contracts\Projects\ProjectServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Contacts\Contact;
use App\Models\Projects\Project;
use App\Models\Projects\ProjectCost;
use App\Models\Projects\ProjectRevenue;
use App\Models\Sales\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->service = app(ProjectServiceInterface::class);
    $this->user = User::first();
});

describe('ProjectService - create', function () {
    it('creates a new project with required fields', function () {
        $contact = Contact::factory()->customer()->create();

        $data = [
            'name' => 'Test Project',
            'contact_id' => $contact->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'budget_amount' => 50000000,
            'contract_amount' => 60000000,
            'priority' => Project::PRIORITY_NORMAL,
        ];

        $project = $this->service->create($data);

        expect($project)
            ->toBeInstanceOf(Project::class)
            ->name->toBe('Test Project')
            ->contact_id->toBe($contact->id)
            ->status->toBe(DocumentStatus::Draft)
            ->budget_amount->toBe(50000000)
            ->contract_amount->toBe(60000000)
            ->priority->toBe(Project::PRIORITY_NORMAL)
            ->project_number->toContain('PRJ-');
    });

    it('creates project with manager and location', function () {
        $contact = Contact::factory()->customer()->create();
        $manager = User::factory()->create();

        $data = [
            'name' => 'Office Building',
            'contact_id' => $contact->id,
            'budget_amount' => 100000000,
            'contract_amount' => 120000000,
            'manager_id' => $manager->id,
            'location' => 'Jakarta Selatan',
            'notes' => 'High priority project',
        ];

        $project = $this->service->create($data);

        expect($project)
            ->manager_id->toBe($manager->id)
            ->location->toBe('Jakarta Selatan')
            ->notes->toBe('High priority project');
    });
});

describe('ProjectService - createFromQuotation', function () {
    it('creates project from approved quotation', function () {
        $quotation = Quotation::factory()->approved()->create();

        $project = $this->service->createFromQuotation($quotation);

        expect($project)
            ->toBeInstanceOf(Project::class)
            ->quotation_id->toBe($quotation->id)
            ->contact_id->toBe($quotation->contact_id)
            ->contract_amount->toBe($quotation->total_amount)
            ->budget_amount->toBe($quotation->total_amount)
            ->status->toBe(DocumentStatus::Planning)
            ->name->toContain($quotation->quotation_number);

        // Verify quotation is linked to project
        expect($quotation->fresh()->project_id)->toBe($project->id);
    });

    it('creates project from quotation with custom data', function () {
        $quotation = Quotation::factory()->approved()->create();
        $manager = User::factory()->create();

        $data = [
            'name' => 'Custom Project Name',
            'description' => 'Custom description',
            'budget_amount' => 75000000,
            'start_date' => now()->addWeek()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'priority' => Project::PRIORITY_HIGH,
            'manager_id' => $manager->id,
        ];

        $project = $this->service->createFromQuotation($quotation, $data);

        expect($project)
            ->name->toBe('Custom Project Name')
            ->description->toBe('Custom description')
            ->budget_amount->toBe(75000000)
            ->priority->toBe(Project::PRIORITY_HIGH)
            ->manager_id->toBe($manager->id);
    });
});

describe('ProjectService - update', function () {
    it('updates project details', function () {
        $project = Project::factory()->draft()->create();

        $data = [
            'name' => 'Updated Project Name',
            'description' => 'New description',
            'priority' => Project::PRIORITY_URGENT,
            'notes' => 'Updated notes',
        ];

        $updated = $this->service->update($project, $data);

        expect($updated)
            ->name->toBe('Updated Project Name')
            ->description->toBe('New description')
            ->priority->toBe(Project::PRIORITY_URGENT)
            ->notes->toBe('Updated notes');
    });

    it('updates project dates and budget', function () {
        $project = Project::factory()->draft()->create();

        $newStart = now()->addDays(5)->toDateString();
        $newEnd = now()->addMonths(4)->toDateString();

        $data = [
            'start_date' => $newStart,
            'end_date' => $newEnd,
            'budget_amount' => 80000000,
        ];

        $updated = $this->service->update($project, $data);

        expect($updated->start_date->toDateString())->toBe($newStart)
            ->and($updated->end_date->toDateString())->toBe($newEnd)
            ->and($updated->budget_amount)->toBe(80000000);
    });
});

describe('ProjectService - delete', function () {
    it('deletes draft project and cascades costs and revenues', function () {
        $project = Project::factory()->draft()->create();

        ProjectCost::factory()->forProject($project)->material()->create();
        ProjectRevenue::factory()->forProject($project)->milestone()->create();

        expect(ProjectCost::where('project_id', $project->id)->count())->toBe(1)
            ->and(ProjectRevenue::where('project_id', $project->id)->count())->toBe(1);

        $result = $this->service->delete($project);

        expect($result)->toBeTrue();
        expect(Project::withTrashed()->find($project->id))->not->toBeNull();
        expect(ProjectCost::where('project_id', $project->id)->count())->toBe(0);
        expect(ProjectRevenue::where('project_id', $project->id)->count())->toBe(0);
    });

    it('cannot delete planning project', function () {
        $project = Project::factory()->planning()->create();

        $this->service->delete($project);
    })->throws(DocumentLockedException::class);

    it('cannot delete in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $this->service->delete($project);
    })->throws(DocumentLockedException::class);

    it('cannot delete completed project', function () {
        $project = Project::factory()->completed()->create();

        $this->service->delete($project);
    })->throws(DocumentLockedException::class);

    it('cannot delete on-hold project', function () {
        $project = Project::factory()->onHold()->create();

        $this->service->delete($project);
    })->throws(DocumentLockedException::class);
});

describe('ProjectService - lifecycle transitions', function () {
    it('transitions project through full lifecycle: Draft → Planning → InProgress → Completed', function () {
        $project = Project::factory()->draft()->create();

        expect($project->status)->toBe(DocumentStatus::Draft);

        // Draft → Planning (via transitionTo)
        $project->transitionTo(DocumentStatus::Planning, $this->user->id);
        $project->refresh();
        expect($project->status)->toBe(DocumentStatus::Planning);

        // Planning → InProgress
        $project = $this->service->start($project, $this->user->id);
        expect($project->status)->toBe(DocumentStatus::InProgress)
            ->and($project->actual_start_date)->not->toBeNull();

        // InProgress → Completed
        $project = $this->service->complete($project, $this->user->id);
        expect($project->status)->toBe(DocumentStatus::Completed)
            ->and($project->actual_end_date)->not->toBeNull();
    });

    it('can start planning project', function () {
        $project = Project::factory()->planning()->create();

        $started = $this->service->start($project, $this->user->id);

        expect($started->status)->toBe(DocumentStatus::InProgress)
            ->and($started->actual_start_date)->not->toBeNull();
    });

    it('cannot start already in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $this->service->start($project);
    })->throws(StateTransitionException::class);

    it('cannot start completed project', function () {
        $project = Project::factory()->completed()->create();

        $this->service->start($project);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - putOnHold', function () {
    it('puts in-progress project on hold with reason', function () {
        $project = Project::factory()->inProgress()->create(['notes' => 'Original notes']);

        $onHold = $this->service->putOnHold($project, 'Waiting for materials', $this->user->id);

        expect($onHold->status)->toBe(DocumentStatus::OnHold)
            ->and($onHold->notes)->toContain('Original notes')
            ->and($onHold->notes)->toContain('Ditunda: Waiting for materials');
    });

    it('puts project on hold without reason', function () {
        $project = Project::factory()->inProgress()->create();

        $onHold = $this->service->putOnHold($project, null, $this->user->id);

        expect($onHold->status)->toBe(DocumentStatus::OnHold);
    });

    it('cannot put draft project on hold', function () {
        $project = Project::factory()->draft()->create();

        $this->service->putOnHold($project);
    })->throws(StateTransitionException::class);

    it('cannot put completed project on hold', function () {
        $project = Project::factory()->completed()->create();

        $this->service->putOnHold($project);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - resume', function () {
    it('resumes on-hold project to in-progress', function () {
        $project = Project::factory()->onHold()->create();

        $resumed = $this->service->resume($project, $this->user->id);

        expect($resumed->status)->toBe(DocumentStatus::InProgress);
    });

    it('cannot resume draft project', function () {
        $project = Project::factory()->draft()->create();

        $this->service->resume($project);
    })->throws(StateTransitionException::class);

    it('cannot resume in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $this->service->resume($project);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - cancel', function () {
    it('cancels draft project with reason', function () {
        $project = Project::factory()->draft()->create();

        $cancelled = $this->service->cancel($project, 'Client withdrew', $this->user->id);

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled)
            ->and($cancelled->notes)->toContain('Dibatalkan: Client withdrew');
    });

    it('cancels planning project', function () {
        $project = Project::factory()->planning()->create();

        $cancelled = $this->service->cancel($project, 'Budget issue');

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cancels in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $cancelled = $this->service->cancel($project, 'Force majeure');

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cancels on-hold project', function () {
        $project = Project::factory()->onHold()->create();

        $cancelled = $this->service->cancel($project, 'No longer viable');

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cannot cancel already completed project', function () {
        $project = Project::factory()->completed()->create();

        $this->service->cancel($project);
    })->throws(StateTransitionException::class);

    it('cannot cancel already cancelled project', function () {
        $project = Project::factory()->cancelled()->create();

        $this->service->cancel($project);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - complete', function () {
    it('completes in-progress project', function () {
        $project = Project::factory()->inProgress()->create();

        // Add some costs and revenues
        ProjectCost::factory()->forProject($project)->withAmount(5000000)->create();
        ProjectRevenue::factory()->forProject($project)->withAmount(8000000)->create();

        $completed = $this->service->complete($project, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Completed)
            ->and($completed->actual_end_date)->not->toBeNull()
            ->and($completed->total_cost)->toBe(5000000)
            ->and($completed->total_revenue)->toBe(8000000)
            ->and($completed->gross_profit)->toBe(3000000);
    });

    it('cannot complete draft project', function () {
        $project = Project::factory()->draft()->create();

        $this->service->complete($project);
    })->throws(StateTransitionException::class);

    it('cannot complete planning project', function () {
        $project = Project::factory()->planning()->create();

        $this->service->complete($project);
    })->throws(StateTransitionException::class);

    it('cannot complete on-hold project', function () {
        $project = Project::factory()->onHold()->create();

        $this->service->complete($project);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - updateProgress', function () {
    it('updates progress of in-progress project', function () {
        $project = Project::factory()->inProgress()->create(['progress_percentage' => 20]);

        $updated = $this->service->updateProgress($project, 55.5);

        expect((float) $updated->progress_percentage)->toBe(55.5);
    });

    it('clamps progress to 0-100 range (upper bound)', function () {
        $project = Project::factory()->inProgress()->create();

        $updated = $this->service->updateProgress($project, 150);

        expect((float) $updated->progress_percentage)->toBe(100.0);
    });

    it('clamps progress to 0-100 range (lower bound)', function () {
        $project = Project::factory()->inProgress()->create();

        $updated = $this->service->updateProgress($project, -10);

        expect((float) $updated->progress_percentage)->toBe(0.0);
    });

    it('cannot update progress of draft project', function () {
        $project = Project::factory()->draft()->create();

        $this->service->updateProgress($project, 50);
    })->throws(StateTransitionException::class);

    it('cannot update progress of completed project', function () {
        $project = Project::factory()->completed()->create();

        $this->service->updateProgress($project, 80);
    })->throws(StateTransitionException::class);
});

describe('ProjectService - addCost', function () {
    it('adds material cost to project', function () {
        $project = Project::factory()->planning()->create();

        $data = [
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'description' => 'Cable purchase',
            'cost_date' => now()->toDateString(),
            'quantity' => 100,
            'unit' => 'meter',
            'unit_cost' => 50000,
        ];

        $cost = $this->service->addCost($project, $data);

        expect($cost)
            ->toBeInstanceOf(ProjectCost::class)
            ->project_id->toBe($project->id)
            ->cost_type->toBe(ProjectCost::TYPE_MATERIAL)
            ->description->toBe('Cable purchase')
            ->total_cost->toBe(5000000);
    });

    it('recalculates project financials after adding cost', function () {
        $project = Project::factory()->inProgress()->create();

        $data = [
            'cost_type' => ProjectCost::TYPE_LABOR,
            'description' => 'Electrician',
            'cost_date' => now()->toDateString(),
            'quantity' => 40,
            'unit' => 'jam',
            'unit_cost' => 100000,
        ];

        $this->service->addCost($project, $data);

        $project->refresh();
        expect($project->total_cost)->toBe(4000000);
    });

    it('adds multiple costs and accumulates totals', function () {
        $project = Project::factory()->inProgress()->create();

        $this->service->addCost($project, [
            'cost_type' => ProjectCost::TYPE_MATERIAL,
            'description' => 'Cost 1',
            'cost_date' => now()->toDateString(),
            'quantity' => 1,
            'unit' => 'lot',
            'unit_cost' => 2000000,
        ]);

        $this->service->addCost($project, [
            'cost_type' => ProjectCost::TYPE_LABOR,
            'description' => 'Cost 2',
            'cost_date' => now()->toDateString(),
            'quantity' => 1,
            'unit' => 'lot',
            'unit_cost' => 3000000,
        ]);

        $project->refresh();
        expect($project->total_cost)->toBe(5000000);
    });
});

describe('ProjectService - updateCost', function () {
    it('updates existing cost', function () {
        $project = Project::factory()->inProgress()->create();
        $cost = ProjectCost::factory()->forProject($project)->withAmount(1000000)->create();

        $data = [
            'description' => 'Updated description',
            'quantity' => 5,
            'unit_cost' => 300000,
        ];

        $updated = $this->service->updateCost($cost, $data);

        expect($updated)
            ->description->toBe('Updated description')
            ->total_cost->toBe(1500000);
    });

    it('recalculates project financials after updating cost', function () {
        $project = Project::factory()->inProgress()->create();
        $cost = ProjectCost::factory()->forProject($project)->withAmount(1000000)->create();

        // Manually calculate financials after creating cost
        $project->calculateFinancials();
        $project->save();

        $project->refresh();
        expect($project->total_cost)->toBe(1000000);

        $this->service->updateCost($cost, [
            'quantity' => 1,
            'unit_cost' => 2500000,
        ]);

        $project->refresh();
        expect($project->total_cost)->toBe(2500000);
    });
});

describe('ProjectService - deleteCost', function () {
    it('deletes cost and recalculates project totals', function () {
        $project = Project::factory()->inProgress()->create();
        $cost1 = ProjectCost::factory()->forProject($project)->withAmount(2000000)->create();
        $cost2 = ProjectCost::factory()->forProject($project)->withAmount(3000000)->create();

        // Manually calculate financials after creating costs
        $project->calculateFinancials();
        $project->save();

        $project->refresh();
        expect($project->total_cost)->toBe(5000000);

        $result = $this->service->deleteCost($cost1);

        expect($result)->toBeTrue();
        expect(ProjectCost::find($cost1->id))->toBeNull();

        $project->refresh();
        expect($project->total_cost)->toBe(3000000);
    });
});

describe('ProjectService - addRevenue', function () {
    it('adds milestone revenue to project', function () {
        $project = Project::factory()->inProgress()->create();

        $data = [
            'revenue_type' => ProjectRevenue::TYPE_MILESTONE,
            'description' => 'Phase 1 completion',
            'revenue_date' => now()->toDateString(),
            'amount' => 10000000,
            'milestone_name' => 'Phase 1',
            'milestone_percentage' => 30,
        ];

        $revenue = $this->service->addRevenue($project, $data);

        expect($revenue)
            ->toBeInstanceOf(ProjectRevenue::class)
            ->project_id->toBe($project->id)
            ->revenue_type->toBe(ProjectRevenue::TYPE_MILESTONE)
            ->amount->toBe(10000000)
            ->milestone_name->toBe('Phase 1');

        expect((float) $revenue->milestone_percentage)->toBe(30.0);
    });

    it('recalculates project financials after adding revenue', function () {
        $project = Project::factory()->inProgress()->create();

        ProjectCost::factory()->forProject($project)->withAmount(5000000)->create();

        $this->service->addRevenue($project, [
            'revenue_type' => ProjectRevenue::TYPE_MILESTONE,
            'description' => 'Revenue',
            'revenue_date' => now()->toDateString(),
            'amount' => 8000000,
        ]);

        $project->refresh();
        expect($project->total_revenue)->toBe(8000000)
            ->and($project->gross_profit)->toBe(3000000);
    });

    it('adds multiple revenues and accumulates totals', function () {
        $project = Project::factory()->inProgress()->create();

        $this->service->addRevenue($project, [
            'revenue_type' => ProjectRevenue::TYPE_DOWN_PAYMENT,
            'description' => 'Down payment',
            'revenue_date' => now()->toDateString(),
            'amount' => 5000000,
        ]);

        $this->service->addRevenue($project, [
            'revenue_type' => ProjectRevenue::TYPE_MILESTONE,
            'description' => 'Milestone 1',
            'revenue_date' => now()->toDateString(),
            'amount' => 7000000,
        ]);

        $project->refresh();
        expect($project->total_revenue)->toBe(12000000);
    });
});

describe('ProjectService - financial calculations', function () {
    it('calculates profit margin correctly', function () {
        $project = Project::factory()->inProgress()->create();

        ProjectCost::factory()->forProject($project)->withAmount(8000000)->create();
        ProjectRevenue::factory()->forProject($project)->withAmount(10000000)->create();

        // Manually calculate financials
        $project->calculateFinancials();
        $project->save();

        $project->refresh();
        expect($project->gross_profit)->toBe(2000000)
            ->and((float) $project->profit_margin)->toBe(20.0);
    });

    it('handles zero revenue scenario', function () {
        $project = Project::factory()->inProgress()->create();

        ProjectCost::factory()->forProject($project)->withAmount(5000000)->create();

        // Manually calculate financials
        $project->calculateFinancials();
        $project->save();

        $project->refresh();
        expect($project->total_revenue)->toBe(0)
            ->and($project->gross_profit)->toBe(-5000000)
            ->and((float) $project->profit_margin)->toBe(0.0);
    });

    it('handles negative profit scenario', function () {
        $project = Project::factory()->inProgress()->create();

        ProjectCost::factory()->forProject($project)->withAmount(12000000)->create();
        ProjectRevenue::factory()->forProject($project)->withAmount(10000000)->create();

        // Manually calculate financials
        $project->calculateFinancials();
        $project->save();

        $project->refresh();
        expect($project->gross_profit)->toBe(-2000000)
            ->and((float) $project->profit_margin)->toBe(-20.0);
    });
});

describe('ProjectService - getSummary', function () {
    it('returns complete project summary', function () {
        $project = Project::factory()->inProgress()->create([
            'budget_amount' => 20000000,
            'contract_amount' => 25000000,
            'progress_percentage' => 60,
        ]);

        ProjectCost::factory()->forProject($project)->material()->withAmount(3000000)->create();
        ProjectCost::factory()->forProject($project)->labor()->withAmount(2000000)->create();
        ProjectRevenue::factory()->forProject($project)->withAmount(8000000)->create();

        $project->refresh();

        $summary = $this->service->getSummary($project);

        expect($summary)
            ->toBeArray()
            ->toHaveKeys([
                'project_id',
                'project_number',
                'status',
                'progress',
                'budget',
                'financials',
                'cost_breakdown',
                'timeline',
            ]);

        expect($summary['project_id'])->toBe($project->id);
        expect($summary['status'])->toBe(DocumentStatus::InProgress);
        expect((float) $summary['progress'])->toBe(60.0);

        expect($summary['budget'])
            ->toHaveKeys(['amount', 'utilization', 'variance', 'is_over']);

        expect($summary['financials'])
            ->toHaveKeys(['contract_amount', 'total_cost', 'total_revenue', 'gross_profit', 'profit_margin']);

        expect($summary['cost_breakdown'])->toBeArray();
    });
});

describe('ProjectService - getStatistics', function () {
    it('returns statistics for all projects', function () {
        Project::factory()->draft()->create();
        Project::factory()->planning()->create();
        Project::factory()->inProgress()->create(['budget_amount' => 10000000, 'contract_amount' => 12000000]);
        Project::factory()->completed()->create();
        Project::factory()->cancelled()->create();

        $stats = $this->service->getStatistics();

        expect($stats)
            ->toBeArray()
            ->toHaveKeys([
                'total_count',
                'by_status',
                'by_priority',
                'financials',
                'active_projects',
            ]);

        expect($stats['total_count'])->toBe(5);
        expect($stats['by_status']['draft'])->toBe(1);
        expect($stats['by_status']['planning'])->toBe(1);
        expect($stats['by_status']['in_progress'])->toBe(1);
        expect($stats['by_status']['completed'])->toBe(1);
        expect($stats['by_status']['cancelled'])->toBe(1);
    });

    it('filters statistics by date range', function () {
        $oldDate = now()->subMonths(6);
        $recentDate = now()->subWeek();

        Project::factory()->draft()->create(['start_date' => $oldDate]);
        Project::factory()->inProgress()->create(['start_date' => $recentDate]);
        Project::factory()->planning()->create(['start_date' => $recentDate]);

        $stats = $this->service->getStatistics($recentDate->toDateString(), now()->toDateString());

        expect($stats['total_count'])->toBe(2);
    });

    it('returns financial statistics', function () {
        Project::factory()->inProgress()->create([
            'contract_amount' => 10000000,
            'total_cost' => 6000000,
            'total_revenue' => 8000000,
            'gross_profit' => 2000000,
        ]);

        Project::factory()->completed()->create([
            'contract_amount' => 15000000,
            'total_cost' => 10000000,
            'total_revenue' => 15000000,
            'gross_profit' => 5000000,
        ]);

        $stats = $this->service->getStatistics();

        expect($stats['financials']['total_contract_value'])->toBe(25000000);
        expect($stats['financials']['total_cost'])->toBe(16000000);
        expect($stats['financials']['total_revenue'])->toBe(23000000);
        expect($stats['financials']['total_profit'])->toBe(7000000);
    });

    it('returns active project statistics', function () {
        Project::factory()->inProgress()->overdue()->create(['budget_amount' => 5000000, 'total_cost' => 6000000]);
        Project::factory()->inProgress()->create(['budget_amount' => 10000000, 'total_cost' => 8000000]);
        Project::factory()->completed()->create();

        $stats = $this->service->getStatistics();

        expect($stats['active_projects']['count'])->toBe(2);
        expect($stats['active_projects']['overdue'])->toBe(1);
        expect($stats['active_projects']['over_budget'])->toBe(1);
    });

    it('counts projects by priority', function () {
        Project::factory()->create(['priority' => Project::PRIORITY_LOW]);
        Project::factory()->create(['priority' => Project::PRIORITY_NORMAL]);
        Project::factory()->create(['priority' => Project::PRIORITY_HIGH]);
        Project::factory()->create(['priority' => Project::PRIORITY_URGENT]);

        $stats = $this->service->getStatistics();

        expect($stats['by_priority']['low'])->toBe(1);
        expect($stats['by_priority']['normal'])->toBe(1);
        expect($stats['by_priority']['high'])->toBe(1);
        expect($stats['by_priority']['urgent'])->toBe(1);
    });
});
