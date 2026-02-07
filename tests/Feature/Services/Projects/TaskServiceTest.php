<?php

declare(strict_types=1);

use App\Contracts\Projects\TaskServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->service = app(TaskServiceInterface::class);
    $this->user = User::first();
    $this->project = Project::factory()->inProgress()->create();
});

describe('TaskService - create', function () {
    it('creates a task for a project', function () {
        $task = $this->service->create($this->project, [
            'title' => 'Design Phase',
            'description' => 'Create wireframes',
            'priority' => 'high',
        ]);

        expect($task)
            ->toBeInstanceOf(Task::class)
            ->title->toBe('Design Phase')
            ->description->toBe('Create wireframes')
            ->priority->toBe('high')
            ->status->toBe(DocumentStatus::Todo)
            ->project_id->toBe($this->project->id)
            ->task_number->toContain('TSK-');
    });

    it('creates a task with assigned user', function () {
        $assignee = User::factory()->create();

        $task = $this->service->create($this->project, [
            'title' => 'Assigned Task',
            'assigned_to' => $assignee->id,
        ]);

        expect($task->assigned_to)->toBe($assignee->id);
    });

    it('creates a task with dates and estimate', function () {
        $task = $this->service->create($this->project, [
            'title' => 'Timed Task',
            'start_date' => '2026-03-01',
            'due_date' => '2026-03-15',
            'estimated_hours' => 24.5,
        ]);

        expect($task)
            ->start_date->toBeInstanceOf(\Carbon\Carbon::class)
            ->due_date->toBeInstanceOf(\Carbon\Carbon::class)
            ->estimated_hours->toBe('24.50');
    });
});

describe('TaskService - addSubtask', function () {
    it('creates a subtask under a parent task', function () {
        $parent = Task::factory()->forProject($this->project)->create();

        $subtask = $this->service->addSubtask($parent, [
            'title' => 'Sub-task 1',
        ]);

        expect($subtask)
            ->parent_id->toBe($parent->id)
            ->project_id->toBe($this->project->id)
            ->isSubtask()->toBeTrue();
    });

    it('enforces one-level depth (no grandchildren)', function () {
        $parent = Task::factory()->forProject($this->project)->create();
        $subtask = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->create();

        expect(fn () => $this->service->addSubtask($subtask, ['title' => 'Grandchild']))
            ->toThrow(BusinessRuleException::class, 'Sub-tugas tidak dapat memiliki sub-tugas sendiri');
    });
});

describe('TaskService - update', function () {
    it('updates a todo task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $updated = $this->service->update($task, [
            'title' => 'Updated Title',
            'priority' => 'urgent',
        ]);

        expect($updated)
            ->title->toBe('Updated Title')
            ->priority->toBe('urgent');
    });

    it('prevents updating a non-todo task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        expect(fn () => $this->service->update($task, ['title' => 'New Title']))
            ->toThrow(DocumentLockedException::class);
    });
});

describe('TaskService - delete', function () {
    it('deletes a todo task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $result = $this->service->delete($task);

        expect($result)->toBeTrue();
        expect(Task::find($task->id))->toBeNull();
    });

    it('prevents deleting a non-todo task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        expect(fn () => $this->service->delete($task))
            ->toThrow(DocumentLockedException::class);
    });

    it('cascade deletes subtasks', function () {
        $parent = Task::factory()->forProject($this->project)->todo()->create();
        $sub1 = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->create();
        $sub2 = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->create();

        $this->service->delete($parent);

        expect(Task::find($parent->id))->toBeNull();
        expect(Task::find($sub1->id))->toBeNull();
        expect(Task::find($sub2->id))->toBeNull();
    });
});

describe('TaskService - start', function () {
    it('transitions a todo task to in_progress', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $started = $this->service->start($task, $this->user->id);

        expect($started->status)->toBe(DocumentStatus::InProgress);
    });

    it('sets start_date when starting if null', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create([
            'start_date' => null,
        ]);

        $started = $this->service->start($task, $this->user->id);

        expect($started->start_date)->not->toBeNull();
    });

    it('prevents starting a task with unfinished dependencies', function () {
        $blocker = Task::factory()->forProject($this->project)->todo()->create();
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $this->service->addDependency($task, $blocker);

        expect(fn () => $this->service->start($task, $this->user->id))
            ->toThrow(StateTransitionException::class);
    });

    it('allows starting when dependencies are done', function () {
        $blocker = Task::factory()->forProject($this->project)->done()->create();
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $this->service->addDependency($task, $blocker);

        $started = $this->service->start($task, $this->user->id);

        expect($started->status)->toBe(DocumentStatus::InProgress);
    });
});

describe('TaskService - complete', function () {
    it('transitions an in_progress task to done', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        $completed = $this->service->complete($task, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Done);
        expect($completed->completed_at)->not->toBeNull();
    });

    it('prevents completing a task with unfinished subtasks', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->todo()->create();

        expect(fn () => $this->service->complete($parent, $this->user->id))
            ->toThrow(StateTransitionException::class);
    });

    it('allows completing when all subtasks are done or cancelled', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->done()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->cancelled()->create();

        $completed = $this->service->complete($parent, $this->user->id);

        expect($completed->status)->toBe(DocumentStatus::Done);
    });

    it('prevents completing a todo task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        expect(fn () => $this->service->complete($task, $this->user->id))
            ->toThrow(StateTransitionException::class);
    });
});

