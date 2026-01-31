<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingChecklist;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Base\BaseService;

class FiscalPeriodService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalService $journalService,
        private YearEndCloseService $yearEndCloseService
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new fiscal period.
     *
     * @param  array{name: string, start_date: string, end_date: string, description?: string}  $data
     */
    public function create(array $data): FiscalPeriod
    {
        return $this->executeInTransaction('create_fiscal_period', function () use ($data) {
            return FiscalPeriod::create([
                ...$data,
                'is_closed' => false,
                'is_locked' => false,
            ]);
        }, ['name' => $data['name']]);
    }

    /**
     * Close a fiscal period with closing journal entry.
     *
     * Delegates to YearEndCloseService for the full closing process.
     *
     * @return array{success: bool, message: string, closing_entry: ?JournalEntry}
     */
    public function closePeriod(FiscalPeriod $period, ?string $notes = null): array
    {
        try {
            $result = $this->yearEndCloseService->executeClose($period, [
                'notes' => $notes,
            ]);

            // Refresh period to get updated closing_entry_id
            $period->refresh();

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'closing_entry' => $period->closingEntry,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'closing_entry' => null,
            ];
        }
    }

    /**
     * Close a fiscal period using the legacy approach (for backward compatibility).
     *
     * @return array{success: bool, message: string, closing_entry: ?JournalEntry}
     *
     * @deprecated Use closePeriod() which delegates to YearEndCloseService
     */
    public function closePeriodLegacy(FiscalPeriod $period, ?string $notes = null): array
    {
        // Check if can close
        $canClose = $period->canClose();
        if (! $canClose['can_close']) {
            return [
                'success' => false,
                'message' => implode(' ', $canClose['errors']),
                'closing_entry' => null,
            ];
        }

        if ($period->is_closed) {
            return [
                'success' => false,
                'message' => 'Periode fiskal sudah ditutup.',
                'closing_entry' => null,
            ];
        }

        return $this->executeInTransaction('close_period_legacy', function () use ($period, $notes) {
            // Calculate income statement
            $incomeStatement = $period->getIncomeStatement();
            $netIncome = $incomeStatement['net_income'];

            // Create closing journal entry
            $closingEntry = $this->createClosingEntry($period, $netIncome);

            // Update fiscal period
            $period->update([
                'is_closed' => true,
                'is_locked' => true,
                'closed_at' => now(),
                'closed_by' => $this->getUserId(),
                'closing_entry_id' => $closingEntry->id,
                'retained_earnings_amount' => $netIncome,
                'closing_notes' => $notes,
            ]);

            return [
                'success' => true,
                'message' => 'Periode fiskal berhasil ditutup.',
                'closing_entry' => $closingEntry,
            ];
        }, ['period_id' => $period->id]);
    }

    /**
     * Create closing journal entry for a period.
     */
    protected function createClosingEntry(FiscalPeriod $period, int $netIncome): JournalEntry
    {
        // Get retained earnings account
        $retainedEarningsAccount = Account::where('code', '3-2000')->first();
        if (! $retainedEarningsAccount) {
            // Create if not exists
            $retainedEarningsAccount = Account::create([
                'code' => '3-2000',
                'name' => 'Laba Ditahan',
                'type' => Account::TYPE_EQUITY,
                'description' => 'Laba ditahan dari periode sebelumnya',
                'is_active' => true,
                'is_system' => true,
            ]);
        }

        $lines = [];

        // Close revenue accounts (debit to zero them out)
        $revenueLines = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);
            })
            ->whereHas('account', function ($q) {
                $q->where('type', Account::TYPE_REVENUE);
            })
            ->select('account_id')
            ->selectRaw('SUM(credit - debit) as balance')
            ->groupBy('account_id')
            ->get();

        foreach ($revenueLines as $line) {
            if ($line->balance != 0) {
                $lines[] = [
                    'account_id' => $line->account_id,
                    'description' => 'Penutupan pendapatan periode '.$period->name,
                    'debit' => $line->balance > 0 ? $line->balance : 0,
                    'credit' => $line->balance < 0 ? abs($line->balance) : 0,
                ];
            }
        }

        // Close expense accounts (credit to zero them out)
        $expenseLines = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);
            })
            ->whereHas('account', function ($q) {
                $q->where('type', Account::TYPE_EXPENSE);
            })
            ->select('account_id')
            ->selectRaw('SUM(debit - credit) as balance')
            ->groupBy('account_id')
            ->get();

        foreach ($expenseLines as $line) {
            if ($line->balance != 0) {
                $lines[] = [
                    'account_id' => $line->account_id,
                    'description' => 'Penutupan beban periode '.$period->name,
                    'debit' => $line->balance < 0 ? abs($line->balance) : 0,
                    'credit' => $line->balance > 0 ? $line->balance : 0,
                ];
            }
        }

        // Transfer net income to retained earnings
        if ($netIncome != 0) {
            $lines[] = [
                'account_id' => $retainedEarningsAccount->id,
                'description' => 'Laba/Rugi bersih periode '.$period->name,
                'debit' => $netIncome < 0 ? abs($netIncome) : 0,
                'credit' => $netIncome > 0 ? $netIncome : 0,
            ];
        }

        // Create the closing entry
        return $this->journalService->createEntry([
            'entry_date' => $period->end_date->toDateString(),
            'description' => 'Jurnal penutup periode '.$period->name,
            'reference' => 'CLOSE-'.$period->id,
            'source_type' => JournalEntry::SOURCE_CLOSING,
            'lines' => $lines,
        ], autoPost: true);
    }

    /**
     * Reopen a closed fiscal period.
     */
    public function reopenPeriod(FiscalPeriod $period): bool
    {
        if (! $period->is_closed) {
            return false;
        }

        return $this->executeInTransaction('reopen_period', function () use ($period) {
            // Reverse the closing entry if exists
            if ($period->closingEntry) {
                $this->journalService->reverseEntry(
                    $period->closingEntry,
                    'Pembatalan penutupan periode '.$period->name
                );
            }

            // Reopen the period
            $period->update([
                'is_closed' => false,
                'is_locked' => false,
                'closed_at' => null,
                'closed_by' => null,
                'closing_entry_id' => null,
                'retained_earnings_amount' => null,
                'closing_notes' => null,
            ]);

            return true;
        }, ['period_id' => $period->id]);
    }

    /**
     * Get the closing checklist for a period.
     *
     * Returns the enhanced ClosingChecklist from YearEndCloseService.
     */
    public function getClosingChecklist(FiscalPeriod $period): ClosingChecklist
    {
        return $this->yearEndCloseService->getClosingChecklist($period);
    }

    /**
     * Get the closing checklist as an array (legacy format).
     *
     * @return array<string, array{status: string, count: int, message: string}>
     */
    public function getClosingChecklistArray(FiscalPeriod $period): array
    {
        return $this->getClosingChecklist($period)->toArray();
    }
}
