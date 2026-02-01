<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Accounting\FiscalPeriod;
use App\Services\Accounting\FiscalPeriodService;
use App\Support\OperationContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Close a fiscal period in the background.
 *
 * Fiscal period closing processes thousands of journal entries
 * to create closing entries — too heavy for an HTTP request.
 */
class CloseFiscalPeriod implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public readonly FiscalPeriod $period,
        public readonly ?string $notes = null,
        public readonly ?int $userId = null
    ) {}

    public function handle(FiscalPeriodService $service): void
    {
        Log::info("CloseFiscalPeriod: Starting close for period {$this->period->name}");

        $context = OperationContext::forJob($this->userId, 'CloseFiscalPeriod');
        $service = $service->withContext($context);

        $result = $service->closePeriod($this->period, $this->notes);

        if ($result['success']) {
            Log::info("CloseFiscalPeriod: Period {$this->period->name} closed successfully");
        } else {
            Log::error("CloseFiscalPeriod: Failed to close period {$this->period->name}", [
                'message' => $result['message'],
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("CloseFiscalPeriod: Job failed for period {$this->period->name}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
