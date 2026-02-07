<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\AccountLookupServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Sales\WriteOffServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Sales\Invoice;
use App\Models\Sales\WriteOff;
use App\Services\Accounting\JournalService;
use App\Services\Base\BaseService;

class WriteOffService extends BaseService implements WriteOffServiceInterface
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger,
        private JournalService $journalService,
        private AccountLookupServiceInterface $accountLookup,
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function writeOff(Invoice $invoice, array $data): WriteOff
    {
        $allowedStatuses = [
            DocumentStatus::Sent,
            DocumentStatus::Partial,
            DocumentStatus::Overdue,
        ];

        if (! in_array($invoice->status, $allowedStatuses, true)) {
            throw BusinessRuleException::operationNotAllowed(
                'write-off',
                "Faktur dengan status '{$invoice->status->label()}' tidak dapat di-write off."
            );
        }

        $amount = $data['amount'];
        $outstanding = $invoice->getOutstandingAmount();

        if ($amount <= 0) {
            throw BusinessRuleException::operationNotAllowed(
                'write-off',
                'Jumlah write-off harus lebih dari nol.'
            );
        }

        if ($amount > $outstanding) {
            throw BusinessRuleException::operationNotAllowed(
                'write-off',
                "Jumlah write-off ({$amount}) melebihi sisa piutang ({$outstanding})."
            );
        }

        return $this->executeInTransaction('write_off', function () use ($invoice, $data, $amount) {
            $currency = $invoice->currency ?? 'IDR';
            $exchangeRate = (float) ($invoice->exchange_rate ?? 1);

            // Convert to base currency for the JE
            $baseAmount = $currency !== 'IDR' && $exchangeRate > 0
                ? (int) round($amount * $exchangeRate)
                : $amount;

            // Find required accounts
            $accounts = $this->accountLookup->findByCodesOrFail(
                ['5-3003', '1-1100'],
                'write-off'
            );

            $badDebtAccount = $accounts->get('5-3003');
            $receivableAccount = $invoice->receivableAccount ?? $accounts->get('1-1100');

            $writeOffDate = $data['write_off_date'] ?? now()->toDateString();

            // Build JE lines
            $lines = [
                [
                    'account_id' => $badDebtAccount->id,
                    'debit' => $baseAmount,
                    'credit' => 0,
                    'description' => "Write-off piutang {$invoice->invoice_number}",
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => $baseAmount,
                    'description' => "Write-off piutang {$invoice->invoice_number}",
                ],
            ];

            // Add currency metadata for FX invoices
            if ($currency !== 'IDR') {
                foreach ($lines as &$line) {
                    $line['currency_code'] = $currency;
                    $line['amount_currency'] = $amount;
                    $line['exchange_rate'] = $exchangeRate;
                }
                unset($line);
            }

            // Create the journal entry
            $journalEntry = $this->journalService->createEntry([
                'entry_date' => $writeOffDate,
                'description' => "Write-off piutang: {$invoice->invoice_number} - {$data['reason']}",
                'reference' => $invoice->invoice_number,
                'source_type' => 'write_off',
                'lines' => $lines,
            ], autoPost: true);

            // Create the write-off record
            $writeOff = WriteOff::create([
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'base_amount' => $baseAmount,
                'reason' => $data['reason'],
                'write_off_date' => $writeOffDate,
                'journal_entry_id' => $journalEntry->id,
                'created_by' => $this->getUserId(),
            ]);

            // Update invoice paid_amount — status transitions naturally
            $invoice->paid_amount += $amount;
            $invoice->save();

            // Transition invoice status
            $this->transitionInvoiceStatus($invoice);

            return $writeOff->fresh(['invoice', 'journalEntry']);
        }, ['invoice_id' => $invoice->id]);
    }

    public function reverse(WriteOff $writeOff, ?string $reason = null): WriteOff
    {
        if ($writeOff->trashed()) {
            throw BusinessRuleException::operationNotAllowed(
                'reverse write-off',
                'Write-off ini sudah dibatalkan.'
            );
        }

        return $this->executeInTransaction('reverse_write_off', function () use ($writeOff, $reason) {
            $invoice = $writeOff->invoice;

            // Reverse the journal entry
            if ($writeOff->journalEntry) {
                $this->journalService->reverseEntry(
                    $writeOff->journalEntry,
                    $reason ?? "Pembatalan write-off {$writeOff->write_off_number}"
                );
            }

            // Restore invoice paid_amount
            $invoice->paid_amount = max(0, $invoice->paid_amount - $writeOff->amount);
            $invoice->save();

            // Revert invoice status
            $this->revertInvoiceStatus($invoice);

            // Soft-delete the write-off
            $writeOff->delete();

            return $writeOff->fresh(['invoice', 'journalEntry']);
        }, ['write_off_id' => $writeOff->id, 'invoice_id' => $writeOff->invoice_id]);
    }

    private function transitionInvoiceStatus(Invoice $invoice): void
    {
        $isFullyPaid = $invoice->paid_amount >= $invoice->total_amount;
        $targetStatus = $isFullyPaid ? DocumentStatus::Paid : DocumentStatus::Partial;

        if ($invoice->stateMachine()->canTransitionTo($targetStatus)) {
            $invoice->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }
    }

    private function revertInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->paid_amount <= 0) {
            $targetStatus = DocumentStatus::Sent;
        } else {
            $targetStatus = DocumentStatus::Partial;
        }

        if ($invoice->stateMachine()->canTransitionTo($targetStatus)) {
            $invoice->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }
    }
}
