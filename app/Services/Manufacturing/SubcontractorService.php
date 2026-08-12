<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Contracts\Manufacturing\SubcontractorServiceInterface;
use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Purchasing\Bill;
use App\Models\Shared\SubcontractorInvoice;
use App\Services\Manufacturing\Subcontractor\SubcontractorInvoiceService;
use App\Services\Manufacturing\Subcontractor\SubcontractorQueryService;
use App\Services\Manufacturing\Subcontractor\SubcontractorWorkOrderService;
use App\Support\OperationContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Subcontractor service coordinator.
 *
 * Thin coordinator that delegates to focused services:
 * - SubcontractorWorkOrderService: create, update, delete, assign, start, updateProgress, complete, cancel
 * - SubcontractorInvoiceService: createInvoice, updateInvoice, approveInvoice, rejectInvoice, convertToBill
 * - SubcontractorQueryService: getStatistics, getSubcontractors
 *
 * @see \App\Services\Manufacturing\Subcontractor\SubcontractorWorkOrderService
 * @see \App\Services\Manufacturing\Subcontractor\SubcontractorInvoiceService
 * @see \App\Services\Manufacturing\Subcontractor\SubcontractorQueryService
 */
class SubcontractorService implements SubcontractorServiceInterface
{
    public function __construct(
        private SubcontractorWorkOrderService $workOrders,
        private SubcontractorInvoiceService $invoices,
        private SubcontractorQueryService $queries,
    ) {}

    /**
     * Set operation context for all underlying services.
     *
     * Returns a clone with context-aware services for fluent chaining.
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->workOrders = $this->workOrders->withContext($context);
        $clone->invoices = $this->invoices->withContext($context);
        $clone->queries = $this->queries->withContext($context);

        return $clone;
    }

    // ─────────────────────────────────────────────────────────────
    // Work Order Operations (delegated to SubcontractorWorkOrderService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function create(array $data): SubcontractorWorkOrder
    {
        return $this->workOrders->create($data);
    }

    /**
     * {@inheritdoc}
     */
    public function update(SubcontractorWorkOrder $scWo, array $data): SubcontractorWorkOrder
    {
        return $this->workOrders->update($scWo, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SubcontractorWorkOrder $scWo): bool
    {
        return $this->workOrders->delete($scWo);
    }

    /**
     * {@inheritdoc}
     */
    public function assign(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        return $this->workOrders->assign($scWo, $userId);
    }

    /**
     * {@inheritdoc}
     */
    public function start(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder
    {
        return $this->workOrders->start($scWo, $userId);
    }

    /**
     * {@inheritdoc}
     */
    public function updateProgress(SubcontractorWorkOrder $scWo, int $percentage): SubcontractorWorkOrder
    {
        return $this->workOrders->updateProgress($scWo, $percentage);
    }

    /**
     * {@inheritdoc}
     */
    public function complete(SubcontractorWorkOrder $scWo, ?int $actualAmount = null, ?int $userId = null): SubcontractorWorkOrder
    {
        return $this->workOrders->complete($scWo, $actualAmount, $userId);
    }

    /**
     * {@inheritdoc}
     */
    public function cancel(SubcontractorWorkOrder $scWo, ?string $reason = null, ?int $userId = null): SubcontractorWorkOrder
    {
        return $this->workOrders->cancel($scWo, $reason, $userId);
    }

    // ─────────────────────────────────────────────────────────────
    // Invoice Operations (delegated to SubcontractorInvoiceService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function createInvoice(SubcontractorWorkOrder $scWo, array $data): SubcontractorInvoice
    {
        return $this->invoices->createInvoice($scWo, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function updateInvoice(SubcontractorInvoice $invoice, array $data): SubcontractorInvoice
    {
        return $this->invoices->updateInvoice($invoice, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function approveInvoice(SubcontractorInvoice $invoice, ?int $userId = null): SubcontractorInvoice
    {
        return $this->invoices->approveInvoice($invoice, $userId);
    }

    /**
     * {@inheritdoc}
     */
    public function rejectInvoice(SubcontractorInvoice $invoice, string $reason, ?int $userId = null): SubcontractorInvoice
    {
        return $this->invoices->rejectInvoice($invoice, $reason, $userId);
    }

    /**
     * {@inheritdoc}
     */
    public function convertToBill(SubcontractorInvoice $invoice): Bill
    {
        return $this->invoices->convertToBill($invoice);
    }

    // ─────────────────────────────────────────────────────────────
    // Query Operations (delegated to SubcontractorQueryService)
    // ─────────────────────────────────────────────────────────────

    /**
     * {@inheritdoc}
     */
    public function getStatistics(?int $subcontractorId = null): array
    {
        return $this->queries->getStatistics($subcontractorId);
    }

    /**
     * {@inheritdoc}
     */
    public function getSubcontractors(): Collection
    {
        return $this->queries->getSubcontractors();
    }
}
