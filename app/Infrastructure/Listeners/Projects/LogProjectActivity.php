<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Projects;

use App\Domain\Projects\Events\ProjectCancelled;
use App\Domain\Projects\Events\ProjectCompleted;
use App\Domain\Projects\Events\ProjectOnHold;
use App\Domain\Projects\Events\ProjectResumed;
use App\Domain\Projects\Events\ProjectStarted;
use App\Domain\Projects\Events\ProjectStatusChanged;
use Illuminate\Support\Facades\Log;

class LogProjectActivity
{
    public function handle(
        ProjectStatusChanged|ProjectStarted|ProjectOnHold|
        ProjectResumed|ProjectCancelled|ProjectCompleted $event
    ): void {
        if ($event instanceof ProjectStatusChanged) {
            Log::info('Project status changed', [
                'project_id' => $event->projectId,
                'from_status' => $event->fromStatus->value,
                'to_status' => $event->toStatus->value,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof ProjectStarted) {
            Log::info('Project started', [
                'project_id' => $event->projectId,
                'started_at' => $event->startedAt,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof ProjectOnHold) {
            Log::info('Project put on hold', [
                'project_id' => $event->projectId,
                'held_at' => $event->heldAt,
                'reason' => $event->reason,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof ProjectResumed) {
            Log::info('Project resumed', [
                'project_id' => $event->projectId,
                'resumed_at' => $event->resumedAt,
                'user_id' => $event->userId,
            ]);

            return;
        }

        if ($event instanceof ProjectCompleted) {
            Log::info('Project completed', [
                'project_id' => $event->projectId,
                'completed_at' => $event->completedAt,
                'user_id' => $event->userId,
            ]);

            return;
        }

        // Last branch: must be ProjectCancelled
        Log::info('Project cancelled', [
            'project_id' => $event->projectId,
            'cancelled_at' => $event->cancelledAt,
            'reason' => $event->reason,
            'user_id' => $event->userId,
        ]);
    }
}
