<?php

declare(strict_types=1);

namespace App\Contracts\Services\Domains;

use App\Models\Manufacturing\SubcontractorWorkOrder;
use App\Models\Shared\SubcontractorInvoice;
use App\Models\Purchasing\Bill;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Subcontractor service operations.
 */
interface SubcontractorServiceInterface
{
    /**
     * Create a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SubcontractorWorkOrder;

    /**
     * Update a subcontractor work order.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SubcontractorWorkOrder $scWo, array $data): SubcontractorWorkOrder;

    /**
     * Delete a subcontractor work order.
     */
    public function delete(SubcontractorWorkOrder $scWo): bool;

    /**
     * Assign work order.
     */
    public function assign(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder;

    /**
     * Start work.
     */
    public function start(SubcontractorWorkOrder $scWo, ?int $userId = null): SubcontractorWorkOrder;

    /**
     * Update progress percentage.
     */
    public function updateProgress(SubcontractorWorkOrder $scWo, int $percentage): SubcontractorWorkOrder;

    /**
     * Complete the subcontractor work.
     */
    public function complete(SubcontractorWorkOrder $scWo, ?int $actualAmount = null, ?int $userId = null): SubcontractorWorkOrder;

    /**
     * Cancel a subcontractor work order.
     */
    public function cancel(SubcontractorWorkOrder $scWo, ?string $reason = null, ?int $userId = null): SubcontractorWorkOrder;

    /**
     * Create an invoice for subcontractor work.
     *
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(SubcontractorWorkOrder $scWo, array $data): SubcontractorInvoice;

    /**
     * Update a subcontractor invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateInvoice(SubcontractorInvoice $invoice, array $data): SubcontractorInvoice;

    /**
     * Approve a subcontractor invoice.
     */
    public function approveInvoice(SubcontractorInvoice $invoice, ?int $userId = null): SubcontractorInvoice;

    /**
     * Reject a subcontractor invoice.
     */
    public function rejectInvoice(SubcontractorInvoice $invoice, string $reason, ?int $userId = null): SubcontractorInvoice;

    /**
     * Convert invoice to bill.
     */
    public function convertToBill(SubcontractorInvoice $invoice): Bill;

    /**
     * Get statistics for subcontractor work.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?int $subcontractorId = null): array;

    /**
     * Get all subcontractors (contacts with subcontractor type).
     *
     * @return Collection<int, \App\Models\Contacts\Contact>
     */
    public function getSubcontractors(): Collection;
}
