<?php

declare(strict_types=1);

namespace App\Contracts\Sales;

use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use Illuminate\Database\Eloquent\Collection;

/**
 * Interface for Sales Return service operations.
 */
interface SalesReturnServiceInterface
{
    /**
     * Create a new sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): SalesReturn;

    /**
     * Update an existing sales return.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SalesReturn $salesReturn, array $data): SalesReturn;

    /**
     * Delete a sales return.
     */
    public function delete(SalesReturn $salesReturn): bool;

    /**
     * Create sales return from invoice.
     *
     * @param  array<string, mixed>  $data
     */
    public function createFromInvoice(Invoice $invoice, array $data = []): SalesReturn;

    /**
     * Submit a sales return for approval.
     */
    public function submit(SalesReturn $salesReturn, ?int $userId = null): SalesReturn;

    /**
     * Approve a sales return.
     */
    public function approve(SalesReturn $salesReturn, ?int $userId = null): SalesReturn;

    /**
     * Reject a sales return.
     */
    public function reject(SalesReturn $salesReturn, ?string $reason = null, ?int $userId = null): SalesReturn;

    /**
     * Complete a sales return.
     */
    public function complete(SalesReturn $salesReturn, ?int $userId = null): SalesReturn;

    /**
     * Cancel a sales return.
     */
    public function cancel(SalesReturn $salesReturn, ?string $reason = null): SalesReturn;

    /**
     * Get sales returns for an invoice.
     *
     * @return Collection<int, SalesReturn>
     */
    public function getForInvoice(Invoice $invoice): Collection;

    /**
     * Get statistics for sales returns.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(?string $startDate = null, ?string $endDate = null): array;
}
