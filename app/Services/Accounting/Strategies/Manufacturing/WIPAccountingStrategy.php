<?php

declare(strict_types=1);

namespace App\Services\Accounting\Strategies\Manufacturing;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Accounting\Strategies\ManufacturingCostStrategy;
use App\Models\Accounting\JournalEntry;
use App\Models\Manufacturing\MaterialConsumption;
use App\Models\Manufacturing\WorkOrder;

/**
 * Full WIP (Work in Progress) accounting strategy.
 *
 * Creates journal entries for all manufacturing stages:
 * - Raw materials → WIP on consumption
 * - WIP → Finished Goods on completion
 *
 * Most complex but provides complete audit trail.
 */
class WIPAccountingStrategy implements ManufacturingCostStrategy
{
    public function __construct(
        private JournalServiceInterface $journalService
    ) {}

    public function onWorkOrderStart(WorkOrder $workOrder): ?JournalEntry
    {
        // WIP is tracked when materials are actually consumed, not on WO start
        return null;
    }

    public function onMaterialConsumption(MaterialConsumption $consumption): ?JournalEntry
    {
        $consumption->loadMissing(['product', 'workOrder']);

        $amount = $consumption->total_cost;

        if ($amount <= 0) {
            return null;
        }

        $wipAccount = config('accounting.default_accounts.wip', '1-1450');
        $rawMaterialsAccount = config('accounting.default_accounts.inventory', '1-1400');

        $woNumber = $consumption->workOrder->wo_number ?? 'WO-Unknown';

        $lines = [
            [
                'account_code' => $wipAccount,
                'debit' => $amount,
                'credit' => 0,
                'description' => "Konsumsi material: {$consumption->product->name}",
            ],
            [
                'account_code' => $rawMaterialsAccount,
                'debit' => 0,
                'credit' => $amount,
                'description' => "Transfer ke WIP: {$woNumber}",
            ],
        ];

        $entryDate = $consumption->consumed_date ?? now();

        return $this->journalService->createEntry([
            'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : $entryDate,
            'description' => "Konsumsi Material: {$woNumber}",
            'reference' => $woNumber,
            'source_type' => MaterialConsumption::class,
            'source_id' => $consumption->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function onWorkOrderComplete(WorkOrder $workOrder): ?JournalEntry
    {
        // Calculate total WIP accumulated for this work order (DB aggregation)
        $totalWIP = (int) $workOrder->consumptions()->sum('total_cost');

        if ($totalWIP <= 0) {
            return null;
        }

        $finishedGoodsAccount = config('accounting.default_accounts.finished_goods', '1-1410');
        $wipAccount = config('accounting.default_accounts.wip', '1-1450');

        $lines = [
            [
                'account_code' => $finishedGoodsAccount,
                'debit' => $totalWIP,
                'credit' => 0,
                'description' => "Barang jadi: {$workOrder->wo_number}",
            ],
            [
                'account_code' => $wipAccount,
                'debit' => 0,
                'credit' => $totalWIP,
                'description' => "Transfer dari WIP: {$workOrder->wo_number}",
            ],
        ];

        $entryDate = $workOrder->completed_at ?? now();

        return $this->journalService->createEntry([
            'entry_date' => $entryDate instanceof \DateTimeInterface ? $entryDate->format('Y-m-d') : $entryDate,
            'description' => "Penyelesaian Work Order: {$workOrder->wo_number}",
            'reference' => $workOrder->wo_number,
            'source_type' => WorkOrder::class,
            'source_id' => $workOrder->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    public function calculateTotalCost(WorkOrder $workOrder): int
    {
        return (int) $workOrder->consumptions()->sum('total_cost');
    }

    public function getIdentifier(): string
    {
        return 'wip_accounting';
    }
}
