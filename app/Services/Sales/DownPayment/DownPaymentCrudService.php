<?php

declare(strict_types=1);

namespace App\Services\Sales\DownPayment;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\DownPayments\Events\DownPaymentCreated;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Sales\DownPayment;
use App\Services\Base\BaseService;
use App\Services\Sales\DownPaymentNumberGenerator;

/**
 * Handles CRUD operations for down payments.
 *
 * Extracted from DownPaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\DownPaymentService The coordinator service
 */
class DownPaymentCrudService extends BaseService
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private DownPaymentNumberGenerator $dpNumberGenerator,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Create a new down payment.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): DownPayment
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $downPayment = new DownPayment($data);
            $downPayment->dp_number = $this->dpNumberGenerator->generate($data['type']);
            $downPayment->save();

            // Create journal entry for the down payment
            $this->createDownPaymentJournalEntry($downPayment);

            $result = $downPayment->fresh(['contact', 'cashAccount', 'journalEntry']);

            $this->eventDispatcher->dispatch(DownPaymentCreated::fromDownPayment(
                $result,
                $data['created_by'] ?? $this->getUserId() ?? 0
            ));

            return $result;
        }, ['type' => $data['type'], 'amount' => $data['amount'] ?? 0]);
    }

    /**
     * Update a down payment (only active with no applications).
     *
     * @param  array<string, mixed>  $data
     */
    public function update(DownPayment $downPayment, array $data): DownPayment
    {
        if ($downPayment->applications()->exists()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($downPayment, 'Tidak dapat mengubah down payment dengan aplikasi yang sudah ada.');
        }

        if ($downPayment->status !== DocumentStatus::Active) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Down Payment',
                'diubah',
                $downPayment->status->value,
                'active'
            );
        }

        return $this->executeInTransaction('update', function () use ($downPayment, $data) {
            // If amount or account changed, reverse old journal and create new
            $needsJournalUpdate = isset($data['amount']) && $data['amount'] !== $downPayment->amount
                || isset($data['cash_account_id']) && $data['cash_account_id'] !== $downPayment->cash_account_id;

            if ($needsJournalUpdate && $downPayment->journalEntry) {
                // Reverse the old journal entry
                $this->journalService->reverseEntry($downPayment->journalEntry);
                $downPayment->journal_entry_id = null;
            }

            $downPayment->fill($data);
            $downPayment->save();

            if ($needsJournalUpdate) {
                $this->createDownPaymentJournalEntry($downPayment);
            }

            return $downPayment->fresh(['contact', 'cashAccount', 'journalEntry']);
        }, ['down_payment_id' => $downPayment->id]);
    }

    /**
     * Delete a down payment (only if no applications).
     */
    public function delete(DownPayment $downPayment): bool
    {
        if ($downPayment->applications()->exists()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotDelete($downPayment, 'Tidak dapat menghapus down payment dengan aplikasi yang sudah ada.');
        }

        return $this->executeInTransaction('delete', function () use ($downPayment) {
            // Reverse application journal entries (safety: guard above prevents this, but defense-in-depth)
            foreach ($downPayment->applications as $application) {
                if ($application->journal_entry_id && $application->journalEntry) {
                    $this->journalService->reverseEntry($application->journalEntry);
                }
            }

            // Reverse main journal entry if exists
            if ($downPayment->journalEntry) {
                $this->journalService->reverseEntry($downPayment->journalEntry);
            }

            return $downPayment->delete();
        }, ['down_payment_id' => $downPayment->id]);
    }

    /**
     * Create journal entry for initial down payment receipt.
     */
    private function createDownPaymentJournalEntry(DownPayment $downPayment): void
    {
        // Get DP account based on type
        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            throw new \RuntimeException("DP account not found: {$dpAccountCode}. Please seed the chart of accounts.");
        }

        $lines = [];

        if ($downPayment->isReceivable()) {
            // Customer pays us: Dr Cash, Cr Uang Muka Penjualan (liability)
            $lines = [
                [
                    'account_id' => $downPayment->cash_account_id,
                    'debit' => $downPayment->amount,
                    'credit' => 0,
                    'description' => 'Down payment received from '.$downPayment->contact->name,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $downPayment->amount,
                    'description' => 'Down payment liability - '.$downPayment->dp_number,
                ],
            ];
        } else {
            // We pay vendor: Dr Uang Muka Pembelian (asset), Cr Cash
            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $downPayment->amount,
                    'credit' => 0,
                    'description' => 'Down payment advance - '.$downPayment->dp_number,
                ],
                [
                    'account_id' => $downPayment->cash_account_id,
                    'debit' => 0,
                    'credit' => $downPayment->amount,
                    'description' => 'Down payment to '.$downPayment->contact->name,
                ],
            ];
        }

        $journalEntry = $this->journalService->createEntry([
            'entry_date' => $downPayment->dp_date,
            'reference' => $downPayment->dp_number,
            'description' => ($downPayment->isReceivable() ? 'Down payment received: ' : 'Down payment paid: ').$downPayment->dp_number,
            'lines' => $lines,
        ], autoPost: true);

        $downPayment->journal_entry_id = $journalEntry->id;
        $downPayment->save();
    }
}
