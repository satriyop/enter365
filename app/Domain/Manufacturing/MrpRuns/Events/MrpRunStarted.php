<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MrpRuns\Events;

use App\Models\Manufacturing\MrpRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an MRP run starts execution.
 */
class MrpRunStarted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $mrpRunId,
        public readonly string $runNumber,
        public readonly ?int $warehouseId,
        public readonly \DateTimeInterface $planningStart,
        public readonly \DateTimeInterface $planningEnd,
        public readonly ?int $userId = null,
        public readonly ?\DateTimeInterface $occurredAt = null
    ) {}

    public static function fromMrpRun(MrpRun $run, ?int $userId = null): self
    {
        return new self(
            mrpRunId: $run->id,
            runNumber: $run->run_number,
            warehouseId: $run->warehouse_id,
            planningStart: $run->planning_horizon_start,
            planningEnd: $run->planning_horizon_end,
            userId: $userId,
            occurredAt: now()
        );
    }
}
