<?php

declare(strict_types=1);

namespace App\Listeners\Projects;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Projects\Events\ProjectCancelled;
use App\Domain\Projects\Events\ProjectCompleted;
use App\Domain\Projects\Events\ProjectOnHold;
use App\Domain\Projects\Events\ProjectResumed;
use App\Domain\Projects\Events\ProjectStarted;
use App\Domain\Projects\Events\ProjectStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all project-related events.
 */
class ProjectEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(ProjectStatusChanged $event): void
    {
        $this->logger->logOperation('project.status_changed', [
            'project_id' => $event->projectId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleStarted(ProjectStarted $event): void
    {
        $this->logger->logOperation('project.started', [
            'project_id' => $event->projectId,
            'project_number' => $event->projectNumber,
            'contact_id' => $event->contactId,
            'started_at' => $event->startedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleOnHold(ProjectOnHold $event): void
    {
        $this->logger->logOperation('project.on_hold', [
            'project_id' => $event->projectId,
            'project_number' => $event->projectNumber,
            'reason' => $event->reason,
            'held_at' => $event->heldAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleResumed(ProjectResumed $event): void
    {
        $this->logger->logOperation('project.resumed', [
            'project_id' => $event->projectId,
            'project_number' => $event->projectNumber,
            'resumed_at' => $event->resumedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(ProjectCompleted $event): void
    {
        $this->logger->logOperation('project.completed', [
            'project_id' => $event->projectId,
            'project_number' => $event->projectNumber,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCancelled(ProjectCancelled $event): void
    {
        $this->logger->logOperation('project.cancelled', [
            'project_id' => $event->projectId,
            'project_number' => $event->projectNumber,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            ProjectStatusChanged::class => 'handleStatusChanged',
            ProjectStarted::class => 'handleStarted',
            ProjectOnHold::class => 'handleOnHold',
            ProjectResumed::class => 'handleResumed',
            ProjectCompleted::class => 'handleCompleted',
            ProjectCancelled::class => 'handleCancelled',
        ];
    }
}
