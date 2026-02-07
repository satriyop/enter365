<?php

namespace App\Services\Sales;

use App\Contracts\Purchasing\BillServiceInterface;
use App\Contracts\Sales\InvoiceServiceInterface;
use App\Contracts\Shared\OverdueServiceInterface;
use App\Enums\DocumentStatus;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use Illuminate\Support\Collection;

class OverdueService implements OverdueServiceInterface
{
    public function __construct(
        private InvoiceServiceInterface $invoiceService,
        private BillServiceInterface $billService
    ) {}

    /**
     * Mark all overdue invoices.
     *
     * @return Collection<int, Invoice>
     */
    public function markOverdueInvoices(): Collection
    {
        $gracePeriod = config('accounting.overdue.grace_period_days', 0);
        $cutoffDate = now()->subDays($gracePeriod);

        $invoices = Invoice::query()
            ->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Partial])
            ->where('due_date', '<', $cutoffDate)
            ->get();

        $marked = collect();

        foreach ($invoices as $invoice) {
            try {
                $this->invoiceService->markAsOverdue($invoice);
                $marked->push($invoice);
            } catch (\App\Exceptions\Domain\StateTransitionException) {
                // Skip if state machine doesn't allow transition
            }
        }

        return $marked;
    }

    /**
     * Mark all overdue bills.
     *
     * @return Collection<int, Bill>
     */
    public function markOverdueBills(): Collection
    {
        $gracePeriod = config('accounting.overdue.grace_period_days', 0);
        $cutoffDate = now()->subDays($gracePeriod);

        $bills = Bill::query()
            ->whereIn('status', [DocumentStatus::Received, DocumentStatus::Partial])
            ->where('due_date', '<', $cutoffDate)
            ->get();

        $marked = collect();

        foreach ($bills as $bill) {
            try {
                $this->billService->markAsOverdue($bill);
                $marked->push($bill);
            } catch (\App\Exceptions\Domain\StateTransitionException) {
                // Skip if state machine doesn't allow transition
            }
        }

        return $marked;
    }

    /**
     * Mark all overdue documents (invoices and bills).
     *
     * @return array{invoices: Collection<int, Invoice>, bills: Collection<int, Bill>}
     */
    public function markAllOverdue(): array
    {
        return [
            'invoices' => $this->markOverdueInvoices(),
            'bills' => $this->markOverdueBills(),
        ];
    }

    /**
     * Get summary of overdue documents.
     *
     * @return array{
     *     invoices: array{count: int, total: int},
     *     bills: array{count: int, total: int}
     * }
     */
    public function getOverdueSummary(): array
    {
        /** @var object{count: int, total: int} $overdueInvoices */
        $overdueInvoices = Invoice::query()
            ->where('status', DocumentStatus::Overdue)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount - paid_amount), 0) as total')
            ->toBase()
            ->first();

        /** @var object{count: int, total: int} $overdueBills */
        $overdueBills = Bill::query()
            ->where('status', DocumentStatus::Overdue)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount - paid_amount), 0) as total')
            ->toBase()
            ->first();

        return [
            'invoices' => [
                'count' => (int) $overdueInvoices->count,
                'total' => (int) $overdueInvoices->total,
            ],
            'bills' => [
                'count' => (int) $overdueBills->count,
                'total' => (int) $overdueBills->total,
            ],
        ];
    }

    /**
     * Get invoices approaching due date.
     *
     * @return Collection<int, Invoice>
     */
    public function getUpcomingDueInvoices(int $daysAhead = 7): Collection
    {
        return Invoice::query()
            ->whereIn('status', [DocumentStatus::Sent, DocumentStatus::Partial])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString().' 23:59:59'])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get bills approaching due date.
     *
     * @return Collection<int, Bill>
     */
    public function getUpcomingDueBills(int $daysAhead = 7): Collection
    {
        return Bill::query()
            ->whereIn('status', [DocumentStatus::Received, DocumentStatus::Partial])
            ->whereBetween('due_date', [now()->toDateString(), now()->addDays($daysAhead)->toDateString().' 23:59:59'])
            ->orderBy('due_date')
            ->get();
    }
}
