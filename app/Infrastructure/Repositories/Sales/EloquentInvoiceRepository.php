<?php

declare(strict_types=1);

namespace App\Infrastructure\Repositories\Sales;

use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Infrastructure\Repositories\EloquentRepository;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

/**
 * Eloquent implementation of Invoice repository.
 *
 * @extends EloquentRepository<Invoice>
 */
class EloquentInvoiceRepository extends EloquentRepository implements InvoiceRepositoryInterface
{
    protected string $modelClass = Invoice::class;

    protected array $with = ['contact', 'items'];

    public function findByStatus(DocumentStatus $status): Collection
    {
        return $this->newQuery()
            ->where('status', $status)
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function findOverdue(): Collection
    {
        return $this->newQuery()
            ->where('due_date', '<', now())
            ->whereNotIn('status', [
                DocumentStatus::Paid,
                DocumentStatus::Cancelled,
                DocumentStatus::Draft,
            ])
            ->orderBy('due_date', 'asc')
            ->get();
    }

    public function findByContact(int $contactId): Collection
    {
        return $this->newQuery()
            ->where('contact_id', $contactId)
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function findByDateRange(DateRange $range): Collection
    {
        return $this->newQuery()
            ->whereBetween('invoice_date', [
                $range->start->toDateString(),
                $range->end->toDateString(),
            ])
            ->orderBy('invoice_date', 'desc')
            ->get();
    }

    public function getOutstandingForContact(int $contactId): int
    {
        return (int) $this->newQuery()
            ->where('contact_id', $contactId)
            ->whereNotIn('status', [DocumentStatus::Paid, DocumentStatus::Cancelled, DocumentStatus::Draft])
            ->selectRaw('COALESCE(SUM(total_amount - paid_amount), 0) as outstanding')
            ->value('outstanding');
    }

    public function findWithRelations(int $id): ?Invoice
    {
        return $this->newQuery()
            ->with([
                'contact',
                'items.product',
                'journalEntry.lines.account',
                'payments',
            ])
            ->find($id);
    }

    public function findByNumber(string $invoiceNumber): ?Invoice
    {
        return $this->findOneBy(['invoice_number' => $invoiceNumber]);
    }
}
