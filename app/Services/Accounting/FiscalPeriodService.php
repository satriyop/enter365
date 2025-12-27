<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class FiscalPeriodService
{
    public function __construct(
        private JournalService $journalService
    ) {}

    /**
     * Close a fiscal period with closing journal entry.
     *
     * @return array{success: bool, message: string, closing_entry: ?JournalEntry}
     */
    public function closePeriod(FiscalPeriod $period, ?string $notes = null): array
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

        return DB::transaction(function () use ($period, $notes) {
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
                'closed_by' => auth()->id(),
                'closing_entry_id' => $closingEntry->id,
                'retained_earnings_amount' => $netIncome,
                'closing_notes' => $notes,
            ]);

            return [
                'success' => true,
                'message' => 'Periode fiskal berhasil ditutup.',
                'closing_entry' => $closingEntry,
            ];
        });
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

        return DB::transaction(function () use ($period) {
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
        });
    }

    /**
     * Get the closing checklist for a period.
     *
     * @return array<string, array{status: string, count: int, message: string}>
     */
    public function getClosingChecklist(FiscalPeriod $period): array
    {
        $checklist = [];

        // Unposted journals
        $unposted = $period->journalEntries()->where('is_posted', false)->count();
        $checklist['unposted_journals'] = [
            'status' => $unposted === 0 ? 'ok' : 'error',
            'count' => $unposted,
            'message' => $unposted === 0
                ? 'Semua jurnal sudah diposting'
                : "{$unposted} jurnal belum diposting",
        ];

        // Draft invoices
        $draftInvoices = \App\Models\Accounting\Invoice::query()
            ->where('status', \App\Models\Accounting\Invoice::STATUS_DRAFT)
            ->whereBetween('invoice_date', [$period->start_date, $period->end_date])
            ->count();
        $checklist['draft_invoices'] = [
            'status' => $draftInvoices === 0 ? 'ok' : 'warning',
            'count' => $draftInvoices,
            'message' => $draftInvoices === 0
                ? 'Tidak ada faktur draft'
                : "{$draftInvoices} faktur masih draft",
        ];

        // Draft bills
        $draftBills = \App\Models\Accounting\Bill::query()
            ->where('status', \App\Models\Accounting\Bill::STATUS_DRAFT)
            ->whereBetween('bill_date', [$period->start_date, $period->end_date])
            ->count();
        $checklist['draft_bills'] = [
            'status' => $draftBills === 0 ? 'ok' : 'warning',
            'count' => $draftBills,
            'message' => $draftBills === 0
                ? 'Tidak ada tagihan draft'
                : "{$draftBills} tagihan masih draft",
        ];

        // Unreconciled bank transactions
        $unreconciled = \App\Models\Accounting\BankTransaction::query()
            ->where('status', '!=', 'reconciled')
            ->whereBetween('transaction_date', [$period->start_date, $period->end_date])
            ->count();
        $checklist['unreconciled_bank'] = [
            'status' => $unreconciled === 0 ? 'ok' : 'warning',
            'count' => $unreconciled,
            'message' => $unreconciled === 0
                ? 'Semua transaksi bank sudah direkonsiliasi'
                : "{$unreconciled} transaksi bank belum direkonsiliasi",
        ];

        return $checklist;
    }
}
