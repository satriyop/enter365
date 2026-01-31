<?php

declare(strict_types=1);

use App\Domain\Projects\Events\ProjectCancelled;
use App\Domain\Projects\Events\ProjectCompleted;
use App\Domain\Projects\Events\ProjectOnHold;
use App\Domain\Projects\Events\ProjectStarted;
use App\Domain\Projects\Events\ProjectStatusChanged;
use App\Domain\Projects\ProjectStateMachine;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Infrastructure\Events\RecordingEventDispatcher;
use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

/*
|--------------------------------------------------------------------------
| ProjectStateMachine Tests
|--------------------------------------------------------------------------
|
| Tests for the project state machine including transitions, event
| dispatching, and business rule helpers.
|
*/

describe('ProjectStateMachine transitions', function () {
    it('allows transition from draft to planning', function () {
        $project = Project::factory()->draft()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Planning))->toBeTrue();
    });

    it('allows transition from draft to in_progress', function () {
        $project = Project::factory()->draft()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('allows transition from draft to cancelled', function () {
        $project = Project::factory()->draft()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from planning to in_progress', function () {
        $project = Project::factory()->planning()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('allows transition from planning to cancelled', function () {
        $project = Project::factory()->planning()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from in_progress to on_hold', function () {
        $project = Project::factory()->inProgress()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::OnHold))->toBeTrue();
    });

    it('allows transition from in_progress to completed', function () {
        $project = Project::factory()->inProgress()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('allows transition from in_progress to cancelled', function () {
        $project = Project::factory()->inProgress()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('allows transition from on_hold to in_progress', function () {
        $project = Project::factory()->onHold()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::InProgress))->toBeTrue();
    });

    it('allows transition from on_hold to completed', function () {
        $project = Project::factory()->onHold()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Completed))->toBeTrue();
    });

    it('allows transition from on_hold to cancelled', function () {
        $project = Project::factory()->onHold()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        expect($stateMachine->canTransitionTo(DocumentStatus::Cancelled))->toBeTrue();
    });

    it('transitions and persists status when valid', function () {
        $user = User::factory()->create();
        $project = Project::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Planning, ['user_id' => $user->id]);

        expect($project->fresh()->status)->toBe(DocumentStatus::Planning);
    });

    it('throws exception on invalid transition', function () {
        $project = Project::factory()->completed()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);

        $stateMachine->transitionTo(DocumentStatus::Draft);
    })->throws(StateTransitionException::class);
});

describe('ProjectStateMachine actions', function () {
    it('updates actual_start_date when transitioning from draft to in_progress', function () {
        $user = User::factory()->create();
        $project = Project::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        $project->refresh();
        expect($project->actual_start_date)->not->toBeNull();
    });

    it('updates actual_start_date when transitioning from planning to in_progress', function () {
        $user = User::factory()->create();
        $project = Project::factory()->planning()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        $project->refresh();
        expect($project->actual_start_date)->not->toBeNull();
    });

    it('does not update actual_start_date when resuming from on_hold', function () {
        $user = User::factory()->create();
        $project = Project::factory()->onHold()->create();

        $existingStartDate = $project->actual_start_date;

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        $project->refresh();
        expect($project->actual_start_date)->toEqual($existingStartDate);
    });

    it('updates actual_end_date and progress when completing', function () {
        $user = User::factory()->create();
        $project = Project::factory()->inProgress()->create(['progress_percentage' => 75]);

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        $project->refresh();
        expect($project->actual_end_date)->not->toBeNull();
        expect((int) $project->progress_percentage)->toBe(100);
    });
});

