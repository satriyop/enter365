<?php

declare(strict_types=1);

namespace App\QueryServices\Sales;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\Sales\InvoiceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\QueryServices\Base\AbstractQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Query service for invoice reads and reporting.
 *
 * Provides optimized read operations for invoices:
 * - List and pagination with filters
 * - Statistics and reports
 * - Aging analysis
 *
 * @extends AbstractQueryService<Invoice>
 */
class InvoiceQueryService extends AbstractQueryService
{
    public function __construct(
        InvoiceRepositoryInterface $repository,
        ContextualLoggerInterface $logger
    ) {
        parent::__construct($repository, $logger);
    }

    /**
     * Get invoice with all relations.
     */
    public function findWithDetails(int $id): ?Invoice
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->findWithRelations($id);
    }

    /**
     * Get overdue invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function getOverdue(): Collection
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->findOverdue();
    }

    /**
     * Get invoices by status.
     *
     * @return Collection<int, Invoice>
     */
    public function getByStatus(DocumentStatus $status): Collection
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->findByStatus($status);
    }

    /**
     * Get invoices for contact.
     *
     * @return Collection<int, Invoice>
     */
    public function getByContact(int $contactId): Collection
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->findByContact($contactId);
    }

    /**
     * Get outstanding amount for contact.
     */
    public function getOutstandingForContact(int $contactId): int
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->getOutstandingForContact($contactId);
    }

    /**
     * Get invoices by date range.
     *
     * @return Collection<int, Invoice>
     */
    public function getByDateRange(DateRange $range): Collection
    {
        /** @var InvoiceRepositoryInterface $repository */
        $repository = $this->repository;

        return $repository->findByDateRange($range);
    }

    /**
     * Get statistics for date range.
     *
     * Uses raw queries for performance on large datasets.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(DateRange $range): array
    {
        $stats = DB::table('invoices')
            ->whereBetween('invoice_date', [
                $range->start->toDateString(),
                $range->end->toDateString(),
            ])
            ->whereNull('deleted_at')
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as draft_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sent_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as paid_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as cancelled_count,
                COALESCE(SUM(total_amount), 0) as total_amount,
                COALESCE(SUM(paid_amount), 0) as paid_amount,
                COALESCE(SUM(total_amount - paid_amount), 0) as outstanding_amount
            ', [
                DocumentStatus::Draft->value,
                DocumentStatus::Sent->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Overdue->value,
                DocumentStatus::Cancelled->value,
            ])
            ->first();

        return [
            'period' => $range->jsonSerialize(),
            'counts' => [
                'total' => (int) $stats->total_count,
                'draft' => (int) $stats->draft_count,
                'sent' => (int) $stats->sent_count,
                'paid' => (int) $stats->paid_count,
                'overdue' => (int) $stats->overdue_count,
                'cancelled' => (int) $stats->cancelled_count,
            ],
            'amounts' => [
                'total' => (int) $stats->total_amount,
                'paid' => (int) $stats->paid_amount,
                'outstanding' => (int) $stats->outstanding_amount,
            ],
        ];
    }

    /**
     * Get aging report for receivables.
     *
     * Categorizes outstanding invoices by days past due.
     *
     * @return array<string, array{count: int, amount: int}>
     */
    public function getAgingReport(): array
    {
        $now = now();

        $aging = DB::table('invoices')
            ->whereNotIn('status', [
                DocumentStatus::Paid->value,
                DocumentStatus::Cancelled->value,
                DocumentStatus::Draft->value,
            ])
            ->whereNull('deleted_at')
            ->selectRaw('
                SUM(CASE WHEN due_date >= ? THEN 1 ELSE 0 END) as current_count,
                COALESCE(SUM(CASE WHEN due_date >= ? THEN total_amount - paid_amount ELSE 0 END), 0) as current_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_1_30_count,
                COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END), 0) as days_1_30_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_31_60_count,
                COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END), 0) as days_31_60_amount,
                SUM(CASE WHEN due_date < ? AND due_date >= ? THEN 1 ELSE 0 END) as days_61_90_count,
                COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total_amount - paid_amount ELSE 0 END), 0) as days_61_90_amount,
                SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END) as over_90_count,
                COALESCE(SUM(CASE WHEN due_date < ? THEN total_amount - paid_amount ELSE 0 END), 0) as over_90_amount
            ', [
                $now, // current
                $now,
                $now, $now->copy()->subDays(30), // 1-30
                $now, $now->copy()->subDays(30),
                $now->copy()->subDays(30), $now->copy()->subDays(60), // 31-60
                $now->copy()->subDays(30), $now->copy()->subDays(60),
                $now->copy()->subDays(60), $now->copy()->subDays(90), // 61-90
                $now->copy()->subDays(60), $now->copy()->subDays(90),
                $now->copy()->subDays(90), // over 90
                $now->copy()->subDays(90),
            ])
            ->first();

        return [
            'current' => [
                'count' => (int) $aging->current_count,
                'amount' => (int) $aging->current_amount,
            ],
            '1-30_days' => [
                'count' => (int) $aging->days_1_30_count,
                'amount' => (int) $aging->days_1_30_amount,
            ],
            '31-60_days' => [
                'count' => (int) $aging->days_31_60_count,
                'amount' => (int) $aging->days_31_60_amount,
            ],
            '61-90_days' => [
                'count' => (int) $aging->days_61_90_count,
                'amount' => (int) $aging->days_61_90_amount,
            ],
            'over_90_days' => [
                'count' => (int) $aging->over_90_count,
                'amount' => (int) $aging->over_90_amount,
            ],
        ];
    }

    /**
     * Get monthly revenue summary.
     *
     * @return Collection<int, array{month: string, total: int, count: int}>
     */
    public function getMonthlySummary(int $year): Collection
    {
        $driver = DB::connection()->getDriverName();

        // Use database-specific month extraction
        $monthExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%m', invoice_date) AS INTEGER)",
            'pgsql' => 'EXTRACT(MONTH FROM invoice_date)::INTEGER',
            default => 'MONTH(invoice_date)',
        };

        return DB::table('invoices')
            ->whereYear('invoice_date', $year)
            ->whereIn('status', [
                DocumentStatus::Sent->value,
                DocumentStatus::Paid->value,
                DocumentStatus::Partial->value,
            ])
            ->whereNull('deleted_at')
            ->selectRaw("{$monthExpression} as month, COALESCE(SUM(total_amount), 0) as total, COUNT(*) as count")
            ->groupByRaw($monthExpression)
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'month' => (int) $row->month,
                'total' => (int) $row->total,
                'count' => (int) $row->count,
            ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    protected function buildFilteredQuery(array $filters): Builder
    {
        $query = Invoice::query()->with(['contact', 'items']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('invoice_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('invoice_date', '<=', $filters['date_to']);
        }

        if (! empty($filters['min_amount'])) {
            $query->where('total_amount', '>=', $filters['min_amount']);
        }

        if (! empty($filters['max_amount'])) {
            $query->where('total_amount', '<=', $filters['max_amount']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhereHas('contact', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
            });
        }

        if (! empty($filters['overdue_only'])) {
            $query->where('due_date', '<', now())
                ->whereNotIn('status', [
                    DocumentStatus::Paid,
                    DocumentStatus::Cancelled,
                    DocumentStatus::Draft,
                ]);
        }

        // Default ordering
        $orderBy = $filters['order_by'] ?? 'invoice_date';
        $orderDir = $filters['order_dir'] ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        return $query;
    }
}
