<?php

declare(strict_types=1);

namespace App\Services\Sales\DownPayment;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\DownPayments\Events\DownPaymentCancelled;
use App\Domain\Sales\DownPayments\Events\DownPaymentRefunded;
use App\Enums\DocumentStatus;
use App\Models\Accounting\Account;
use App\Models\Sales\DownPayment;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use App\Services\Sales\PaymentNumberGenerator;

/**
 * Handles lifecycle operations for down payments (refund, cancel, availability).
 *
 * Extracted from DownPaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Sales\DownPaymentService The coordinator service
 */
class DownPaymentLifecycleService extends BaseService
{
    public function __construct(
        private JournalServiceInterface $journalService,
        private PaymentNumberGenerator $paymentNumberGenerator,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
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
            // Pessimistic lock to prevent concurrent refund
            $downPayment = DownPayment::lockForUpdate()->findOrFail($downPayment->id);

            // Re-validate after acquiring lock
            if ($refundAmount > $downPayment->remaining_amount) {
                throw \App\Exceptions\Domain\BusinessRuleException::quantityValidation(
                    'Jumlah refund',
                    $refundAmount,
                    $downPayment->remaining_amount,
                    'exceeds'
                );
            }

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

            // Update down payment — only mark as Refunded if fully refunded
            $downPayment->refund_payment_id = $payment->id;
            $downPayment->refunded_at = now();
            if ($refundAmount >= $downPayment->remaining_amount) {
                $downPayment->status = DocumentStatus::Refunded;
            }
            // Partial refund: status stays Active, remaining_amount is reduced via applied_amount
            $downPayment->applied_amount += $refundAmount;
            $downPayment->save();

            // Create refund journal entry
            $this->createRefundJournalEntry($downPayment, $payment, $refundAmount);

            $this->eventDispatcher->dispatch(DownPaymentRefunded::fromDownPayment(
                $downPayment,
                $payment,
                $refundAmount,
                $data['created_by'] ?? $this->getUserId() ?? 0
            ));

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

            $this->eventDispatcher->dispatch(DownPaymentCancelled::fromDownPayment(
                $downPayment,
                $reason,
                $this->getUserId() ?? 0
            ));

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
