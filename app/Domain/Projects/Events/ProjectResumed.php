<?php

declare(strict_types=1);

namespace App\Domain\Projects\Events;

use App\Models\Projects\Project;

readonly class ProjectResumed
{
    public function __construct(
        public readonly int $projectId,
        public readonly string $projectNumber,
        public readonly int $contactId,
        public readonly ?int $userId,
        public readonly \Carbon\Carbon $resumedAt,
    ) {}

    public static function fromProject(Project $project, ?int $userId = null): self
    {
        return new self(
            projectId: $project->id,
            projectNumber: $project->project_number,
            contactId: $project->contact_id,
            userId: $userId,
            resumedAt: now(),
        );
    }
}
