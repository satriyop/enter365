<?php

declare(strict_types=1);

namespace App\Services\Shared\Payment;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Base\BaseService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Handles payment query and eligibility checks.
 *
 * Extracted from PaymentService as part of the Coordinator Pattern refactoring.
 *
 * @see \App\Services\Shared\PaymentService The coordinator service
 */
class PaymentQueryService extends BaseService
{
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($eventDispatcher, $logger);
    }

    /**
     * Get all payments for an invoice.
     *
     * @return Collection<int, Payment>
     */
    public function getForInvoice(Invoice $invoice): Collection
    {
        return Payment::query()
            ->where(function ($q) use ($invoice) {
                // Legacy path
                $q->where(function ($q2) use ($invoice) {
                    $q2->where('payable_type', Invoice::class)
                        ->where('payable_id', $invoice->id);
                })
                // Multi-allocation path
                    ->orWhereHas('allocations', function ($q2) use ($invoice) {
                        $q2->where('allocatable_type', 'invoice')
                            ->where('allocatable_id', $invoice->id);
                    });
            })
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get all payments for a bill.
     *
     * @return Collection<int, Payment>
     */
    public function getForBill(Bill $bill): Collection
    {
        return Payment::query()
            ->where(function ($q) use ($bill) {
                $q->where(function ($q2) use ($bill) {
                    $q2->where('payable_type', Bill::class)
                        ->where('payable_id', $bill->id);
                })
                    ->orWhereHas('allocations', function ($q2) use ($bill) {
                        $q2->where('allocatable_type', 'bill')
                            ->where('allocatable_id', $bill->id);
                    });
            })
            ->where('is_voided', false)
            ->with(['cashAccount', 'creator'])
            ->orderBy('payment_date', 'desc')
            ->get();
    }

    /**
     * Get the outstanding amount for a payable (invoice or bill).
     */
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

    /**
     * Check if a payable can receive a payment.
     */
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
}
