<?php

declare(strict_types=1);

use App\Domain\Projects\Tasks\Events\TaskCancelled;
use App\Domain\Projects\Tasks\Events\TaskCompleted;
use App\Domain\Projects\Tasks\Events\TaskStarted;
use App\Domain\Projects\Tasks\Events\TaskStatusChanged;
use App\Domain\Projects\Tasks\TaskStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->project = Project::factory()->inProgress()->create();
    $this->recorder = new RecordingEventDispatcher;
});

describe('TaskStateMachine - valid transitions', function () {
    it('allows todo to in_progress', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('allows todo to cancelled', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows in_progress to done', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Done))->toBeTrue();
    });

    it('allows in_progress to cancelled', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });
});

describe('TaskStateMachine - invalid transitions', function () {
    it('prevents todo to done (must go through in_progress)', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Done))->toBeFalse();
    });

    it('prevents done to any state (terminal)', function () {
        $task = Task::factory()->forProject($this->project)->done()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Todo))->toBeFalse();
        expect($sm->canTransitionTo(DocumentStatus::InProgress))->toBeFalse();
        expect($sm->canTransitionTo(DocumentStatus::Cancelled))->toBeFalse();
    });

    it('prevents cancelled to any state (terminal)', function () {
        $task = Task::factory()->forProject($this->project)->cancelled()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::Todo))->toBeFalse();
        expect($sm->canTransitionTo(DocumentStatus::InProgress))->toBeFalse();
        expect($sm->canTransitionTo(DocumentStatus::Done))->toBeFalse();
    });

    it('throws on invalid transition execution', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect(fn () => $sm->transitionTo(DocumentStatus::Done))
            ->toThrow(StateTransitionException::class);
    });
});

describe('TaskStateMachine - guards', function () {
    it('blocks start when dependencies are incomplete', function () {
        $blocker = Task::factory()->forProject($this->project)->todo()->create();
        $task = Task::factory()->forProject($this->project)->todo()->create();

        // Add dependency via pivot
        $task->dependencies()->attach($blocker->id, ['dependency_type' => 'finish_to_start']);

        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::InProgress))->toBeFalse();
    });

    it('allows start when dependencies are done', function () {
        $blocker = Task::factory()->forProject($this->project)->done()->create();
        $task = Task::factory()->forProject($this->project)->todo()->create();

        $task->dependencies()->attach($blocker->id, ['dependency_type' => 'finish_to_start']);

        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('blocks completion when subtasks are unfinished', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->todo()->create();

        $sm = TaskStateMachine::fromTask($parent);

        expect($sm->canTransitionTo(DocumentStatus::Done))->toBeFalse();
    });

    it('allows completion when all subtasks are done or cancelled', function () {
        $parent = Task::factory()->forProject($this->project)->inProgress()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->done()->create();
        Task::factory()->forProject($this->project)->asSubtaskOf($parent)->cancelled()->create();

        $sm = TaskStateMachine::fromTask($parent);

        expect($sm->canTransitionTo(DocumentStatus::Done))->toBeTrue();
    });
});

describe('TaskStateMachine - events', function () {
    it('dispatches TaskStarted on start transition', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task, $this->recorder);

        $sm->transitionTo(DocumentStatus::InProgress, ['user_id' => 1]);

        $this->recorder->assertDispatched(TaskStarted::class);
        $this->recorder->assertDispatched(TaskStatusChanged::class);
    });

    it('dispatches TaskCompleted on done transition', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task, $this->recorder);

        $sm->transitionTo(DocumentStatus::Done, ['user_id' => 1]);

        $this->recorder->assertDispatched(TaskCompleted::class);
        $this->recorder->assertDispatched(TaskStatusChanged::class);
    });

    it('dispatches TaskCancelled on cancel transition', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task, $this->recorder);

        $sm->transitionTo(DocumentStatus::Cancelled, ['user_id' => 1, 'cancellation_reason' => 'Scope cut']);

        $this->recorder->assertDispatched(TaskCancelled::class);
        $this->recorder->assertDispatched(TaskStatusChanged::class);
    });
});

describe('TaskStateMachine - helpers', function () {
    it('canStart returns true for todo', function () {
        $task = Task::factory()->forProject($this->project)->todo()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canStart())->toBeTrue();
    });

    it('canStart returns false for in_progress', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canStart())->toBeFalse();
    });

    it('canComplete returns true for in_progress', function () {
        $task = Task::factory()->forProject($this->project)->inProgress()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canComplete())->toBeTrue();
    });

    it('canCancel returns false for done', function () {
        $task = Task::factory()->forProject($this->project)->done()->create();
        $sm = TaskStateMachine::fromTask($task);

        expect($sm->canCancel())->toBeFalse();
    });

    it('canEdit returns true only for todo', function () {
        $todo = Task::factory()->forProject($this->project)->todo()->create();
        $ip = Task::factory()->forProject($this->project)->inProgress()->create();

        expect(TaskStateMachine::fromTask($todo)->canEdit())->toBeTrue();
        expect(TaskStateMachine::fromTask($ip)->canEdit())->toBeFalse();
    });

    it('canDelete returns true only for todo', function () {
        $todo = Task::factory()->forProject($this->project)->todo()->create();
        $done = Task::factory()->forProject($this->project)->done()->create();

        expect(TaskStateMachine::fromTask($todo)->canDelete())->toBeTrue();
        expect(TaskStateMachine::fromTask($done)->canDelete())->toBeFalse();
    });
});
