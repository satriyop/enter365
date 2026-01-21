<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MrpRuns\Events;

use App\Enums\DocumentStatus;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Base event for MRP Run status changes.
 *
 * Provides a consistent event structure for all MRP run transitions.
 */
class MrpRunStatusChanged
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $mrpRunId,
        public readonly string $runNumber,
        public readonly DocumentStatus $fromStatus,
        public readonly DocumentStatus $toStatus,
        public readonly ?int $userId = null,
        public readonly ?\DateTimeInterface $occurredAt = null
    ) {}
}
