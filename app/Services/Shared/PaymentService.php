<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Shared\PaymentServiceInterface;
use App\Domain\Purchasing\Bills\Events\BillFullyPaid;
use App\Domain\Sales\Events\PaymentReceived;
use App\Domain\Sales\Events\PaymentVoided;
use App\Domain\Sales\Invoices\Events\InvoiceFullyPaid;
use App\Enums\DocumentStatus;
use App\Models\Core\AuditLog;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;

class PaymentService extends BaseService implements PaymentServiceInterface
{
    public function __construct(
        private JournalServiceInterface $journalService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    public function create(array $data): Payment
    {
        return $this->executeInTransaction('create', function () use ($data) {
            $payableType = null;
            $payableId = null;
            $payable = null;

            // Handle invoice allocation (with pessimistic lock)
            if (isset($data['invoice_id'])) {
                $invoice = Invoice::lockForUpdate()->findOrFail($data['invoice_id']);
                $this->validateInvoicePayment($invoice, $data['amount']);
                $payableType = Invoice::class;
                $payableId = $invoice->id;
                $payable = $invoice;
                unset($data['invoice_id']);
            }

            // Handle bill allocation (with pessimistic lock)
            if (isset($data['bill_id'])) {
                $bill = Bill::lockForUpdate()->findOrFail($data['bill_id']);
                $this->validateBillPayment($bill, $data['amount']);
                $payableType = Bill::class;
                $payableId = $bill->id;
                $payable = $bill;
                unset($data['bill_id']);
            }

            // Create payment record
            $payment = Payment::create([
                ...$data,
                'payment_number' => Payment::generatePaymentNumber($data['type']),
                'payable_type' => $payableType,
                'payable_id' => $payableId,
                'created_by' => $this->getUserId(),
            ]);

            // Create journal entry (JournalService now only creates the journal)
            $journalEntry = $this->journalService->postPayment($payment);
            $payment->update(['journal_entry_id' => $journalEntry->id]);

            // Update payable amounts and status
            if ($payable) {
                $this->updatePayableAfterPayment($payable, $payment);
            }

            return $payment->load(['contact', 'cashAccount', 'journalEntry.lines.account']);
        }, ['type' => $data['type'], 'amount' => $data['amount']]);
    }

    public function createForInvoice(Invoice $invoice, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'invoice_id' => $invoice->id,
        ]);
    }

    public function createForBill(Bill $bill, array $data): Payment
    {
        return $this->create([
            ...$data,
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'bill_id' => $bill->id,
        ]);
    }

    public function void(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->is_voided) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'membatalkan pembayaran',
                'Pembayaran sudah dibatalkan'
            );
        }

        return $this->executeInTransaction('void', function () use ($payment, $reason) {
            // Lock the payable to prevent concurrent payment modifications
            $payable = $payment->payable;
            if ($payable) {
                /** @var \App\Models\Sales\Invoice|\App\Models\Purchasing\Bill $payable */
                $payable = $payable::lockForUpdate()->find($payable->getKey());
            }
            $previousPaidAmount = $payable?->paid_amount ?? 0;

            // Reverse journal entry
            if ($payment->journalEntry) {
                $this->journalService->reverseEntry(
                    $payment->journalEntry,
                    "Pembatalan pembayaran: {$payment->payment_number}"
                );
            }

            // Update payment as voided
            $payment->update([
                'is_voided' => true,
                'voided_at' => now(),
                'voided_by' => $this->getUserId(),
                'void_reason' => $reason,
            ]);

            // Reverse payable amounts and status
            if ($payable) {
                $this->updatePayableAfterVoid($payable, $payment, $previousPaidAmount);
            }

            AuditLog::log(AuditLog::ACTION_VOIDED, $payment, null, [
                'void_reason' => $reason,
                'amount' => $payment->amount,
            ]);

            // Dispatch void event for all voided payments
            Event::dispatch(new PaymentVoided(
                invoiceId: $payment->payable_type === Invoice::class ? $payment->payable_id : null,
                paymentId: $payment->id,
                amount: $payment->amount,
                currency: 'IDR',
                customerId: $payment->contact_id,
                userId: $this->getUserId() ?? $payment->created_by,
                voidedAt: now(),
                reason: $reason
            ));

            return $payment->fresh(['contact', 'cashAccount']);
        }, ['payment_id' => $payment->id]);
    }

    public function getForInvoice(Invoice $invoice): Collection
    {
        return Payment::query()
            ->where('payable_type', Invoice::class)
            ->where('payable_id', $invoice->id)
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function getForBill(Bill $bill): Collection
    {
        return Payment::query()
            ->where('payable_type', Bill::class)
            ->where('payable_id', $bill->id)
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    public function getOutstandingAmount(Model $payable): int
    {
        if (! method_exists($payable, 'getOutstandingAmount')) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'menggunakan model sebagai payable',
                'Model does not support outstanding amount calculation'
            );
        }

        return $payable->getOutstandingAmount();
    }

    public function canReceivePayment(Model $payable): bool
    {
        if ($payable instanceof Invoice) {
            return in_array($payable->status, [
                DocumentStatus::Sent,
                DocumentStatus::Partial,
                DocumentStatus::Overdue,
            ]);
        }

        if ($payable instanceof Bill) {
            return in_array($payable->status, [
                DocumentStatus::Received,
                DocumentStatus::Partial,
                DocumentStatus::Overdue,
            ]);
        }

        return false;
    }

    /**
     * Validate that an invoice can receive the payment amount.
     */
    private function validateInvoicePayment(Invoice $invoice, int $amount): void
    {
        if (! $this->canReceivePayment($invoice)) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Invoice',
                'menerima pembayaran',
                $invoice->status->value,
                'approved atau partial'
            );
        }

        if ($amount > $invoice->getOutstandingAmount()) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah pembayaran',
                $amount,
                $invoice->getOutstandingAmount(),
                'exceeds'
            );
        }
    }

    /**
     * Validate that a bill can receive the payment amount.
     */
    private function validateBillPayment(Bill $bill, int $amount): void
    {
        if (! $this->canReceivePayment($bill)) {
            throw \App\Exceptions\Domain\StateTransitionException::wrongStateForOperation(
                'Bill',
                'menerima pembayaran',
                $bill->status->value,
                'approved atau partial'
            );
        }

        if ($amount > $bill->getOutstandingAmount()) {
            throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                'Jumlah pembayaran',
                $amount,
                $bill->getOutstandingAmount(),
                'exceeds'
            );
        }
    }

    /**
     * Update payable (invoice/bill) after a payment is created.
     */
    private function updatePayableAfterPayment(Model $payable, Payment $payment): void
    {
        $previousPaidAmount = $payable->paid_amount;
        $payable->paid_amount += $payment->amount;
        $payable->save();

        // Dispatch payment received event for incoming payments
        if ($payment->type === Payment::TYPE_RECEIVE && $payable instanceof Invoice) {
            Event::dispatch(PaymentReceived::fromPayment(
                invoice: $payable,
                paymentId: $payment->id,
                amount: $payment->amount,
                userId: $this->getUserId() ?? $payment->created_by
            ));
        }

        // Transition status based on payment
        $this->transitionPayableStatus($payable, $previousPaidAmount);
    }

    /**
     * Update payable after a payment is voided.
     */
    private function updatePayableAfterVoid(Model $payable, Payment $payment, int $previousPaidAmount): void
    {
        // Refresh to get latest state
        $payable->refresh();

        $payable->paid_amount = max(0, $payable->paid_amount - $payment->amount);
        $payable->save();

        // Revert status if needed
        $this->revertPayableStatus($payable);
    }

    /**
     * Transition payable status after payment.
     */
    private function transitionPayableStatus(Model $payable, int $previousPaidAmount): void
    {
        $isFullyPaid = $payable->paid_amount >= $payable->total_amount;
        $targetStatus = $isFullyPaid ? DocumentStatus::Paid : DocumentStatus::Partial;

        if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
            $payable->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }

        // Dispatch fully paid events
        if ($isFullyPaid && $previousPaidAmount < $payable->total_amount) {
            if ($payable instanceof Invoice) {
                Event::dispatch(InvoiceFullyPaid::fromInvoice($payable, $this->getUserId()));
            } elseif ($payable instanceof Bill) {
                Event::dispatch(BillFullyPaid::fromBill($payable, $this->getUserId()));
            }
        }
    }

    /**
     * Revert payable status after void.
     */
    private function revertPayableStatus(Model $payable): void
    {
        // Determine appropriate status based on remaining paid amount
        if ($payable->paid_amount <= 0) {
            // No payments remaining - revert to original posted status
            $targetStatus = $payable instanceof Invoice
                ? DocumentStatus::Sent
                : DocumentStatus::Received;
        } else {
            // Partial payment remaining
            $targetStatus = DocumentStatus::Partial;
        }

        if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
            $payable->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }
    }
}
