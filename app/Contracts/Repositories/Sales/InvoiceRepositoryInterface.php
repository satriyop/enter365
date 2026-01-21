<?php

declare(strict_types=1);

namespace App\Contracts\Repositories\Sales;

use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * Repository interface for Invoice entities.
 *
 * @extends RepositoryInterface<Invoice>
 */
interface InvoiceRepositoryInterface extends RepositoryInterface
{
    /**
     * Find invoices by status.
     *
     * @return Collection<int, Invoice>
     */
    public function findByStatus(DocumentStatus $status): Collection;

    /**
     * Find overdue invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function findOverdue(): Collection;

    /**
     * Find invoices for contact.
     *
     * @return Collection<int, Invoice>
     */
    public function findByContact(int $contactId): Collection;

    /**
     * Find invoices in date range.
     *
     * @return Collection<int, Invoice>
     */
    public function findByDateRange(DateRange $range): Collection;

    /**
     * Get total outstanding amount for contact.
     */
    public function getOutstandingForContact(int $contactId): int;

    /**
     * Get invoice with all relations loaded.
     */
    public function findWithRelations(int $id): ?Invoice;

    /**
     * Find by invoice number.
     */
    public function findByNumber(string $invoiceNumber): ?Invoice;
}
