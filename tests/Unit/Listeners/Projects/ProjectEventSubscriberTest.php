<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Projects\Events\ProjectCancelled;
use App\Domain\Projects\Events\ProjectCompleted;
use App\Domain\Projects\Events\ProjectOnHold;
use App\Domain\Projects\Events\ProjectResumed;
use App\Domain\Projects\Events\ProjectStarted;
use App\Domain\Projects\Events\ProjectStatusChanged;
use App\Enums\DocumentStatus;
use App\Listeners\Projects\ProjectEventSubscriber;
use Carbon\Carbon;
use Illuminate\Events\Dispatcher;

describe('ProjectEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new ProjectEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all project events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(ProjectStatusChanged::class)
                ->and($subscriptions)->toHaveKey(ProjectStarted::class)
                ->and($subscriptions)->toHaveKey(ProjectOnHold::class)
                ->and($subscriptions)->toHaveKey(ProjectResumed::class)
                ->and($subscriptions)->toHaveKey(ProjectCompleted::class)
                ->and($subscriptions)->toHaveKey(ProjectCancelled::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new ProjectStatusChanged(
                projectId: 1,
                fromStatus: DocumentStatus::Draft,
                toStatus: DocumentStatus::InProgress,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.status_changed', [
                    'project_id' => 1,
                    'from' => 'draft',
                    'to' => 'in_progress',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleStarted', function () {
        it('logs start with correct context', function () {
            $startedAt = Carbon::parse('2025-01-15 08:00:00');

            $event = new ProjectStarted(
                projectId: 1,
                projectNumber: 'PRJ-2025-0001',
                contactId: 5,
                userId: 10,
                startedAt: $startedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.started', [
                    'project_id' => 1,
                    'project_number' => 'PRJ-2025-0001',
                    'contact_id' => 5,
                    'started_at' => $startedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStarted($event);
        });
    });

    describe('handleOnHold', function () {
        it('logs hold with correct context', function () {
            $heldAt = Carbon::parse('2025-01-20 14:00:00');

            $event = new ProjectOnHold(
                projectId: 1,
                projectNumber: 'PRJ-2025-0001',
                contactId: 5,
                userId: 10,
                heldAt: $heldAt,
                reason: 'Waiting for material delivery',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.on_hold', [
                    'project_id' => 1,
                    'project_number' => 'PRJ-2025-0001',
                    'reason' => 'Waiting for material delivery',
                    'held_at' => $heldAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleOnHold($event);
        });
    });

    describe('handleResumed', function () {
        it('logs resume with correct context', function () {
            $resumedAt = Carbon::parse('2025-01-25 09:00:00');

            $event = new ProjectResumed(
                projectId: 1,
                projectNumber: 'PRJ-2025-0001',
                contactId: 5,
                userId: 10,
                resumedAt: $resumedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.resumed', [
                    'project_id' => 1,
                    'project_number' => 'PRJ-2025-0001',
                    'resumed_at' => $resumedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleResumed($event);
        });
    });

    describe('handleCompleted', function () {
        it('logs completion with correct context', function () {
            $completedAt = Carbon::parse('2025-02-15 16:00:00');

            $event = new ProjectCompleted(
                projectId: 1,
                projectNumber: 'PRJ-2025-0001',
                contactId: 5,
                userId: 10,
                completedAt: $completedAt,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.completed', [
                    'project_id' => 1,
                    'project_number' => 'PRJ-2025-0001',
                    'completed_at' => $completedAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCompleted($event);
        });
    });

    describe('handleCancelled', function () {
        it('logs cancellation with correct context', function () {
            $cancelledAt = Carbon::parse('2025-01-30 11:00:00');

            $event = new ProjectCancelled(
                projectId: 1,
                projectNumber: 'PRJ-2025-0001',
                contactId: 5,
                userId: 10,
                cancelledAt: $cancelledAt,
                reason: 'Project scope no longer valid',
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('project.cancelled', [
                    'project_id' => 1,
                    'project_number' => 'PRJ-2025-0001',
                    'reason' => 'Project scope no longer valid',
                    'cancelled_at' => $cancelledAt->toIso8601String(),
                    'user_id' => 10,
                ]);

            $this->subscriber->handleCancelled($event);
        });
    });
});
