<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\YearEndCloseServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\ClosingStep;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\Exceptions\FiscalPeriodException;
use App\Domain\Accounting\FiscalPeriods\FiscalPeriodStateMachine;
use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingChecklist;
use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingProgress;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Base\BaseService;

/**
 * Orchestrator service for year-end closing process.
 *
 * Manages the multi-step closing workflow:
 * 1. Lock period
 * 2. Validate checklist
 * 3. Close temporary accounts (revenue/expense)
 * 4. Close dividends
 * 5. Mark period closed
 * 6. Create next period (optional)
 * 7. Populate opening balances (optional)
 */
class YearEndCloseService extends BaseService implements YearEndCloseServiceInterface
{
    public function __construct(
        private AccountingPolicyManager $policyManager,
        private JournalService $journalService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Execute the full closing process.
     *
     * @param  array{skip_next_period?: bool, skip_opening_balances?: bool, notes?: string}  $options
     * @return array{success: bool, message: string, progress: ClosingProgress, period: FiscalPeriod, next_period?: FiscalPeriod}
     *
     * @throws FiscalPeriodException
     */
    public function executeClose(FiscalPeriod $period, array $options = []): array
    {
        // Validate current state
        if ($period->getStatus() === FiscalPeriodStatus::Closed) {
            throw FiscalPeriodException::alreadyClosed($period->id);
        }

        if ($period->getStatus() === FiscalPeriodStatus::Closing) {
            throw FiscalPeriodException::closingInProgress($period->id);
        }

        $stateMachine = new FiscalPeriodStateMachine($period);
        $progress = ClosingProgress::initial()->start();

        return $this->executeInTransaction('execute_close', function () use ($period, $options, $stateMachine, $progress) {
            $result = [
                'success' => true,
                'message' => '',
                'progress' => $progress,
                'period' => $period,
            ];

            try {
                // Step 1: Lock Period
                $progress = $this->executeLockPeriod($period, $stateMachine, $progress);

                // Step 2: Validate Checklist
                $progress = $this->executeValidateChecklist($period, $progress);

                // Transition to Closing state
                $stateMachine->startClosing();

                // Step 3: Close Temporary Accounts (Revenue & Expense)
                $progress = $this->executeCloseTemporaryAccounts($period, $progress);

                // Step 4: Close Dividends
                $progress = $this->executeCloseDividends($period, $progress);

                // Calculate net income for context
                $incomeStatement = $period->getIncomeStatement();
                $netIncome = $incomeStatement['net_income'];

                // Step 5: Mark Period Closed
                $progress = $this->executeMarkPeriodClosed($period, $stateMachine, $progress, $netIncome);

                // Step 6: Create Next Period (optional)
                $nextPeriod = null;
                if (config('accounting.year_end_close.auto_create_next_period', true)
                    && ! ($options['skip_next_period'] ?? false)) {
                    [$progress, $nextPeriod] = $this->executeCreateNextPeriod($period, $progress);
                    $result['next_period'] = $nextPeriod;
                } else {
                    $progress = $progress->completeStep(ClosingStep::CreateNextPeriod);
                }

                // Step 7: Populate Opening Balances (optional)
                if ($nextPeriod !== null
                    && config('accounting.year_end_close.auto_opening_balances', true)
                    && ! ($options['skip_opening_balances'] ?? false)) {
                    $progress = $this->executePopulateOpeningBalances($period, $nextPeriod, $progress);
                } else {
                    $progress = $progress->completeStep(ClosingStep::PopulateOpeningBalances);
                }

                // Update closing notes if provided
                if (! empty($options['notes'])) {
                    $period->update(['closing_notes' => $options['notes']]);
                }

                $result['progress'] = $progress;
                $result['message'] = 'Periode fiskal berhasil ditutup.';

                return $result;
            } catch (\Throwable $e) {
                // Mark progress as failed
                $currentStep = $progress->currentStep ?? ClosingStep::LockPeriod;
                $progress = $progress->fail($currentStep, $e->getMessage());

                throw FiscalPeriodException::closingStepFailed(
                    $currentStep,
                    $e->getMessage(),
                    $e
                );
            }
        }, ['period_id' => $period->id]);
    }

    /**
     * Execute a single step of the closing process.
     *
     * @throws FiscalPeriodException
     */
    public function executeStep(
        FiscalPeriod $period,
        ClosingStep $step,
        ClosingProgress $progress
    ): ClosingProgress {
        $stateMachine = new FiscalPeriodStateMachine($period);

        return match ($step) {
            ClosingStep::LockPeriod => $this->executeLockPeriod($period, $stateMachine, $progress),
            ClosingStep::ValidateChecklist => $this->executeValidateChecklist($period, $progress),
            ClosingStep::CloseTemporaryAccounts => $this->executeCloseTemporaryAccounts($period, $progress),
            ClosingStep::CloseDividends => $this->executeCloseDividends($period, $progress),
            ClosingStep::MarkPeriodClosed => $this->executeMarkPeriodClosed(
                $period,
                $stateMachine,
                $progress,
                $period->getIncomeStatement()['net_income']
            ),
            ClosingStep::CreateNextPeriod => $this->executeCreateNextPeriod($period, $progress)[0],
            ClosingStep::PopulateOpeningBalances => throw new \InvalidArgumentException(
                'PopulateOpeningBalances requires a next period'
            ),
        };
    }

    /**
     * Get enhanced closing checklist for a period.
     */
    public function getClosingChecklist(FiscalPeriod $period): ClosingChecklist
    {
        $checks = [];

        // Check 1: Unposted journals (blocking)
        $unposted = $period->journalEntries()->where('is_posted', false)->count();
        $checks['unposted_journals'] = [
            'status' => $unposted === 0 ? 'ok' : 'error',
            'count' => $unposted,
            'message' => $unposted === 0
                ? 'Semua jurnal sudah diposting'
                : "{$unposted} jurnal belum diposting",
        ];

        // Check 2: Draft invoices (warning)
        $draftInvoices = \App\Models\Sales\Invoice::query()
            ->where('status', \App\Enums\DocumentStatus::Draft)
            ->whereBetween('invoice_date', [$period->start_date->toDateString(), $period->end_date->toDateString().' 23:59:59'])
            ->count();
        $checks['draft_invoices'] = [
            'status' => $draftInvoices === 0 ? 'ok' : 'warning',
            'count' => $draftInvoices,
            'message' => $draftInvoices === 0
                ? 'Tidak ada faktur draft'
                : "{$draftInvoices} faktur masih draft",
        ];

        // Check 3: Draft bills (warning)
        $draftBills = \App\Models\Purchasing\Bill::query()
            ->where('status', \App\Enums\DocumentStatus::Draft)
            ->whereBetween('bill_date', [$period->start_date->toDateString(), $period->end_date->toDateString().' 23:59:59'])
            ->count();
        $checks['draft_bills'] = [
            'status' => $draftBills === 0 ? 'ok' : 'warning',
            'count' => $draftBills,
            'message' => $draftBills === 0
                ? 'Tidak ada tagihan draft'
                : "{$draftBills} tagihan masih draft",
        ];

        // Check 4: Unreconciled bank transactions (warning)
        $unreconciled = \App\Models\Accounting\BankTransaction::query()
            ->where('status', '!=', 'reconciled')
            ->whereBetween('transaction_date', [$period->start_date->toDateString(), $period->end_date->toDateString().' 23:59:59'])
            ->count();
        $checks['unreconciled_bank'] = [
            'status' => $unreconciled === 0 ? 'ok' : 'warning',
            'count' => $unreconciled,
            'message' => $unreconciled === 0
                ? 'Semua transaksi bank sudah direkonsiliasi'
                : "{$unreconciled} transaksi bank belum direkonsiliasi",
        ];

        // Check 5: Required accounts exist (blocking)
        $missingAccounts = $this->checkRequiredAccounts();
        $checks['required_accounts'] = [
            'status' => empty($missingAccounts) ? 'ok' : 'error',
            'count' => count($missingAccounts),
            'message' => empty($missingAccounts)
                ? 'Semua akun yang diperlukan tersedia'
                : 'Akun tidak ditemukan: '.implode(', ', $missingAccounts),
        ];

        // Check 6: Trial balance balanced (blocking)
        $trialBalance = $this->checkTrialBalance($period);
        $checks['trial_balance'] = [
            'status' => $trialBalance['is_balanced'] ? 'ok' : 'error',
            'count' => $trialBalance['difference'],
            'message' => $trialBalance['is_balanced']
                ? 'Neraca saldo seimbang'
                : "Neraca saldo tidak seimbang (selisih: {$trialBalance['difference']})",
        ];

        return ClosingChecklist::fromArray($checks);
    }

    /**
     * Rollback a partial close (reverse journal entries).
     */
    public function rollbackClose(FiscalPeriod $period, ClosingProgress $progress): void
    {
        $this->executeInTransaction('rollback_close', function () use ($period, $progress) {
            // Reset period status FIRST so reversal JEs can be created
            // (createEntry blocks JEs in closed periods)
            $period->update([
                'status' => FiscalPeriodStatus::Open->value,
                'is_locked' => false,
                'is_closed' => false,
                'closed_at' => null,
                'closed_by' => null,
            ]);

            // Reverse journal entries in reverse order
            $journalIds = array_reverse($progress->getJournalEntryIds());

            foreach ($journalIds as $journalId) {
                $journal = JournalEntry::find($journalId);
                if ($journal) {
                    $this->journalService->reverseEntry(
                        $journal,
                        "Pembatalan penutupan periode {$period->name}"
                    );
                }
            }
        }, ['period_id' => $period->id]);
    }

    /**
     * Create opening balance journal entry for new period.
     */
    public function createOpeningBalanceEntry(
        FiscalPeriod $closedPeriod,
        FiscalPeriod $newPeriod
    ): ?JournalEntry {
        $lines = [];

        // Get all permanent account balances (assets, liabilities, equity)
        $permanentTypes = [
            Account::TYPE_ASSET,
            Account::TYPE_LIABILITY,
            Account::TYPE_EQUITY,
        ];

        foreach ($permanentTypes as $type) {
            $balances = $this->getPermanentAccountBalances($closedPeriod, $type);

            foreach ($balances as $balance) {
                if ($balance->balance == 0) {
                    continue;
                }

                $isDebitBalance = in_array($type, [Account::TYPE_ASSET]);
                $amount = abs($balance->balance);

                $lines[] = [
                    'account_id' => $balance->account_id,
                    'description' => "Saldo awal periode {$newPeriod->name}",
                    'debit' => $isDebitBalance ? $amount : 0,
                    'credit' => $isDebitBalance ? 0 : $amount,
                ];
            }
        }

        if (empty($lines)) {
            return null;
        }

        return $this->journalService->createEntry([
            'entry_date' => $newPeriod->start_date->toDateString(),
            'description' => "Jurnal saldo awal periode {$newPeriod->name}",
            'reference' => "OPEN-{$newPeriod->id}",
            'source_type' => JournalEntry::SOURCE_OPENING,
            'fiscal_period_id' => $newPeriod->id,
            'lines' => $lines,
        ], autoPost: true);
    }

    // =========================================================================
    // Step Implementations
    // =========================================================================

    protected function executeLockPeriod(
        FiscalPeriod $period,
        FiscalPeriodStateMachine $stateMachine,
        ClosingProgress $progress
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::LockPeriod);

        if ($period->getStatus() === FiscalPeriodStatus::Open) {
            $stateMachine->lock();
        }

        return $progress->completeStep(ClosingStep::LockPeriod);
    }

