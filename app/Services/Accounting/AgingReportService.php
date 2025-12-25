<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use Illuminate\Support\Collection;

class AgingReportService
{
    /**
     * Get accounts receivable aging report.
     *
     * @return array{
     *     as_of_date: string,
     *     buckets: array<int, array{label: string, min: int, max: ?int}>,
     *     contacts: Collection<int, array>,
     *     totals: array<string, int>
     * }
     */
    public function getReceivableAging(?\DateTimeInterface $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        $buckets = config('accounting.aging_buckets');

        $invoices = Invoice::query()
            ->with('contact')
            ->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->get();

        $byContact = $invoices->groupBy('contact_id');
        $contacts = collect();
        $totals = $this->initializeBucketTotals($buckets);

        foreach ($byContact as $contactId => $contactInvoices) {
            $contact = $contactInvoices->first()->contact;
            $contactBuckets = $this->initializeBucketTotals($buckets);

            foreach ($contactInvoices as $invoice) {
                $outstanding = $invoice->total_amount - $invoice->paid_amount;
                $daysOverdue = $this->calculateDaysOverdue($invoice->due_date, $asOfDate);
                $bucketKey = $this->getBucketKey($daysOverdue, $buckets);

                $contactBuckets[$bucketKey] += $outstanding;
                $totals[$bucketKey] += $outstanding;
            }

            $contactBuckets['total'] = array_sum($contactBuckets);

            $contacts->push([
                'contact_id' => $contactId,
                'contact_code' => $contact->code,
                'contact_name' => $contact->name,
                'buckets' => $contactBuckets,
                'invoice_count' => $contactInvoices->count(),
            ]);
        }

        $totals['total'] = array_sum($totals);

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'buckets' => $buckets,
            'contacts' => $contacts->sortByDesc(fn ($c) => $c['buckets']['total'])->values(),
            'totals' => $totals,
        ];
    }

    /**
     * Get accounts payable aging report.
     *
     * @return array{
     *     as_of_date: string,
     *     buckets: array<int, array{label: string, min: int, max: ?int}>,
     *     contacts: Collection<int, array>,
     *     totals: array<string, int>
     * }
     */
    public function getPayableAging(?\DateTimeInterface $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        $buckets = config('accounting.aging_buckets');

        $bills = Bill::query()
            ->with('contact')
            ->whereIn('status', [
                Bill::STATUS_RECEIVED,
                Bill::STATUS_PARTIAL,
                Bill::STATUS_OVERDUE,
            ])
            ->whereDate('bill_date', '<=', $asOfDate)
            ->get();

        $byContact = $bills->groupBy('contact_id');
        $contacts = collect();
        $totals = $this->initializeBucketTotals($buckets);

        foreach ($byContact as $contactId => $contactBills) {
            $contact = $contactBills->first()->contact;
            $contactBuckets = $this->initializeBucketTotals($buckets);

            foreach ($contactBills as $bill) {
                $outstanding = $bill->total_amount - $bill->paid_amount;
                $daysOverdue = $this->calculateDaysOverdue($bill->due_date, $asOfDate);
                $bucketKey = $this->getBucketKey($daysOverdue, $buckets);

                $contactBuckets[$bucketKey] += $outstanding;
                $totals[$bucketKey] += $outstanding;
            }

            $contactBuckets['total'] = array_sum($contactBuckets);

            $contacts->push([
                'contact_id' => $contactId,
                'contact_code' => $contact->code,
                'contact_name' => $contact->name,
                'buckets' => $contactBuckets,
                'bill_count' => $contactBills->count(),
            ]);
        }

        $totals['total'] = array_sum($totals);

        return [
            'as_of_date' => $asOfDate->format('Y-m-d'),
            'buckets' => $buckets,
            'contacts' => $contacts->sortByDesc(fn ($c) => $c['buckets']['total'])->values(),
            'totals' => $totals,
        ];
    }

    /**
     * Get aging summary for a specific contact.
     *
     * @return array{receivable: array, payable: array}
     */
    public function getContactAging(Contact $contact, ?\DateTimeInterface $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();
        $buckets = config('accounting.aging_buckets');

        $receivableBuckets = $this->initializeBucketTotals($buckets);
        $payableBuckets = $this->initializeBucketTotals($buckets);

        // Receivables
        $invoices = $contact->invoices()
            ->whereIn('status', [
                Invoice::STATUS_SENT,
                Invoice::STATUS_PARTIAL,
                Invoice::STATUS_OVERDUE,
            ])
            ->whereDate('invoice_date', '<=', $asOfDate)
            ->get();

        foreach ($invoices as $invoice) {
            $outstanding = $invoice->total_amount - $invoice->paid_amount;
            $daysOverdue = $this->calculateDaysOverdue($invoice->due_date, $asOfDate);
            $bucketKey = $this->getBucketKey($daysOverdue, $buckets);
            $receivableBuckets[$bucketKey] += $outstanding;
        }
        $receivableBuckets['total'] = array_sum($receivableBuckets);

        // Payables
        $bills = $contact->bills()
            ->whereIn('status', [
                Bill::STATUS_RECEIVED,
                Bill::STATUS_PARTIAL,
                Bill::STATUS_OVERDUE,
            ])
            ->whereDate('bill_date', '<=', $asOfDate)
            ->get();

        foreach ($bills as $bill) {
            $outstanding = $bill->total_amount - $bill->paid_amount;
            $daysOverdue = $this->calculateDaysOverdue($bill->due_date, $asOfDate);
            $bucketKey = $this->getBucketKey($daysOverdue, $buckets);
            $payableBuckets[$bucketKey] += $outstanding;
        }
        $payableBuckets['total'] = array_sum($payableBuckets);

        return [
            'receivable' => [
                'buckets' => $receivableBuckets,
                'invoice_count' => $invoices->count(),
            ],
            'payable' => [
                'buckets' => $payableBuckets,
                'bill_count' => $bills->count(),
            ],
        ];
    }

    /**
     * Initialize bucket totals array.
     *
     * @return array<string, int>
     */
    protected function initializeBucketTotals(array $buckets): array
    {
        $totals = [];
        foreach ($buckets as $index => $bucket) {
            $totals["bucket_{$index}"] = 0;
        }

        return $totals;
    }

    /**
     * Calculate days overdue.
     */
    protected function calculateDaysOverdue(\DateTimeInterface $dueDate, \DateTimeInterface $asOfDate): int
    {
        $dueCarbon = \Carbon\Carbon::parse($dueDate);
        $asOfCarbon = \Carbon\Carbon::parse($asOfDate);

        if ($asOfCarbon->lte($dueCarbon)) {
            return 0; // Not yet due
        }

        return (int) $dueCarbon->diffInDays($asOfCarbon);
    }

    /**
     * Get the bucket key for a number of days overdue.
     */
    protected function getBucketKey(int $daysOverdue, array $buckets): string
    {
        foreach ($buckets as $index => $bucket) {
            $min = $bucket['min'];
            $max = $bucket['max'];

            if ($daysOverdue >= $min && ($max === null || $daysOverdue <= $max)) {
                return "bucket_{$index}";
            }
        }

        // Default to last bucket
        return 'bucket_' . (count($buckets) - 1);
    }
}
