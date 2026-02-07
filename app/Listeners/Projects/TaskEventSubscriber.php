<?php

declare(strict_types=1);

namespace App\Listeners\Projects;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Projects\Tasks\Events\TaskCancelled;
use App\Domain\Projects\Tasks\Events\TaskCompleted;
use App\Domain\Projects\Tasks\Events\TaskStarted;
use App\Domain\Projects\Tasks\Events\TaskStatusChanged;
use App\Enums\DocumentStatus;
use App\Models\Projects\Project;
use Illuminate\Events\Dispatcher;

/**
 * Handles all task-related events.
 */
class TaskEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(TaskStatusChanged $event): void
    {
        $this->logger->logOperation('task.status_changed', [
            'task_id' => $event->taskId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleStarted(TaskStarted $event): void
    {
        $this->logger->logOperation('task.started', [
            'task_id' => $event->taskId,
            'task_number' => $event->taskNumber,
            'project_id' => $event->projectId,
            'started_at' => $event->startedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);
    }

    public function handleCompleted(TaskCompleted $event): void
    {
        $this->logger->logOperation('task.completed', [
            'task_id' => $event->taskId,
            'task_number' => $event->taskNumber,
            'project_id' => $event->projectId,
            'completed_at' => $event->completedAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);

        $this->recalculateProjectProgress($event->projectId);
    }

    public function handleCancelled(TaskCancelled $event): void
    {
        $this->logger->logOperation('task.cancelled', [
            'task_id' => $event->taskId,
            'task_number' => $event->taskNumber,
            'project_id' => $event->projectId,
            'reason' => $event->reason,
            'cancelled_at' => $event->cancelledAt->toIso8601String(),
            'user_id' => $event->userId,
        ]);

        $this->recalculateProjectProgress($event->projectId);
    }

    /**
     * Recalculate project progress based on root task completion.
     */
    private function recalculateProjectProgress(int $projectId): void
    {
        $project = Project::find($projectId);
        if (! $project) {
            return;
        }

        $totalRootTasks = $project->rootTasks()->count();
        if ($totalRootTasks === 0) {
            return;
        }

        $doneRootTasks = $project->rootTasks()
            ->whereIn('status', [DocumentStatus::Done->value, DocumentStatus::Cancelled->value])
            ->count();

        $progress = round(($doneRootTasks / $totalRootTasks) * 100, 2);

        $project->update(['progress_percentage' => $progress]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            TaskStatusChanged::class => 'handleStatusChanged',
            TaskStarted::class => 'handleStarted',
            TaskCompleted::class => 'handleCompleted',
            TaskCancelled::class => 'handleCancelled',
        ];
    }
}
