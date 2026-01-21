<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MrpRuns\Events;

use App\Models\Manufacturing\MrpRun;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when an MRP run fails during execution.
 */
class MrpRunFailed
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int $mrpRunId,
        public readonly string $runNumber,
        public readonly string $errorMessage,
        public readonly ?string $errorClass = null,
        public readonly ?int $userId = null,
        public readonly ?\DateTimeInterface $occurredAt = null
    ) {}

    public static function fromMrpRun(MrpRun $run, \Throwable $exception, ?int $userId = null): self
    {
        return new self(
            mrpRunId: $run->id,
            runNumber: $run->run_number,
            errorMessage: $exception->getMessage(),
            errorClass: $exception::class,
            userId: $userId,
            occurredAt: now()
        );
    }
}
