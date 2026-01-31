<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Manufacturing;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;

/**
 * Job costing strategy.
 *
 * Tracks costs per work order with journal entries.
 * Suitable for custom manufacturing where each job is unique.
 */
class JobCostingStrategy implements ManufacturingCostStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry
    {
        // No journal on start - costs recorded as materials are consumed
        return null;
    }

    public function onMaterialConsumption(MaterialConsumption $consumption): ?JournalEntry
    {
        $consumption->loadMissing(['product', 'workOrder']);

        $amount = $consumption->total_cost;

        if ($amount <= 0) {
            return null;
        }

        $workOrderNumber = $consumption->workOrder->wo_number;

        $entryDate = $consumption->consumed_date ?? now();
        if ($entryDate instanceof \Illuminate\Support\Carbon || $entryDate instanceof \DateTimeInterface) {
            $entryDate = $entryDate->format('Y-m-d');
        }

        return $this->journalService->createEntry([
            'entry_date' => $entryDate,
            'reference' => $workOrderNumber,
            'description' => "Konsumsi Material (Job Costing): {$workOrderNumber}",
            'source_type' => MaterialConsumption::class,
            'source_id' => $consumption->id,
            'lines' => [
                [
                    'account_code' => config('accounting.default_accounts.wip', '1-1450'),
                    'description' => "Konsumsi material: {$consumption->product->name}",
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_code' => config('accounting.default_accounts.inventory', '1-1400'),
                    'description' => "Transfer ke WIP: {$workOrderNumber}",
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
        ]);
    }

    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry
    {
        $workOrder->loadMissing(['consumptions.product']);

        $totalWIP = $workOrder->consumptions->sum('total_cost');

        if ($totalWIP <= 0) {
            return null;
        }

        $workOrderNumber = $workOrder->wo_number;

        $entryDate = $workOrder->completed_at ?? now();
        if ($entryDate instanceof \Illuminate\Support\Carbon || $entryDate instanceof \DateTimeInterface) {
            $entryDate = $entryDate->format('Y-m-d');
        }

        return $this->journalService->createEntry([
            'entry_date' => $entryDate,
            'reference' => $workOrderNumber,
            'description' => "Penyelesaian Work Order (Job Costing): {$workOrderNumber}",
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
            'lines' => [
                [
                    'account_code' => config('accounting.default_accounts.finished_goods', '1-1410'),
                    'description' => "Barang jadi (Job Costing): {$workOrderNumber}",
                    'debit' => $totalWIP,
                    'credit' => 0,
                ],
                [
                    'account_code' => config('accounting.default_accounts.wip', '1-1450'),
                    'description' => "Transfer dari WIP: {$workOrderNumber}",
                    'debit' => 0,
                    'credit' => $totalWIP,
                ],
            ],
        ]);
    }

    public function calculateTotalCost(WorkOrder $workOrder): int
    {
        $workOrder->loadMissing(['consumptions']);

        return (int) $workOrder->consumptions->sum('total_cost');
    }

    public function getIdentifier(): string
    {
        return 'job_costing';
    }
}
