<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Collection;

class OverdueService
{
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
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('due_date', '<', $cutoffDate)
            ->get();

        $marked = collect();

        foreach ($invoices as $invoice) {
            if ($invoice->markAsOverdue()) {
                $marked->push($invoice);
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
            ->whereIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL])
            ->where('due_date', '<', $cutoffDate)
            ->get();

        $marked = collect();

        foreach ($bills as $bill) {
            if ($bill->markAsOverdue()) {
                $marked->push($bill);
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
        $overdueInvoices = Invoice::query()
            ->where('status', Invoice::STATUS_OVERDUE)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount - paid_amount), 0) as total')
            ->first();

        $overdueBills = Bill::query()
            ->where('status', Bill::STATUS_OVERDUE)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount - paid_amount), 0) as total')
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
            ->whereIn('status', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->whereBetween('due_date', [now(), now()->addDays($daysAhead)])
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
            ->whereIn('status', [Bill::STATUS_RECEIVED, Bill::STATUS_PARTIAL])
            ->whereBetween('due_date', [now(), now()->addDays($daysAhead)])
            ->orderBy('due_date')
            ->get();
    }
}
