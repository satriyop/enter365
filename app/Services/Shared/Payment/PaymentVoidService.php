<?php

declare(strict_types=1);

namespace App\Services\Shared\Payment;

use App\Contracts\Accounting\JournalServiceInterface;
use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Sales\Events\PaymentVoided;
use App\Enums\DocumentStatus;
use App\Models\Core\AuditLog;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;

/**
 * Handles payment void and payable reversal.
 *
 * Extracted from PaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Shared\PaymentService The coordinator service
 */
class PaymentVoidService extends BaseService
{
    public function __construct(
        private JournalServiceInterface $journalService,
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Void a payment and reverse its effects.
     */
    public function void(Payment $payment, ?string $reason = null): Payment
    {
        if ($payment->is_voided) {
            throw \App\Exceptions\Domain\BusinessRuleException::operationNotAllowed(
                'membatalkan pembayaran',
                'Pembayaran sudah dibatalkan'
            );
        }

        return $this->executeInTransaction('void', function () use ($payment, $reason) {
            // Load allocations if not already loaded
            $payment->loadMissing('allocations.allocatable');

            // Lock all payables via allocations
            $payables = collect();
            foreach ($payment->allocations as $allocation) {
                $payable = $allocation->allocatable;
                if ($payable) {
                    $locked = $payable::lockForUpdate()->find($payable->getKey());
                    $payables->put($allocation->id, $locked);
                }
            }

            // Fall back to legacy payable if no allocations
            if ($payables->isEmpty() && $payment->payable) {
                $payable = $payment->payable;
                $locked = $payable::lockForUpdate()->find($payable->getKey());
                $payables->put('legacy', $locked);
            }

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

            // Reverse payable amounts and status via allocations
            if ($payment->allocations->isNotEmpty()) {
                foreach ($payment->allocations as $allocation) {
                    $payable = $payables->get($allocation->id);
                    if ($payable) {
                        $this->updatePayableAfterVoid($payable, $allocation->amount);
                    }
                }
            } elseif ($payables->has('legacy')) {
                // Legacy path: single payable
                $this->updatePayableAfterVoid($payables->get('legacy'), $payment->amount);
            }

            // Reverse PPh withheld amount on Bill
            if ($payment->hasPphWithholding()) {
                $bill = $payables->first(fn ($p) => $p instanceof Bill);
                if ($bill) {
                    $bill->pph_withheld_amount = max(0, $bill->pph_withheld_amount - $payment->pph_amount);
                    $bill->save();
                }
            }

            AuditLog::log(AuditLog::ACTION_VOIDED, $payment, null, [
                'void_reason' => $reason,
                'amount' => $payment->amount,
            ]);

            // Dispatch void event
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

    /**
     * Update payable after a payment void (per-allocation amount).
     */
    private function updatePayableAfterVoid(Model $payable, int $allocationAmount): void
    {
        /** @var Invoice|Bill $payable */
        $payable->paid_amount = max(0, $payable->paid_amount - $allocationAmount);
        $payable->save();

        // Revert status if needed
        $this->revertPayableStatus($payable);
    }

    /**
     * Revert payable status after void.
     */
    private function revertPayableStatus(Model $payable): void
    {
        /** @var Invoice|Bill $payable */
        if ($payable->paid_amount <= 0) {
            $targetStatus = $payable instanceof Invoice
                ? DocumentStatus::Sent
                : DocumentStatus::Received;
        } else {
            $targetStatus = DocumentStatus::Partial;
        }

        if ($payable->stateMachine()->canTransitionTo($targetStatus)) {
            $payable->stateMachine()->transitionTo($targetStatus, [
                'user_id' => $this->getUserId(),
            ]);
        }
    }
}
