<?php

use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->project = Project::factory()->inProgress()->create();
});

describe('Task CRUD', function () {

    it('can list tasks for a project', function () {
        Task::factory()->forProject($this->project)->count(5)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks");

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'task_number',
                        'title',
                        'status' => ['value', 'label', 'color', 'is_terminal', 'is_editable'],
                        'priority',
                        'is_overdue',
                        'is_subtask',
                    ],
                ],
            ]);
    });

    it('can filter tasks by status', function () {
        Task::factory()->forProject($this->project)->todo()->count(3)->create();
        Task::factory()->forProject($this->project)->inProgress()->count(2)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks?status=todo");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter tasks by priority', function () {
        Task::factory()->forProject($this->project)->create(['priority' => 'normal']);
        Task::factory()->forProject($this->project)->highPriority()->count(2)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks?priority=high");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter tasks by assigned user', function () {
        $assignee = User::factory()->create();
        Task::factory()->forProject($this->project)->assignedTo($assignee)->count(2)->create();
        Task::factory()->forProject($this->project)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks?assigned_to={$assignee->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter root tasks only', function () {
        $root = Task::factory()->forProject($this->project)->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($root)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks?parent_id=null");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a task', function () {
        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'title' => 'New Task',
            'description' => 'Task description',
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'New Task')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.status.value', 'todo');
    });

    it('validates required title', function () {
        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks", [
            'priority' => 'high',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    });

    it('can show a specific task', function () {
        $task = Task::factory()->forProject($this->project)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.task_number', $task->task_number);
    });

    it('can update a task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $response = $this->putJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Updated Title',
            'priority' => 'urgent',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Updated Title')
            ->assertJsonPath('data.priority', 'urgent');
    });

    it('cannot update an in-progress task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        $response = $this->putJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}", [
            'title' => 'Updated',
        ]);

        $response->assertStatus(422);
    });

    it('can delete a todo task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $response = $this->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Tugas berhasil dihapus.');
    });

    it('cannot delete an in-progress task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        $response = $this->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}");

        $response->assertStatus(422);
    });
});

describe('Task Actions', function () {

    it('can start a task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/start");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'in_progress');
    });

    it('can complete a task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'done');
    });

    it('cannot complete a task with unfinished subtasks', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->todo()->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$parent->id}/complete");

        $response->assertStatus(422);
    });

    it('can cancel a task with reason', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/cancel", [
            'reason' => 'Not needed anymore',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });
});

describe('Subtasks', function () {

    it('can add a subtask', function () {
        $parent = Task::factory()->forProject($this->project)->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$parent->id}/subtasks", [
            'title' => 'Sub-task 1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id)
            ->assertJsonPath('data.is_subtask', true);
    });

    it('cannot add subtask to a subtask', function () {
        $parent = Task::factory()->forProject($this->project)->create();
        $subtask = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$subtask->id}/subtasks", [
            'title' => 'Grandchild',
        ]);

        $response->assertStatus(409);
    });
});

describe('Dependencies', function () {

    it('can add a dependency', function () {
        $task = Task::factory()->forProject($this->project)->create();
        $dep = Task::factory()->forProject($this->project)->create();

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/dependencies", [
            'dependency_id' => $dep->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Dependensi berhasil ditambahkan.');
    });

    it('can remove a dependency', function () {
        $task = Task::factory()->forProject($this->project)->create();
        $dep = Task::factory()->forProject($this->project)->create();
        $task->dependencies()->attach($dep->id, ['dependency_type' => 'finish_to_start']);

        $response = $this->deleteJson("/api/v1/projects/{$this->project->id}/tasks/{$task->id}/dependencies/{$dep->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Dependensi berhasil dihapus.');
    });

    it('prevents circular dependency', function () {
        $taskA = Task::factory()->forProject($this->project)->create();
        $taskB = Task::factory()->forProject($this->project)->create();

        $taskA->dependencies()->attach($taskB->id, ['dependency_type' => 'finish_to_start']);

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/{$taskB->id}/dependencies", [
            'dependency_id' => $taskA->id,
        ]);

        $response->assertStatus(409);
    });
});

describe('Reorder', function () {

    it('can reorder tasks', function () {
        $task1 = Task::factory()->forProject($this->project)->create(['sort_order' => 0]);
        $task2 = Task::factory()->forProject($this->project)->create(['sort_order' => 1]);
        $task3 = Task::factory()->forProject($this->project)->create(['sort_order' => 2]);

        $response = $this->postJson("/api/v1/projects/{$this->project->id}/tasks/reorder", [
            'task_ids' => [$task3->id, $task1->id, $task2->id],
        ]);

        $response->assertOk();
        expect($task3->fresh()->sort_order)->toBe(0);
        expect($task1->fresh()->sort_order)->toBe(1);
        expect($task2->fresh()->sort_order)->toBe(2);
    });
});

describe('Statistics', function () {

    it('returns task statistics', function () {
        Task::factory()->forProject($this->project)->todo()->count(3)->create();
        Task::factory()->forProject($this->project)->inProgress()->count(2)->create();
        Task::factory()->forProject($this->project)->done()->count(1)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks-statistics");

        $response->assertOk()
            ->assertJsonPath('data.total', 6)
            ->assertJsonPath('data.by_status.todo', 3)
            ->assertJsonPath('data.by_status.in_progress', 2)
            ->assertJsonPath('data.by_status.done', 1);
    });
});

describe('Search and includes', function () {

    it('can search tasks by title', function () {
        Task::factory()->forProject($this->project)->create(['title' => 'Design Phase']);
        Task::factory()->forProject($this->project)->create(['title' => 'Development Sprint']);
        Task::factory()->forProject($this->project)->create(['title' => 'QA Testing']);

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks?search=Design");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can include subtasks', function () {
        $root = Task::factory()->forProject($this->project)->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($root)->count(2)->create();

        $response = $this->getJson("/api/v1/projects/{$this->project->id}/tasks/{$root->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.subtasks');
    });
});
