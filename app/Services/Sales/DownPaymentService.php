<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Sales\DownPaymentServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;

class DownPaymentService extends BaseService implements DownPaymentServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private InvoiceServiceInterface $invoiceService,
        private BillServiceInterface $billService,
        private DownPaymentNumberGenerator $dpNumberGenerator,
        private PaymentNumberGenerator $paymentNumberGenerator,
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

            return $downPayment->fresh(['contact', 'cashAccount', 'journalEntry']);
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
            // Reverse journal entry if exists
            if ($downPayment->journalEntry) {
                $this->journalService->reverseEntry($downPayment->journalEntry);
            }

            return $downPayment->delete();
        }, ['down_payment_id' => $downPayment->id]);
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

            return $application->fresh(['downPayment', 'applicable', 'journalEntry']);
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

            return $application->fresh(['downPayment', 'applicable', 'journalEntry']);
        }, ['down_payment_id' => $downPayment->id, 'bill_id' => $bill->id, 'amount' => $amount]);
    }

    /**
     * Unapply (reverse) a down payment application.
     */
    public function unapply(DownPaymentApplication $application): bool
    {
        return $this->executeInTransaction('unapply', function () use ($application) {
            $downPayment = $application->downPayment;
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
     * Refund remaining down payment balance.
     *
     * @param  array<string, mixed>  $data
     */
    public function refund(DownPayment $downPayment, array $data): Payment
    {
        if (! $downPayment->canBeRefunded()) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Down Payment',
                'direfund',
                $downPayment->status->value,
                'active dengan sisa saldo'
            );
        }

        $refundAmount = $data['amount'] ?? $downPayment->remaining_amount;

        if ($refundAmount > $downPayment->remaining_amount) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah refund',
                $refundAmount,
                $downPayment->remaining_amount,
                'exceeds'
            );
        }

        return $this->executeInTransaction('refund', function () use ($downPayment, $data, $refundAmount) {
            // Create refund payment
            // Receivable DP: we refund TO customer (outgoing for us)
            // Payable DP: vendor refunds TO us (incoming for us)
            $paymentType = $downPayment->isReceivable() ? Payment::TYPE_SEND : Payment::TYPE_RECEIVE;

            $payment = new Payment([
                'payment_number' => $this->paymentNumberGenerator->generate($paymentType),
                'type' => $paymentType,
                'contact_id' => $downPayment->contact_id,
                'payment_date' => $data['refund_date'] ?? now()->toDateString(),
                'amount' => $refundAmount,
                'payment_method' => $data['payment_method'] ?? $downPayment->payment_method,
                'reference' => 'Refund: '.$downPayment->dp_number,
                'notes' => $data['notes'] ?? 'Down payment refund',
                'cash_account_id' => $data['cash_account_id'] ?? $downPayment->cash_account_id,
                'created_by' => $data['created_by'] ?? null,
            ]);
            $payment->save();

            // Update down payment
            $downPayment->refund_payment_id = $payment->id;
            $downPayment->refunded_at = now();
            $downPayment->status = DocumentStatus::Refunded;
            $downPayment->save();

            // Create refund journal entry
            $this->createRefundJournalEntry($downPayment, $payment, $refundAmount);

            return $payment;
        }, ['down_payment_id' => $downPayment->id, 'refund_amount' => $refundAmount]);
    }

    /**
     * Cancel a down payment (only if no applications).
     */
    public function cancel(DownPayment $downPayment, ?string $reason = null): DownPayment
    {
        if ($downPayment->applications()->exists()) {
            throw \App\Exceptions\Domain\DocumentLockedException::cannotEdit($downPayment, 'Tidak dapat membatalkan down payment dengan aplikasi yang sudah ada.');
        }

        if ($downPayment->status !== DocumentStatus::Active) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Down Payment',
                'dibatalkan',
                $downPayment->status->value,
                'active'
            );
        }

        return $this->executeInTransaction('cancel', function () use ($downPayment, $reason) {
            // Reverse journal entry
            if ($downPayment->journalEntry) {
                $this->journalService->reverseEntry($downPayment->journalEntry);
            }

            $downPayment->status = DocumentStatus::Cancelled;
            if ($reason) {
                $downPayment->notes = ($downPayment->notes ? $downPayment->notes."\n" : '').'Cancelled: '.$reason;
            }
            $downPayment->save();

            return $downPayment;
        }, ['down_payment_id' => $downPayment->id]);
    }

    /**
     * Get available down payments for a contact.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, DownPayment>
     */
    public function getAvailableForContact(int $contactId, string $type): \Illuminate\Database\Eloquent\Collection
    {
        return DownPayment::query()
            ->where('contact_id', $contactId)
            ->where('type', $type)
            ->where('status', DocumentStatus::Active->value)
            ->whereRaw('applied_amount < amount')
            ->orderBy('dp_date')
            ->get();
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

    /**
     * Create journal entry for refund.
     */
    private function createRefundJournalEntry(DownPayment $downPayment, Payment $payment, int $refundAmount): void
    {
        $dpAccountCode = $downPayment->getDpAccountCode();
        $dpAccount = Account::where('code', $dpAccountCode)->first();

        if (! $dpAccount) {
            throw new \RuntimeException("DP account not found: {$dpAccountCode}. Please seed the chart of accounts.");
        }

        $lines = [];

        if ($downPayment->isReceivable()) {
            // Refund to customer: Dr Uang Muka Penjualan, Cr Cash
            $lines = [
                [
                    'account_id' => $dpAccount->id,
                    'debit' => $refundAmount,
                    'credit' => 0,
                    'description' => 'DP refund - '.$downPayment->dp_number,
                ],
                [
                    'account_id' => $payment->cash_account_id,
                    'debit' => 0,
                    'credit' => $refundAmount,
                    'description' => 'Refund to '.$downPayment->contact->name,
                ],
            ];
        } else {
            // Refund from vendor: Dr Cash, Cr Uang Muka Pembelian
            $lines = [
                [
                    'account_id' => $payment->cash_account_id,
                    'debit' => $refundAmount,
                    'credit' => 0,
                    'description' => 'Refund from '.$downPayment->contact->name,
                ],
                [
                    'account_id' => $dpAccount->id,
                    'debit' => 0,
                    'credit' => $refundAmount,
                    'description' => 'DP refund - '.$downPayment->dp_number,
                ],
            ];
        }

        $journalEntry = $this->journalService->createEntry([
            'entry_date' => $payment->payment_date,
            'reference' => $payment->payment_number,
            'description' => 'Down payment refund: '.$downPayment->dp_number,
            'lines' => $lines,
        ], autoPost: true);

        $payment->journal_entry_id = $journalEntry->id;
        $payment->save();
    }
}
