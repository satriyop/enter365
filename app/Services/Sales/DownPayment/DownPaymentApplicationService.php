<?php

declare(strict_types=1);

namespace App\Services\Sales\DownPayment;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Domain\Sales\DownPayments\Events\DownPaymentApplied;
use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Services\Base\BaseService;

/**
 * Handles applying and unapplying down payments to invoices and bills.
 *
 * Extracted from DownPaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\DownPaymentService The coordinator service
 */
class DownPaymentApplicationService extends BaseService
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private InvoiceServiceInterface $invoiceService,
        private BillServiceInterface $billService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Apply down payment to an invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToInvoice(DownPayment $downPayment, Invoice $invoice, array $data): DownPaymentApplication
    {
        if (! $downPayment->canBeApplied()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Down Payment',
                'diterapkan',
                $downPayment->status->value,
                'active'
            );
        }

        if ($downPayment->type !== DownPayment::TYPE_RECEIVABLE) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menerapkan down payment ke invoice',
                'Down payment harus tipe receivable'
            );
        }

        if ($invoice->contact_id !== $downPayment->contact_id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menerapkan down payment',
                'Down payment dan invoice harus milik contact yang sama'
            );
        }

        $amount = $data['amount'];
        $outstandingAmount = $invoice->getOutstandingAmount();

        if ($amount > $downPayment->remaining_amount) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah aplikasi down payment',
                $amount,
                $downPayment->remaining_amount,
                'exceeds'
            );
        }

        if ($amount > $outstandingAmount) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah aplikasi down payment',
                $amount,
                $outstandingAmount,
                'exceeds'
            );
        }

        return $this->executeInTransaction('apply_to_invoice', function () use ($downPayment, $invoice, $data, $amount) {
            // Pessimistic lock to prevent concurrent over-application
            $downPayment = DownPayment::lockForUpdate()->findOrFail($downPayment->id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);

            // Re-validate after acquiring lock
            if ($amount > $downPayment->remaining_amount) {
                throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                    'Jumlah aplikasi down payment',
                    $amount,
                    $downPayment->remaining_amount,
                    'exceeds'
                );
            }

            $application = new DownPaymentApplication([
                'down_payment_id' => $downPayment->id,
                'applicable_type' => Invoice::class,
                'applicable_id' => $invoice->id,
                'amount' => $amount,
                'applied_date' => $data['applied_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $application->save();

            // Create journal entry for application
            $this->createApplicationJournalEntry($application);

            // Update down payment applied amount
            $downPayment->applied_amount += $amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Update invoice paid amount and status via service
            $invoice->paid_amount += $amount;
            $invoice->save();
            $this->invoiceService->updatePaymentStatus($invoice);

            $result = $application->fresh(['downPayment', 'applicable', 'journalEntry']);

            $this->eventDispatcher->dispatch(DownPaymentApplied::fromApplication(
                $result,
                $data['created_by'] ?? $this->getUserId() ?? 0
            ));

            return $result;
        }, ['down_payment_id' => $downPayment->id, 'invoice_id' => $invoice->id, 'amount' => $amount]);
    }

    /**
     * Apply down payment to a bill.
     *
     * @param  array<string, mixed>  $data
     */
    public function applyToBill(DownPayment $downPayment, Bill $bill, array $data): DownPaymentApplication
    {
        if (! $downPayment->canBeApplied()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Down Payment',
                'diterapkan',
                $downPayment->status->value,
                'active'
            );
        }

        if ($downPayment->type !== DownPayment::TYPE_PAYABLE) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menerapkan down payment ke bill',
                'Down payment harus tipe payable'
            );
        }

        if ($bill->contact_id !== $downPayment->contact_id) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menerapkan down payment',
                'Down payment dan bill harus milik contact yang sama'
            );
        }

        $amount = $data['amount'];
        $outstandingAmount = $bill->getOutstandingAmount();

        if ($amount > $downPayment->remaining_amount) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah aplikasi down payment',
                $amount,
                $downPayment->remaining_amount,
                'exceeds'
            );
        }

        if ($amount > $outstandingAmount) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah aplikasi down payment',
                $amount,
                $outstandingAmount,
                'exceeds'
            );
        }

        return $this->executeInTransaction('apply_to_bill', function () use ($downPayment, $bill, $data, $amount) {
            // Pessimistic lock to prevent concurrent over-application
            $downPayment = DownPayment::lockForUpdate()->findOrFail($downPayment->id);
            $bill = Bill::lockForUpdate()->findOrFail($bill->id);

            // Re-validate after acquiring lock
            if ($amount > $downPayment->remaining_amount) {
                throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                    'Jumlah aplikasi down payment',
                    $amount,
                    $downPayment->remaining_amount,
                    'exceeds'
                );
            }

            $application = new DownPaymentApplication([
                'down_payment_id' => $downPayment->id,
                'applicable_type' => Bill::class,
                'applicable_id' => $bill->id,
                'amount' => $amount,
                'applied_date' => $data['applied_date'] ?? now()->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $application->save();

            // Create journal entry for application
            $this->createApplicationJournalEntry($application);

            // Update down payment applied amount
            $downPayment->applied_amount += $amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Update bill paid amount and status via service
            $bill->paid_amount += $amount;
            $bill->save();
            $this->billService->updatePaymentStatus($bill);

            $result = $application->fresh(['downPayment', 'applicable', 'journalEntry']);

            $this->eventDispatcher->dispatch(DownPaymentApplied::fromApplication(
                $result,
                $data['created_by'] ?? $this->getUserId() ?? 0
            ));

            return $result;
        }, ['down_payment_id' => $downPayment->id, 'bill_id' => $bill->id, 'amount' => $amount]);
    }

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPaymentApplication $application): bool
    {
        return $this->executeInTransaction('unapply', function () use ($application) {
            // Pessimistic lock to prevent concurrent modifications
            $downPayment = DownPayment::lockForUpdate()->findOrFail($application->down_payment_id);
            $applicable = $application->applicable;

            // Reverse journal entry
            if ($application->journalEntry) {
                $this->journalService->reverseEntry($application->journalEntry);
            }

            // Restore down payment applied amount
            $downPayment->applied_amount -= $application->amount;
            $downPayment->updateStatus();
            $downPayment->save();

            // Restore document paid amount and status via service
            if ($applicable instanceof Invoice || $applicable instanceof Bill) {
                $applicable->paid_amount -= $application->amount;
                $applicable->save();

                if ($applicable instanceof Invoice) {
                    $this->invoiceService->updatePaymentStatus($applicable);
                } else {
                    $this->billService->updatePaymentStatus($applicable);
                }
            }

            return $application->delete();
        }, ['application_id' => $application->id, 'down_payment_id' => $application->down_payment_id]);
    }

    /**
     * Create journal entry for applying down payment.
     */
    private function createApplicationJournalEntry(DownPaymentApplication $application): void
    {
        $downPayment = $application->downPayment;
        /** @var Invoice|Bill $applicable */
        $applicable = $application->applicable;

        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            throw new \RuntimeException("DP account not found: {$dpAccountCode}. Please seed the chart of accounts.");
        }

        $lines = [];

        if ($downPayment->isReceivable() && $applicable instanceof Invoice) {
            // Apply to invoice: Dr Uang Muka Penjualan, Cr Piutang
            $receivableAccount = $applicable->receivableAccount ?? Account::where('code', '1-1100')->first();

            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $application->amount,
                    'credit' => 0,
                    'description' => 'DP applied to '.$applicable->invoice_number,
                ],
                [
                    'account_id' => $receivableAccount->id,
                    'debit' => 0,
                    'credit' => $application->amount,
                    'description' => 'Reduce receivable - '.$applicable->invoice_number,
                ],
            ];
        } elseif ($downPayment->isPayable() && $applicable instanceof Bill) {
            // Apply to bill: Dr Hutang, Cr Uang Muka Pembelian
            $payableAccount = $applicable->payableAccount ?? Account::where('code', '2-1100')->first();

            $lines = [
                [
                    'account_id' => $payableAccount->id,
                    'debit' => $application->amount,
                    'credit' => 0,
                    'description' => 'Reduce payable - '.$applicable->bill_number,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $application->amount,
                    'description' => 'DP applied to '.$applicable->bill_number,
                ],
            ];
        }

        if (! empty($lines)) {
            $journalEntry = $this->journalService->createEntry([
                'entry_date' => $application->applied_date,
                'reference' => $downPayment->dp_number,
                'description' => 'Apply DP '.$downPayment->dp_number.' to '.
                    ($applicable instanceof Invoice ? $applicable->invoice_number : $applicable->bill_number),
                'lines' => $lines,
            ], autoPost: true);

            $application->journal_entry_id = $journalEntry->id;
            $application->save();
        }
    }
}
