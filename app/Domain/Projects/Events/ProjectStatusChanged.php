<?php

declare(strict_types=1);

namespace App\Domain\Projects\Events;

use App\Enums\DocumentStatus;
use App\Models\Projects\Project;

readonly class ProjectStatusChanged
{
    public function __construct(
        public readonly int $projectId,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId
    ) {}

    public static function fromProject(Project $project, DocumentStatus $fromStatus, DocumentStatus $toStatus, ?int $userId = null): self
    {
        return new self(
            projectId: $project->id,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            userId: $userId,
        );
    }
}
