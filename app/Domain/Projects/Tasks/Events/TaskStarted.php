<?php

declare(strict_types=1);

namespace App\Domain\Projects\Tasks\Events;

use Carbon\Carbon;

readonly class TaskStarted
{
    public function __construct(
        public int $taskId,
        public string $taskNumber,
        public int $projectId,
        public ?int $userId,
        public Carbon $startedAt,
    ) {}
}
