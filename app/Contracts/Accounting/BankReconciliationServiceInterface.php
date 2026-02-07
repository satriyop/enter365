<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliationSession;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Shared\Payment;
use Illuminate\Support\Collection;

/**
 * Interface for Bank Reconciliation operations.
 */
interface BankReconciliationServiceInterface
{
    /**
     * Create a bank transaction for reconciliation.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BankTransaction;

    /**
     * Delete a bank transaction.
     */
    public function delete(BankTransaction $transaction): void;

    // --- Matching ---

    /**
     * Match a bank transaction to a payment.
     */
    public function matchToPayment(BankTransaction $txn, Payment $payment): void;

    /**
     * Match a bank transaction to a journal entry line.
     */
    public function matchToJournalLine(BankTransaction $txn, JournalEntryLine $line): void;

    /**
     * Remove match from a bank transaction.
     */
    public function unmatch(BankTransaction $txn): void;

    /**
     * Mark a matched transaction as reconciled.
     */
    public function reconcile(BankTransaction $txn): void;

    /**
     * Reconcile multiple transactions at once.
     *
     * @param  array<int>  $transactionIds
     * @return int Number of reconciled transactions
     */
    public function batchReconcile(array $transactionIds): int;

    // --- Auto-suggestion ---

    /**
     * Suggest matches for unmatched bank transactions.
     *
     * @return Collection<int, object{transaction_id: int, match_type: string, match_id: int, confidence: int, rule: string}>
     */
    public function suggestMatches(Account $account, ?string $asOfDate = null): Collection;

    // --- Session management ---

    /**
     * Start a new reconciliation session.
     */
    public function startSession(Account $account, string $statementDate, int $statementBalance): BankReconciliationSession;

    /**
     * Complete a reconciliation session.
     */
    public function completeSession(BankReconciliationSession $session): BankReconciliationSession;
}
