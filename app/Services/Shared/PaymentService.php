<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\PaymentServiceInterface;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Shared\Payment\PaymentCreationService;
use App\Services\Shared\Payment\PaymentQueryService;
use App\Services\Shared\Payment\PaymentVoidService;
use App\Support\OperationContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Payment service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - PaymentCreationService: create, createForInvoice, createForBill
 * - PaymentVoidService: void
 * - PaymentQueryService: getForInvoice, getForBill, getOutstandingAmount, canReceivePayment
 *
 * @see \App\Services\Shared\Payment\PaymentCreationService
 * @see \App\Services\Shared\Payment\PaymentVoidService
 * @see \App\Services\Shared\Payment\PaymentQueryService
 */
class PaymentService implements PaymentServiceInterface
{
    public function __construct(
        private PaymentCreationService $creation,
        private PaymentVoidService $void,
        private PaymentQueryService $query,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->creation = $this->creation->withContext($context);
        $clone->void = $this->void->withContext($context);
        $clone->query = $this->query->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // Creation (delegated to PaymentCreationService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Payment
    {
        return $this->creation->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function createForInvoice(Invoice $invoice, array $data): Payment
    {
        return $this->creation->createForInvoice($invoice, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function createForBill(Bill $bill, array $data): Payment
    {
        return $this->creation->createForBill($bill, $data);
    }

    // ─────────────────────────────────────────────────────────────
    // Void (delegated to PaymentVoidService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function void(Payment $payment, ?string $reason = null): Payment
    {
        return $this->void->void($payment, $reason);
    }

    // ─────────────────────────────────────────────────────────────
    // Queries (delegated to PaymentQueryService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function getForInvoice(Invoice $invoice): Collection
    {
        return $this->query->getForInvoice($invoice);
    }

    /**
     * {@inheritdoc}
     */
    public function getForBill(Bill $bill): Collection
    {
        return $this->query->getForBill($bill);
    }

    /**
     * {@inheritdoc}
     */
    public function getOutstandingAmount(Model $payable): int
    {
        return $this->query->getOutstandingAmount($payable);
    }

    /**
     * {@inheritdoc}
     */
    public function canReceivePayment(Model $payable): bool
    {
        return $this->query->canReceivePayment($payable);
    }
}
