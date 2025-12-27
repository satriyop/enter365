<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Payment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BankReconciliationReportService
{
    /**
     * Get reconciliation report comparing book balance vs bank balance.
     *
     * @return array{
     *     account: array{id: int, code: string, name: string},
     *     as_of_date: string,
     *     book_balance: int,
     *     bank_balance: int,
     *     adjustments_to_book: array{items: Collection, total: int},
     *     adjustments_to_bank: array{items: Collection, total: int},
     *     adjusted_book_balance: int,
     *     adjusted_bank_balance: int,
     *     difference: int,
     *     is_reconciled: bool,
     *     reconciliation_summary: array{total: int, reconciled: int, unmatched: int, matched: int}
     * }
     */
    public function getReconciliationReport(Account $account, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();

        // Get book balance (from journal entries)
        $bookBalance = $this->getBookBalance($account, $asOfDate);

        // Get bank balance (from imported bank transactions)
        $bankBalance = $this->getBankBalance($account, $asOfDate);

        // Get adjustments needed to reconcile book to bank
        $adjustmentsToBook = $this->getAdjustmentsToBook($account, $asOfDate);

        // Get adjustments needed to reconcile bank to book
        $adjustmentsToBank = $this->getAdjustmentsToBank($account, $asOfDate);

        $adjustedBookBalance = $bookBalance + $adjustmentsToBook['total'];
        $adjustedBankBalance = $bankBalance + $adjustmentsToBank['total'];

        $difference = $adjustedBookBalance - $adjustedBankBalance;

        // Get reconciliation summary
        $summary = $this->getReconciliationSummary($account, $asOfDate);

        return [
            'account' => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ],
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'book_balance' => $bookBalance,
            'bank_balance' => $bankBalance,
            'adjustments_to_book' => $adjustmentsToBook,
            'adjustments_to_bank' => $adjustmentsToBank,
            'adjusted_book_balance' => $adjustedBookBalance,
            'adjusted_bank_balance' => $adjustedBankBalance,
            'difference' => $difference,
            'is_reconciled' => $difference === 0,
            'reconciliation_summary' => $summary,
        ];
    }

    /**
     * Get outstanding items that need attention.
     *
     * @return array{
     *     outstanding_deposits: Collection,
     *     outstanding_checks: Collection,
     *     unmatched_bank_transactions: Collection,
     *     unmatched_book_entries: Collection
     * }
     */
    public function getOutstandingItems(Account $account, ?string $asOfDate = null): array
    {
        $asOfDate = $asOfDate ? Carbon::parse($asOfDate) : now();

        return [
            'outstanding_deposits' => $this->getOutstandingDeposits($account, $asOfDate),
            'outstanding_checks' => $this->getOutstandingChecks($account, $asOfDate),
            'unmatched_bank_transactions' => $this->getUnmatchedBankTransactions($account, $asOfDate),
            'unmatched_book_entries' => $this->getUnmatchedBookEntries($account, $asOfDate),
        ];
    }

    /**
     * Get book balance from posted journal entries.
     */
    protected function getBookBalance(Account $account, Carbon $asOfDate): int
    {
        $query = JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                    ->whereDate('entry_date', '<=', $asOfDate);
            });

        $totalDebit = (clone $query)->sum('debit');
        $totalCredit = (clone $query)->sum('credit');

        // Bank accounts are assets (debit normal)
        return (int) ($account->opening_balance + $totalDebit - $totalCredit);
    }

    /**
     * Get bank balance from imported bank transactions.
     */
    protected function getBankBalance(Account $account, Carbon $asOfDate): int
    {
        $lastTransaction = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->first();

        if ($lastTransaction && $lastTransaction->balance !== null) {
            return $lastTransaction->balance;
        }

        // Calculate from transactions if balance field not populated
        $totalDebit = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->sum('debit');

        $totalCredit = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->sum('credit');

        return (int) ($totalDebit - $totalCredit);
    }

    /**
     * Get adjustments needed to reconcile book balance to bank.
     * These are items in bank but not yet in books.
     *
     * @return array{items: Collection, total: int}
     */
    protected function getAdjustmentsToBook(Account $account, Carbon $asOfDate): array
    {
        // Find bank transactions that are not matched to any book entry
        $items = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->where('status', BankTransaction::STATUS_UNMATCHED)
            ->orderBy('transaction_date')
            ->get()
            ->map(fn (BankTransaction $txn) => [
                'id' => $txn->id,
                'date' => $txn->transaction_date->format('Y-m-d'),
                'description' => $txn->description,
                'reference' => $txn->reference,
                'amount' => $txn->debit - $txn->credit,
                'type' => $txn->debit > 0 ? 'deposit' : 'withdrawal',
            ]);

        return [
            'items' => $items,
            'total' => $items->sum('amount'),
        ];
    }

    /**
     * Get adjustments needed to reconcile bank balance to book.
     * These are items in books but not yet cleared by bank.
     *
     * @return array{items: Collection, total: int}
     */
    protected function getAdjustmentsToBank(Account $account, Carbon $asOfDate): array
    {
        $items = collect();

        // Find payments that don't have matching bank transactions
        $outstandingPayments = Payment::query()
            ->where('account_id', $account->id)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->where('is_voided', false)
            ->whereDoesntHave('bankTransaction')
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'type' => 'payment',
                'date' => $payment->payment_date->format('Y-m-d'),
                'number' => $payment->payment_number,
                'description' => $payment->description,
                'amount' => $payment->type === Payment::TYPE_RECEIVE
                    ? $payment->amount
                    : -$payment->amount,
            ]);

        $items = $items->merge($outstandingPayments);

        // Negative total because these are in books but NOT in bank yet
        // So they need to be added back to bank balance
        $total = $items->sum('amount');

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Get reconciliation summary statistics.
     *
     * @return array{total: int, reconciled: int, unmatched: int, matched: int}
     */
    protected function getReconciliationSummary(Account $account, Carbon $asOfDate): array
    {
        $baseQuery = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate);

        return [
            'total' => (clone $baseQuery)->count(),
            'reconciled' => (clone $baseQuery)->where('status', BankTransaction::STATUS_RECONCILED)->count(),
            'matched' => (clone $baseQuery)->where('status', BankTransaction::STATUS_MATCHED)->count(),
            'unmatched' => (clone $baseQuery)->where('status', BankTransaction::STATUS_UNMATCHED)->count(),
        ];
    }

    /**
     * Get outstanding deposits (recorded in books, not yet in bank).
     */
    protected function getOutstandingDeposits(Account $account, Carbon $asOfDate): Collection
    {
        return Payment::query()
            ->where('account_id', $account->id)
            ->where('type', Payment::TYPE_RECEIVE)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->where('is_voided', false)
            ->whereDoesntHave('bankTransaction')
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'date' => $payment->payment_date->format('Y-m-d'),
                'number' => $payment->payment_number,
                'description' => $payment->description,
                'amount' => $payment->amount,
            ]);
    }

    /**
     * Get outstanding checks (issued but not yet cleared).
     */
    protected function getOutstandingChecks(Account $account, Carbon $asOfDate): Collection
    {
        return Payment::query()
            ->where('account_id', $account->id)
            ->where('type', Payment::TYPE_SEND)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->where('is_voided', false)
            ->whereDoesntHave('bankTransaction')
            ->orderBy('payment_date')
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'date' => $payment->payment_date->format('Y-m-d'),
                'number' => $payment->payment_number,
                'description' => $payment->description,
                'amount' => $payment->amount,
            ]);
    }

    /**
     * Get unmatched bank transactions.
     */
    protected function getUnmatchedBankTransactions(Account $account, Carbon $asOfDate): Collection
    {
        return BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereDate('transaction_date', '<=', $asOfDate)
            ->where('status', BankTransaction::STATUS_UNMATCHED)
            ->orderBy('transaction_date')
            ->get()
            ->map(fn (BankTransaction $txn) => [
                'id' => $txn->id,
                'date' => $txn->transaction_date->format('Y-m-d'),
                'description' => $txn->description,
                'reference' => $txn->reference,
                'debit' => $txn->debit,
                'credit' => $txn->credit,
                'net_amount' => $txn->debit - $txn->credit,
            ]);
    }

    /**
     * Get unmatched book entries (journal lines without bank transaction match).
     */
    protected function getUnmatchedBookEntries(Account $account, Carbon $asOfDate): Collection
    {
        // Get journal entry lines that don't have matching bank transactions
        $matchedLineIds = BankTransaction::query()
            ->where('account_id', $account->id)
            ->whereNotNull('matched_journal_line_id')
            ->pluck('matched_journal_line_id');

        return JournalEntryLine::query()
            ->where('account_id', $account->id)
            ->whereNotIn('id', $matchedLineIds)
            ->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('is_posted', true)
                    ->whereDate('entry_date', '<=', $asOfDate);
            })
            ->with('journalEntry')
            ->orderBy('created_at', 'desc')
            ->limit(100) // Limit to recent entries
            ->get()
            ->map(fn (JournalEntryLine $line) => [
                'id' => $line->id,
                'journal_entry_id' => $line->journal_entry_id,
                'date' => $line->journalEntry->entry_date->format('Y-m-d'),
                'journal_number' => $line->journalEntry->entry_number,
                'description' => $line->description ?? $line->journalEntry->description,
                'debit' => $line->debit,
                'credit' => $line->credit,
            ]);
    }

    /**
     * Get reconciliation history for an account.
     *
     * @return Collection<int, array{date: string, count: int, total_amount: int}>
     */
    public function getReconciliationHistory(Account $account, ?string $startDate = null, ?string $endDate = null): Collection
    {
        $startDate = $startDate ? Carbon::parse($startDate) : now()->subMonths(12);
        $endDate = $endDate ? Carbon::parse($endDate) : now();

        return BankTransaction::query()
            ->where('account_id', $account->id)
            ->where('status', BankTransaction::STATUS_RECONCILED)
            ->whereNotNull('reconciled_at')
            ->whereBetween('reconciled_at', [$startDate, $endDate])
            ->selectRaw('DATE(reconciled_at) as date, COUNT(*) as count, SUM(debit) - SUM(credit) as total_amount')
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
                'total_amount' => (int) $row->total_amount,
            ]);
    }
}
