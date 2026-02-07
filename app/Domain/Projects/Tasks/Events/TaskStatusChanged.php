<?php

declare(strict_types=1);

namespace App\Domain\Projects\Tasks\Events;

use App\Enums\DocumentStatus;

readonly class TaskStatusChanged
{
    public function __construct(
        public int $taskId,
        public DocumentStatus $fromStatus,
        public DocumentStatus $toStatus,
        public ?int $userId
    ) {}
}
