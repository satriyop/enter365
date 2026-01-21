<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MrpRuns\Events;

use App\Models\Manufacturing\MrpRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an MRP run completes successfully.
 */
class MrpRunCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $mrpRunId,
        public readonly string $runNumber,
        public readonly int $totalDemands,
        public readonly int $totalShortages,
        public readonly int $totalPurchaseSuggestions,
        public readonly int $totalWorkOrderSuggestions,
        public readonly int $totalSubcontractSuggestions,
        public readonly ?int $userId = null,
        public readonly ?\DateTimeInterface $occurredAt = null
    ) {}

    public static function fromMrpRun(MrpRun $run, ?int $userId = null): self
    {
        return new self(
            mrpRunId: $run->id,
            runNumber: $run->run_number,
            totalDemands: $run->total_demands ?? 0,
            totalShortages: $run->total_shortages ?? 0,
            totalPurchaseSuggestions: $run->total_purchase_suggestions ?? 0,
            totalWorkOrderSuggestions: $run->total_work_order_suggestions ?? 0,
            totalSubcontractSuggestions: $run->total_subcontract_suggestions ?? 0,
            userId: $userId,
            occurredAt: now()
        );
    }
}
