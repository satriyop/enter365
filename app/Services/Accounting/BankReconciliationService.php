<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Contracts\Accounting\BankReconciliationServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\BankTransactionStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliationSession;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationService extends BaseService implements BankReconciliationServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new bank transaction.
     *
     * @param  array{account_id: int, transaction_date: string, description: string, reference?: string, debit: int, credit: int, import_batch?: string}  $data
     */
    public function create(array $data): BankTransaction
    {
        return $this->executeInTransaction('create_bank_transaction', function () use ($data) {
            return BankTransaction::create([
                ...$data,
                'status' => BankTransactionStatus::Unmatched,
                'created_by' => $this->getUserId(),
            ]);
        }, ['account_id' => $data['account_id']]);
    }

    /**
     * Delete a bank transaction.
     */
    public function delete(BankTransaction $transaction): void
    {
        $this->executeInTransaction('delete_bank_transaction', function () use ($transaction) {
            if ($transaction->isReconciled()) {
                throw BusinessRuleException::operationNotAllowed(
                    'hapus transaksi bank',
                    'Tidak dapat menghapus transaksi yang sudah direkonsiliasi.'
                );
            }

            $transaction->delete();
        }, ['transaction_id' => $transaction->id]);
    }

    // --- Matching ---

    public function matchToPayment(BankTransaction $txn, Payment $payment): void
    {
        $this->guardNotReconciled($txn);

        $txn->update([
            'status' => BankTransactionStatus::Matched,
            'matched_payment_id' => $payment->id,
            'matched_journal_line_id' => null,
            'match_confidence' => 100,
            'match_rule' => 'manual',
        ]);
    }

    public function matchToJournalLine(BankTransaction $txn, JournalEntryLine $line): void
    {
        $this->guardNotReconciled($txn);

        $txn->update([
            'status' => BankTransactionStatus::Matched,
            'matched_journal_line_id' => $line->id,
            'matched_payment_id' => null,
            'match_confidence' => 100,
            'match_rule' => 'manual',
        ]);
    }

    public function unmatch(BankTransaction $txn): void
    {
        if ($txn->isReconciled()) {
            throw BusinessRuleException::operationNotAllowed(
                'unmatch transaksi bank',
                'Tidak dapat unmatch transaksi yang sudah direkonsiliasi.'
            );
        }

        $txn->update([
            'status' => BankTransactionStatus::Unmatched,
            'matched_payment_id' => null,
            'matched_journal_line_id' => null,
            'match_confidence' => null,
            'match_rule' => null,
        ]);
    }

    public function reconcile(BankTransaction $txn): void
    {
        if ($txn->status !== BankTransactionStatus::Matched) {
            throw BusinessRuleException::operationNotAllowed(
                'rekonsiliasi transaksi bank',
                'Transaksi harus di-match terlebih dahulu sebelum direkonsiliasi.'
            );
        }

        $txn->update([
            'status' => BankTransactionStatus::Reconciled,
            'reconciled_at' => now(),
            'reconciled_by' => $this->getUserId(),
        ]);
    }

    public function batchReconcile(array $transactionIds): int
    {
        $count = 0;

        foreach ($transactionIds as $id) {
            $txn = BankTransaction::find($id);
            if ($txn && $txn->status === BankTransactionStatus::Matched) {
                $this->reconcile($txn);
                $count++;
            }
        }

        return $count;
    }

    // --- Auto-suggestion ---

    public function suggestMatches(Account $account, ?string $asOfDate = null): Collection
    {
        $asOfDate = $asOfDate ?? now()->toDateString();
        $suggestions = collect();

        $unmatchedTxns = BankTransaction::where('account_id', $account->id)
            ->where('status', BankTransactionStatus::Unmatched)
            ->get();

        foreach ($unmatchedTxns as $txn) {
            $amount = $txn->getNetAmount();

            // 1. Reference match on payments (highest confidence)
            if ($txn->reference) {
                $refPayment = Payment::where('cash_account_id', $account->id)
                    ->where('payment_number', $txn->reference)
                    ->where('is_voided', false)
                    ->whereDoesntHave('bankTransaction')
                    ->first();

                if ($refPayment) {
                    $suggestions->push((object) [
                        'transaction_id' => $txn->id,
                        'match_type' => 'payment',
                        'match_id' => $refPayment->id,
                        'confidence' => 99,
                        'rule' => 'reference',
                    ]);

                    continue;
                }
            }

            // 2. Exact amount match on payments
            $amountPayment = Payment::where('cash_account_id', $account->id)
                ->where(function ($q) use ($amount) {
                    if ($amount > 0) {
                        $q->where('amount', $amount);
                    } else {
                        $q->where('amount', abs($amount));
                    }
                })
                ->where('is_voided', false)
                ->whereDoesntHave('bankTransaction')
                ->first();

            if ($amountPayment) {
                $suggestions->push((object) [
                    'transaction_id' => $txn->id,
                    'match_type' => 'payment',
                    'match_id' => $amountPayment->id,
                    'confidence' => 90,
                    'rule' => 'exact_amount',
                ]);

                continue;
            }

            // 3. JE line fallback: exact amount match on posted JE lines
            $jeLineQuery = DB::table('journal_entry_lines as jel')
                ->join('journal_entries as je', 'je.id', '=', 'jel.journal_entry_id')
                ->where('jel.account_id', $account->id)
                ->where('je.is_posted', true)
                ->whereNull('je.deleted_at')
                ->whereNotIn('jel.id', function ($q) {
                    $q->select('matched_journal_line_id')
                        ->from('bank_transactions')
                        ->whereNotNull('matched_journal_line_id');
                });

            if ($amount > 0) {
                $jeLineQuery->where('jel.debit', abs($amount));
            } else {
                $jeLineQuery->where('jel.credit', abs($amount));
            }

            $jeLine = $jeLineQuery->select('jel.id')->first();

            if ($jeLine) {
                $suggestions->push((object) [
                    'transaction_id' => $txn->id,
                    'match_type' => 'journal_line',
                    'match_id' => $jeLine->id,
                    'confidence' => 80,
                    'rule' => 'exact_amount',
                ]);
            }
        }

        return $suggestions->sortByDesc('confidence')->values();
    }

    // --- Session management ---

    public function startSession(Account $account, string $statementDate, int $statementBalance): BankReconciliationSession
    {
        return BankReconciliationSession::create([
            'account_id' => $account->id,
            'statement_date' => $statementDate,
            'statement_balance' => $statementBalance,
            'status' => BankReconciliationSession::STATUS_IN_PROGRESS,
            'created_by' => $this->getUserId(),
        ]);
    }

    public function completeSession(BankReconciliationSession $session): BankReconciliationSession
    {
        if ($session->isCompleted()) {
            throw BusinessRuleException::operationNotAllowed(
                'selesaikan sesi rekonsiliasi',
                'Sesi ini sudah diselesaikan.'
            );
        }

        $reconciledCount = BankTransaction::where('session_id', $session->id)
            ->where('status', BankTransactionStatus::Reconciled)
            ->count();

        $session->update([
            'status' => BankReconciliationSession::STATUS_COMPLETED,
            'reconciled_count' => $reconciledCount,
            'completed_at' => now(),
            'completed_by' => $this->getUserId(),
        ]);

        return $session->refresh();
    }

    private function guardNotReconciled(BankTransaction $txn): void
    {
        if ($txn->isReconciled()) {
            throw BusinessRuleException::operationNotAllowed(
                'match transaksi bank',
                'Tidak dapat mengubah match pada transaksi yang sudah direkonsiliasi.'
            );
        }
    }
}
