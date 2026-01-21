<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\InMemory;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * In-memory Invoice repository for testing.
 *
 * @extends InMemoryRepository<Invoice>
 */
class InMemoryInvoiceRepository extends InMemoryRepository implements InvoiceRepositoryInterface
{
    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->items->filter(function (Invoice $invoice) use ($status) {
            return $invoice->status === $status;
        })->sortByDesc('invoice_date')->values();
    }

    public function findOverdue(): Collection
    {
        $now = now();

        return $this->items->filter(function (Invoice $invoice) use ($now) {
            return $invoice->due_date < $now
                && ! in_array($invoice->status, [
                    DocumentStatus::Paid,
                    DocumentStatus::Cancelled,
                    DocumentStatus::Draft,
                ], true);
        })->sortBy('due_date')->values();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->findBy(['contact_id' => $contactId], ['invoice_date' => 'desc']);
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->items->filter(function (Invoice $invoice) use ($range) {
            $invoiceDate = $invoice->invoice_date;

            return $invoiceDate >= $range->start && $invoiceDate <= $range->end;
        })->sortByDesc('invoice_date')->values();
    }

    public function getOutstandingForContact(int $contactId): int
    {
        return (int) $this->items
            ->filter(function (Invoice $invoice) use ($contactId) {
                return $invoice->contact_id === $contactId
                    && ! in_array($invoice->status, [
                        DocumentStatus::Paid,
                        DocumentStatus::Cancelled,
                        DocumentStatus::Draft,
                    ], true);
            })
            ->sum(fn (Invoice $invoice) => $invoice->total_amount - $invoice->paid_amount);
    }

    public function findWithRelations(int $id): ?Invoice
    {
        // In-memory implementation doesn't need eager loading
        return $this->find($id);
    }

    public function findByNumber(string $invoiceNumber): ?Invoice
    {
        return $this->findOneBy(['invoice_number' => $invoiceNumber]);
    }

    protected function createEntity(int $id, array $data): object
    {
        $invoice = new Invoice;
        $invoice->id = $id;
        $invoice->forceFill($data);

        return $invoice;
    }

    protected function updateEntity(object $entity, array $data): object
    {
        $entity->forceFill($data);

        return $entity;
    }
}
