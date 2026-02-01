<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Manufacturing\MrpRun;
use App\Services\Manufacturing\MrpService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Execute an MRP calculation run in the background.
 *
 * MRP calculates demands, supply, BOM explosion, and suggestions
 * across all products — too compute-intensive for synchronous execution.
 */
class RunMrpCalculation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly MrpRun $run,
        public readonly ?int $userId = null
    ) {}

    public function handle(MrpService $service): void
    {
        Log::info("RunMrpCalculation: Starting MRP run #{$this->run->id}");

        $service->executeRun($this->run, $this->userId);

        Log::info("RunMrpCalculation: MRP run #{$this->run->id} completed");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("RunMrpCalculation: MRP run #{$this->run->id} failed", [
            'error' => $exception->getMessage(),
        ]);
    }
}