    protected function executeValidateChecklist(
        FiscalPeriod $period,
        ClosingProgress $progress
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::ValidateChecklist);

        $checklist = $this->getClosingChecklist($period);

        if (! $checklist->canClose()) {
            throw FiscalPeriodException::cannotCloseWithErrors(
                $checklist->getBlockingErrors()
            );
        }

        return $progress->completeStep(ClosingStep::ValidateChecklist);
    }

    protected function executeCloseTemporaryAccounts(
        FiscalPeriod $period,
        ClosingProgress $progress
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::CloseTemporaryAccounts);

        $strategy = $this->policyManager->closing();
        $journalIds = [];

        // Close revenue
        $revenueEntry = $strategy->closeRevenueAccounts($period);
        if ($revenueEntry) {
            $journalIds[] = $revenueEntry->id;
        }

        // Close expenses
        $expenseEntry = $strategy->closeExpenseAccounts($period);
        if ($expenseEntry) {
            $journalIds[] = $expenseEntry->id;
        }

        // Close income summary (if using income summary strategy)
        $incomeSummaryEntry = $strategy->closeIncomeSummary($period);
        if ($incomeSummaryEntry) {
            $journalIds[] = $incomeSummaryEntry->id;

            // Store closing entry ID
            $period->update(['closing_entry_id' => $incomeSummaryEntry->id]);
        } elseif ($revenueEntry || $expenseEntry) {
            // For direct strategy, use the revenue entry as the main closing entry
            $period->update(['closing_entry_id' => $revenueEntry->id ?? $expenseEntry->id ?? null]);
        }

        // Return progress with the last journal ID for rollback tracking
        return $progress->completeStep(
            ClosingStep::CloseTemporaryAccounts,
            $journalIds[count($journalIds) - 1] ?? null
        );
    }

    protected function executeCloseDividends(
        FiscalPeriod $period,
        ClosingProgress $progress
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::CloseDividends);

        $strategy = $this->policyManager->closing();
        $dividendEntry = $strategy->closeDividends($period);

        return $progress->completeStep(
            ClosingStep::CloseDividends,
            $dividendEntry->id ?? null
        );
    }

    protected function executeMarkPeriodClosed(
        FiscalPeriod $period,
        FiscalPeriodStateMachine $stateMachine,
        ClosingProgress $progress,
        int $netIncome
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::MarkPeriodClosed);

        // Update period with net income
        $period->update(['retained_earnings_amount' => $netIncome]);

        // Transition to Closed state
        $stateMachine->markClosed([
            'net_income' => $netIncome,
            'closing_entry_id' => $period->closing_entry_id,
        ]);

        return $progress->completeStep(ClosingStep::MarkPeriodClosed);
    }

    /**
     * @return array{ClosingProgress, FiscalPeriod}
     */
    protected function executeCreateNextPeriod(
        FiscalPeriod $period,
        ClosingProgress $progress
    ): array {
        $progress = $progress->startStep(ClosingStep::CreateNextPeriod);

        $nextPeriod = $period->createNextPeriod();

        return [$progress->completeStep(ClosingStep::CreateNextPeriod), $nextPeriod];
    }

    protected function executePopulateOpeningBalances(
        FiscalPeriod $closedPeriod,
        FiscalPeriod $newPeriod,
        ClosingProgress $progress
    ): ClosingProgress {
        $progress = $progress->startStep(ClosingStep::PopulateOpeningBalances);

        $openingEntry = $this->createOpeningBalanceEntry($closedPeriod, $newPeriod);

        return $progress->completeStep(
            ClosingStep::PopulateOpeningBalances,
            $openingEntry->id ?? null
        );
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Check if required accounts exist.
     *
     * @return array<string> Missing account codes
     */
    protected function checkRequiredAccounts(): array
    {
        $missing = [];
        $requiredCodes = [
            config('accounting.default_accounts.retained_earnings', '3-2000'),
        ];

        // Add income summary account if using that strategy
        if (config('accounting.policies.closing_strategy') === 'income_summary') {
            $requiredCodes[] = config('accounting.default_accounts.income_summary', '3-9000');
        }

        foreach ($requiredCodes as $code) {
            if (! Account::where('code', $code)->exists()) {
                $missing[] = $code;
            }
        }

        return $missing;
    }

    /**
     * Check if trial balance is balanced.
     *
     * @return array{is_balanced: bool, difference: int, total_debit: int, total_credit: int}
     */
    protected function checkTrialBalance(FiscalPeriod $period): array
    {
        /** @var object{total_debit: int, total_credit: int} $result */
        $result = JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('fiscal_period_id', $period->id);
            })
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit')
            ->selectRaw('COALESCE(SUM(credit), 0) as total_credit')
            ->toBase()
            ->first();

        $totalDebit = (int) $result->total_debit;
        $totalCredit = (int) $result->total_credit;
        $difference = abs($totalDebit - $totalCredit);

        return [
            'is_balanced' => $difference === 0,
            'difference' => $difference,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
        ];
    }

    /**
     * Get permanent account balances for opening balance calculation.
     *
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    protected function getPermanentAccountBalances(
        FiscalPeriod $period,
        string $accountType
    ): \Illuminate\Support\Collection {
        // For permanent accounts, we need cumulative balance, not just this period
        // This includes all posted entries up to and including this period
        return JournalEntryLine::query()
            ->whereHas('journalEntry', function ($q) use ($period) {
                $q->where('is_posted', true)
                    ->where('entry_date', '<=', $period->end_date);
            })
            ->whereHas('account', function ($q) use ($accountType) {
                $q->where('type', $accountType);
            })
            ->select('account_id')
            ->selectRaw('SUM(debit - credit) as balance')
            ->groupBy('account_id')
            ->toBase()
            ->get();
    }
}
