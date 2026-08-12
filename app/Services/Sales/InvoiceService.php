<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Contracts\Sales\InvoiceServiceInterface;
use App\Models\Sales\Invoice;
use App\Services\Sales\Invoice\InvoiceCrudService;
use App\Services\Sales\Invoice\InvoicePaymentStatusService;
use App\Services\Sales\Invoice\InvoicePostingService;
use App\Services\Sales\Invoice\InvoiceVoidService;
use App\Support\OperationContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Invoice service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - InvoiceCrudService: create, update, delete
 * - InvoicePostingService: post
 * - InvoiceVoidService: void (full cascade)
 * - InvoicePaymentStatusService: markAsPaid, markAsPartial, markAsOverdue, updatePaymentStatus
 *
 * @see \App\Services\Sales\Invoice\InvoiceCrudService
 * @see \App\Services\Sales\Invoice\InvoicePostingService
 * @see \App\Services\Sales\Invoice\InvoiceVoidService
 * @see \App\Services\Sales\Invoice\InvoicePaymentStatusService
 */
class InvoiceService implements InvoiceServiceInterface
{
    public function __construct(
        private InvoiceCrudService $crud,
        private InvoicePostingService $posting,
        private InvoiceVoidService $void,
        private InvoicePaymentStatusService $paymentStatus,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->crud = $this->crud->withContext($context);
        $clone->posting = $this->posting->withContext($context);
        $clone->void = $this->void->withContext($context);
        $clone->paymentStatus = $this->paymentStatus->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // CRUD Operations (delegated to InvoiceCrudService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function create(array $data): Invoice
    {
        return $this->crud->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(Model $document, array $data): Invoice
    {
        return $this->crud->update($document, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(Model $document): bool
    {
        return $this->crud->delete($document);
    }

    // ─────────────────────────────────────────────────────────────
    // Posting (delegated to InvoicePostingService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function post(Invoice $invoice): Invoice
    {
        return $this->posting->post($invoice);
    }

    // ─────────────────────────────────────────────────────────────
    // Void (delegated to InvoiceVoidService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function void(Invoice $invoice, string $reason): Invoice
    {
        return $this->void->void($invoice, $reason);
    }

    // ─────────────────────────────────────────────────────────────
    // Payment Status (delegated to InvoicePaymentStatusService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function markAsPaid(Invoice $invoice): Invoice
    {
        return $this->paymentStatus->markAsPaid($invoice);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsPartial(Invoice $invoice): Invoice
    {
        return $this->paymentStatus->markAsPartial($invoice);
    }

    /**
     * {@inheritdoc}
     */
    public function markAsOverdue(Invoice $invoice): Invoice
    {
        return $this->paymentStatus->markAsOverdue($invoice);
    }

    /**
     * {@inheritdoc}
     */
    public function updatePaymentStatus(Invoice $invoice): Invoice
    {
        return $this->paymentStatus->updatePaymentStatus($invoice);
    }
}
