<?php

use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = authenticatedAdmin();
});

describe('Project task workspace (#154)', function () {
    it('lists all tasks across projects and filters by project', function () {
        $alpha = Project::factory()->create(['name' => 'Alpha']);
        $beta = Project::factory()->create(['name' => 'Beta']);
        Task::factory()->forProject($alpha)->create(['title' => 'Alpha task']);
        Task::factory()->forProject($beta)->create(['title' => 'Beta task']);

        $all = $this->getJson('/api/v1/tasks');
        $all->assertOk();
        expect($all->json('meta.total'))->toBeGreaterThanOrEqual(2);

        $filtered = $this->getJson('/api/v1/tasks?project_id='.$alpha->id);
        $filtered->assertOk()
            ->assertJsonPath('data.0.project_id', $alpha->id);
        expect($filtered->json('meta.total'))->toBe(1);
    });

    it('lists only tasks assigned to the current user', function () {
        $other = User::factory()->create();
        $project = Project::factory()->create();
        Task::factory()->forProject($project)->assignedTo($this->user)->create(['title' => 'Mine']);
        Task::factory()->forProject($project)->assignedTo($other)->create(['title' => 'Theirs']);
        Task::factory()->forProject($project)->create(['title' => 'Unassigned']);

        $response = $this->getJson('/api/v1/tasks/my');
        $response->assertOk();
        expect($response->json('meta.total'))->toBe(1)
            ->and($response->json('data.0.title'))->toBe('Mine')
            ->and($response->json('data.0.assigned_to'))->toBe($this->user->id);
    });

    it('returns tasks analysis grouped by status project priority and assignee', function () {
        $project = Project::factory()->create();
        Task::factory()->forProject($project)->todo()->create();
        Task::factory()->forProject($project)->done()->create();
        Task::factory()->forProject($project)->overdue()->assignedTo($this->user)->create();

        $response = $this->getJson('/api/v1/reports/tasks-analysis');
        $response->assertOk()
            ->assertJsonPath('data.report_name', 'Tasks Analysis')
            ->assertJsonStructure([
                'data' => [
                    'report_name',
                    'totals' => ['count', 'done', 'overdue', 'completion_rate'],
                    'by_status',
                    'by_priority',
                    'by_project',
                    'by_assignee',
                ],
            ]);

        expect($response->json('data.totals.count'))->toBeGreaterThanOrEqual(3)
            ->and($response->json('data.totals.overdue'))->toBeGreaterThanOrEqual(1);
    });
});