describe('TaskService - cancel', function () {
    it('cancels a todo task', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $cancelled = $this->service->cancel($task, 'Not needed', $this->user->id);

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cancels an in_progress task', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();

        $cancelled = $this->service->cancel($task, 'Scope changed', $this->user->id);

        expect($cancelled->status)->toBe(DocumentStatus::Cancelled);
    });

    it('cascade cancels open subtasks', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        $openSub = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->todo()->create();
        $doneSub = Task::factory()->forProject($this->project)->asSubtaskOf($parent)->done()->create();

        $this->service->cancel($parent, 'Project cancelled', $this->user->id);

        expect($openSub->fresh()->status)->toBe(DocumentStatus::Cancelled);
        expect($doneSub->fresh()->status)->toBe(DocumentStatus::Done); // Done stays done
    });

    it('prevents cancelling an already done task', function () {
        $task = Task::factory()->forProject($this->project)->done()->create();

        expect(fn () => $this->service->cancel($task, 'Oops', $this->user->id))
            ->toThrow(StateTransitionException::class);
    });
});

describe('TaskService - dependencies', function () {
    it('adds a dependency between tasks', function () {
        $task = Task::factory()->forProject($this->project)->create();
        $dep = Task::factory()->forProject($this->project)->create();

        $this->service->addDependency($task, $dep);

        expect($task->dependencies()->count())->toBe(1);
        expect($task->dependencies->first()->id)->toBe($dep->id);
    });

    it('removes a dependency', function () {
        $task = Task::factory()->forProject($this->project)->create();
        $dep = Task::factory()->forProject($this->project)->create();

        $this->service->addDependency($task, $dep);
        $this->service->removeDependency($task, $dep);

        expect($task->dependencies()->count())->toBe(0);
    });

    it('prevents self-dependency', function () {
        $task = Task::factory()->forProject($this->project)->create();

        expect(fn () => $this->service->addDependency($task, $task))
            ->toThrow(BusinessRuleException::class, 'dirinya sendiri');
    });

    it('prevents cross-project dependency', function () {
        $otherProject = Project::factory()->create();
        $task = Task::factory()->forProject($this->project)->create();
        $dep = Task::factory()->forProject($otherProject)->create();

        expect(fn () => $this->service->addDependency($task, $dep))
            ->toThrow(BusinessRuleException::class, 'proyek yang sama');
    });

    it('prevents circular dependency', function () {
        $taskA = Task::factory()->forProject($this->project)->create();
        $taskB = Task::factory()->forProject($this->project)->create();

        $this->service->addDependency($taskA, $taskB);

        expect(fn () => $this->service->addDependency($taskB, $taskA))
            ->toThrow(BusinessRuleException::class, 'circular');
    });

    it('prevents transitive circular dependency', function () {
        $taskA = Task::factory()->forProject($this->project)->create();
        $taskB = Task::factory()->forProject($this->project)->create();
        $taskC = Task::factory()->forProject($this->project)->create();

        $this->service->addDependency($taskA, $taskB); // A depends on B
        $this->service->addDependency($taskB, $taskC); // B depends on C

        expect(fn () => $this->service->addDependency($taskC, $taskA))
            ->toThrow(BusinessRuleException::class, 'circular');
    });
});

describe('TaskService - reorder', function () {
    it('reorders tasks within a project', function () {
        $task1 = Task::factory()->forProject($this->project)->create(['sort_order' => 0]);
        $task2 = Task::factory()->forProject($this->project)->create(['sort_order' => 1]);
        $task3 = Task::factory()->forProject($this->project)->create(['sort_order' => 2]);

        $this->service->reorder($this->project, [$task3->id, $task1->id, $task2->id]);

        expect($task3->fresh()->sort_order)->toBe(0);
        expect($task1->fresh()->sort_order)->toBe(1);
        expect($task2->fresh()->sort_order)->toBe(2);
    });
});

describe('TaskService - getTaskTree', function () {
    it('returns root tasks with subtasks', function () {
        $root1 = Task::factory()->forProject($this->project)->create(['sort_order' => 0]);
        $root2 = Task::factory()->forProject($this->project)->create(['sort_order' => 1]);
        Task::factory()->forProject($this->project)->asSubtaskOf($root1)->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($root1)->create();

        $tree = $this->service->getTaskTree($this->project);

        expect($tree)->toHaveCount(2);
        expect($tree->first()->subtasks)->toHaveCount(2);
    });
});

describe('TaskService - getTaskStatistics', function () {
    it('returns statistics by status', function () {
        Task::factory()->forProject($this->project)->todo()->count(3)->create();
        Task::factory()->forProject($this->project)->inProgress()->count(2)->create();
        Task::factory()->forProject($this->project)->done()->count(1)->create();

        $stats = $this->service->getTaskStatistics($this->project);

        expect($stats['total'])->toBe(6);
        expect($stats['by_status']['todo'])->toBe(3);
        expect($stats['by_status']['in_progress'])->toBe(2);
        expect($stats['by_status']['done'])->toBe(1);
    });

    it('counts overdue tasks', function () {
        Task::factory()->forProject($this->project)->overdue()->create();
        Task::factory()->forProject($this->project)->todo()->create();

        $stats = $this->service->getTaskStatistics($this->project);

        expect($stats['overdue_count'])->toBe(1);
    });
});
