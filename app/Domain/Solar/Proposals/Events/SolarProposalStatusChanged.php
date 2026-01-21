<?php

declare(strict_types=1);

namespace App\Domain\Solar\Proposals\Events;

use App\Enums\DocumentStatus;

readonly class SolarProposalStatusChanged
{
    public function __construct(
        public int $proposalId,
        public DocumentStatus $fromStatus,
        public DocumentStatus $toStatus,
        public ?int $userId = null
    ) {}
}