describe('ProjectStateMachine event dispatching', function () {
    it('dispatches ProjectStatusChanged event when transitioning to planning', function () {
        $user = User::factory()->create();
        $project = Project::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Planning, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(ProjectStatusChanged::class))->toBe(2); // Generic + specific
    });

    it('dispatches ProjectStarted event when starting from draft', function () {
        $user = User::factory()->create();
        $project = Project::factory()->draft()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(ProjectStarted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(ProjectStarted::class);
        expect($event->projectId)->toBe($project->id);
        expect($event->projectNumber)->toBe($project->project_number);
    });

    it('dispatches ProjectStarted event when starting from planning', function () {
        $user = User::factory()->create();
        $project = Project::factory()->planning()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::InProgress, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(ProjectStarted::class))->toBe(1);
    });

    it('dispatches ProjectOnHold event when putting on hold', function () {
        $user = User::factory()->create();
        $project = Project::factory()->inProgress()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::OnHold, [
            'user_id' => $user->id,
            'hold_reason' => 'Waiting for materials',
        ]);

        expect($eventDispatcher->dispatchCount(ProjectOnHold::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(ProjectOnHold::class);
        expect($event->projectId)->toBe($project->id);
        expect($event->reason)->toBe('Waiting for materials');
    });

    it('dispatches ProjectCompleted event when completing', function () {
        $user = User::factory()->create();
        $project = Project::factory()->inProgress()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Completed, ['user_id' => $user->id]);

        expect($eventDispatcher->dispatchCount(ProjectCompleted::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(ProjectCompleted::class);
        expect($event->projectId)->toBe($project->id);
    });

    it('dispatches ProjectCancelled event when cancelling', function () {
        $user = User::factory()->create();
        $project = Project::factory()->inProgress()->create();

        $eventDispatcher = new RecordingEventDispatcher;
        $stateMachine = ProjectStateMachine::fromProject($project, $eventDispatcher);

        $stateMachine->transitionTo(DocumentStatus::Cancelled, [
            'user_id' => $user->id,
            'cancellation_reason' => 'Client request',
        ]);

        expect($eventDispatcher->dispatchCount(ProjectCancelled::class))->toBe(1);

        $event = $eventDispatcher->getFirstEvent(ProjectCancelled::class);
        expect($event->projectId)->toBe($project->id);
        expect($event->reason)->toBe('Client request');
    });
});

describe('ProjectStateMachine workflow metadata', function () {
    it('provides correct workflow metadata for draft project', function () {
        $project = Project::factory()->draft()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Draft->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Planning->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for planning project', function () {
        $project = Project::factory()->planning()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Planning->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for in_progress project', function () {
        $project = Project::factory()->inProgress()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::OnHold->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Completed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides correct workflow metadata for on_hold project', function () {
        $project = Project::factory()->onHold()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::OnHold->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::InProgress->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Completed->value);
        expect(array_column($metadata['next_statuses'], 'value'))->toContain(DocumentStatus::Cancelled->value);
    });

    it('provides empty transitions for completed project', function () {
        $project = Project::factory()->completed()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata['current_status']['value'])->toBe(DocumentStatus::Completed->value);
        expect($metadata['next_statuses'])->toBeEmpty();
    });

    it('includes all workflow elements in metadata', function () {
        $project = Project::factory()->draft()->create();

        $stateMachine = ProjectStateMachine::fromProject($project);
        $metadata = $stateMachine->getWorkflowMetadata();

        expect($metadata)->toHaveKey('current_status');
        expect($metadata)->toHaveKey('next_statuses');
        expect($metadata)->toHaveKey('all_statuses');
        expect($metadata)->toHaveKey('transitions');
        expect($metadata['current_status'])->toHaveKey('value');
        expect($metadata['current_status'])->toHaveKey('label');
        expect($metadata['current_status'])->toHaveKey('color');
    });
});

describe('ProjectStateMachine business helpers', function () {
    it('reports canStart correctly', function () {
        $draft = Project::factory()->draft()->create();
        $planning = Project::factory()->planning()->create();
        $inProgress = Project::factory()->inProgress()->create();

        expect(ProjectStateMachine::fromProject($draft)->canStart())->toBeTrue();
        expect(ProjectStateMachine::fromProject($planning)->canStart())->toBeTrue();
        expect(ProjectStateMachine::fromProject($inProgress)->canStart())->toBeFalse();
    });

    it('reports canPutOnHold correctly', function () {
        $inProgress = Project::factory()->inProgress()->create();
        $draft = Project::factory()->draft()->create();

        expect(ProjectStateMachine::fromProject($inProgress)->canPutOnHold())->toBeTrue();
        expect(ProjectStateMachine::fromProject($draft)->canPutOnHold())->toBeFalse();
    });

    it('reports canResume correctly', function () {
        $onHold = Project::factory()->onHold()->create();
        $inProgress = Project::factory()->inProgress()->create();

        expect(ProjectStateMachine::fromProject($onHold)->canResume())->toBeTrue();
        expect(ProjectStateMachine::fromProject($inProgress)->canResume())->toBeFalse();
    });

    it('reports canComplete correctly', function () {
        $inProgress = Project::factory()->inProgress()->create();
        $planning = Project::factory()->planning()->create();

        expect(ProjectStateMachine::fromProject($inProgress)->canComplete())->toBeTrue();
        expect(ProjectStateMachine::fromProject($planning)->canComplete())->toBeFalse();
    });

    it('reports canCancel correctly for non-terminal statuses', function () {
        $draft = Project::factory()->draft()->create();
        $planning = Project::factory()->planning()->create();
        $inProgress = Project::factory()->inProgress()->create();
        $onHold = Project::factory()->onHold()->create();
        $completed = Project::factory()->completed()->create();
        $cancelled = Project::factory()->cancelled()->create();

        expect(ProjectStateMachine::fromProject($draft)->canCancel())->toBeTrue();
        expect(ProjectStateMachine::fromProject($planning)->canCancel())->toBeTrue();
        expect(ProjectStateMachine::fromProject($inProgress)->canCancel())->toBeTrue();
        expect(ProjectStateMachine::fromProject($onHold)->canCancel())->toBeTrue();
        expect(ProjectStateMachine::fromProject($completed)->canCancel())->toBeFalse();
        expect(ProjectStateMachine::fromProject($cancelled)->canCancel())->toBeFalse();
    });

    it('reports canEdit correctly', function () {
        $draft = Project::factory()->draft()->create();
        $planning = Project::factory()->planning()->create();
        $inProgress = Project::factory()->inProgress()->create();

        expect(ProjectStateMachine::fromProject($draft)->canEdit())->toBeTrue();
        expect(ProjectStateMachine::fromProject($planning)->canEdit())->toBeTrue();
        expect(ProjectStateMachine::fromProject($inProgress)->canEdit())->toBeFalse();
    });

    it('reports canDelete correctly', function () {
        $draft = Project::factory()->draft()->create();
        $planning = Project::factory()->planning()->create();

        expect(ProjectStateMachine::fromProject($draft)->canDelete())->toBeTrue();
        expect(ProjectStateMachine::fromProject($planning)->canDelete())->toBeFalse();
    });
});
