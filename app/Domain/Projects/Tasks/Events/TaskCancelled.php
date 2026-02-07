<?php

declare(strict_types=1);

namespace App\Domain\Projects\Tasks\Events;

use Carbon\Carbon;

readonly class TaskCancelled
{
    public function __construct(
        public int $taskId,
        public string $taskNumber,
        public int $projectId,
        public ?int $userId,
        public Carbon $cancelledAt,
        public ?string $reason = null,
    ) {}
}
