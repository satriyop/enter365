<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Manufacturing\MrpServiceInterface;
use App\Domain\Manufacturing\MrpRuns\Events\MrpRunCompleted;
use App\Domain\Manufacturing\MrpRuns\Events\MrpRunFailed;
use App\Domain\Manufacturing\MrpRuns\Events\MrpRunStarted;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\MrpRun;
use App\Services\Base\AbstractApplicationService;
use InvalidArgumentException;

/**
 * Service for MRP run lifecycle management.
 *
 * Orchestrates MRP runs by coordinating demand collection (MrpDemandService)
 * and suggestion generation (MrpSuggestionService).
 */
class MrpService extends AbstractApplicationService implements MrpServiceInterface
{
    public function __construct(
        private MrpDemandService $demandService,
        private MrpSuggestionService $suggestionService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new MRP run.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): MrpRun
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $run = new MrpRun($data);
            $run->run_number = MrpRun::generateRunNumber();
            $run->status = DocumentStatus::Draft;
            $run->created_by = $data['created_by'] ?? $this->getUserId();
            $run->save();

            return $run->fresh(['warehouse']);
        }, ['warehouse_id' => $data['warehouse_id'] ?? null]);
    }

    /**
     * Execute MRP run - collect demands and generate suggestions.
     */
    public function executeRun(MrpRun $run, ?int $userId = null): MrpRun
    {
        if ($run->status !== DocumentStatus::Draft) {
            throw new InvalidArgumentException('Hanya MRP run dalam status draft yang dapat dijalankan.');
        }

        $userId ??= $this->getUserId();

        return $this->executeInTransaction('execute_run', function () use ($run, $userId) {
            $run->status = DocumentStatus::Processing;
            $run->save();

            // Dispatch started event
            event(MrpRunStarted::fromMrpRun($run, $userId));

            try {
                // Step 1: Collect demands from work orders
                $this->demandService->collectDemands($run);

                // Step 2: Calculate supply for each demand
                $this->demandService->calculateSupply($run);

                // Step 3: Explode BOM for products that need to be made
                $this->demandService->explodeBomDemands($run);

                // Step 4: Generate suggestions for shortages
                $this->suggestionService->generateSuggestions($run);

                // Step 5: Update summary counts
                $run->updateSummaryCounts();

                $run->status = DocumentStatus::Completed;
                $run->completed_at = now();
                $run->save();

                // Dispatch completed event
                event(MrpRunCompleted::fromMrpRun($run, $userId));

                return $run->fresh(['demands', 'suggestions', 'warehouse']);
            } catch (\Exception $e) {
                $run->status = DocumentStatus::Draft;
                $run->save();

                // Dispatch failed event
                event(MrpRunFailed::fromMrpRun($run, $e, $userId));

                throw $e;
            }
        }, ['run_id' => $run->id]);
    }

    /**
     * Update an MRP run (only draft).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(MrpRun $run, array $data): MrpRun
    {
        if ($run->status !== DocumentStatus::Draft) {
            throw new InvalidArgumentException('Hanya MRP run dalam status draft yang dapat diubah.');
        }

        $run->fill($data);
        $run->save();

        return $run->fresh(['warehouse']);
    }

    /**
     * Delete an MRP run.
     */
    public function delete(MrpRun $run): bool
    {
        if (! $run->canBeDeleted()) {
            throw new InvalidArgumentException('MRP run tidak dapat dihapus.');
        }

        return $this->executeInTransaction('delete', function () use ($run) {
            $run->demands()->delete();
            $run->suggestions()->delete();

            return $run->delete();
        }, ['run_id' => $run->id]);
    }

    /**
     * Get MRP run statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $total = MrpRun::count();
        $byStatus = [];

        foreach (MrpRun::getStatuses() as $status => $label) {
            $byStatus[$status] = MrpRun::where('status', $status)->count();
        }

        $lastRun = MrpRun::where('status', DocumentStatus::Completed)
            ->orderByDesc('completed_at')
            ->first();

        $lastApplied = MrpRun::where('status', DocumentStatus::Applied)
            ->orderByDesc('applied_at')
            ->first();

        return [
            'total_runs' => $total,
            'by_status' => $byStatus,
            'last_completed_run' => $lastRun ? [
                'id' => $lastRun->id,
                'run_number' => $lastRun->run_number,
                'completed_at' => $lastRun->completed_at,
                'total_suggestions' => $lastRun->total_purchase_suggestions
                    + $lastRun->total_work_order_suggestions
                    + $lastRun->total_subcontract_suggestions,
            ] : null,
            'last_applied_run' => $lastApplied ? [
                'id' => $lastApplied->id,
                'run_number' => $lastApplied->run_number,
                'applied_at' => $lastApplied->applied_at,
            ] : null,
        ];
    }
}
