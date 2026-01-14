---
adr: "0032"
title: "Aging Report Buckets"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [reporting, receivables, payables]
related_adrs: [0011]
related_modules: [accounting]
impact: low
---

# ADR-0032: Aging Report Buckets

## AI Agent Quick Reference

**Use this ADR when:**
- Building aging reports
- Implementing AR/AP dashboards
- Working with overdue tracking
- Creating collection workflows

**Key takeaway:** Aging uses 30-day buckets (Current, 1-30, 31-60, 61-90, 90+) based on due date.

---

## Decision

Use standard 30-day aging buckets for receivables and payables aging reports.

---

## Context

Indonesian businesses typically track:
1. Current (not yet due)
2. Overdue in 30-day increments
3. Severely overdue (90+ days)
4. Write-off candidates

---

## Implementation

### Aging Buckets

| Bucket | Days | Indonesian | Description |
|--------|------|------------|-------------|
| current | 0 | Belum Jatuh Tempo | Not yet due |
| 1-30 | 1-30 | 1-30 Hari | Slightly overdue |
| 31-60 | 31-60 | 31-60 Hari | Moderately overdue |
| 61-90 | 61-90 | 61-90 Hari | Significantly overdue |
| 90+ | >90 | Lebih 90 Hari | Severely overdue |

### Configuration

```php
// config/accounting.php
'aging' => [
    'buckets' => [
        ['name' => 'current', 'min' => null, 'max' => 0, 'label' => 'Belum Jatuh Tempo'],
        ['name' => '1-30', 'min' => 1, 'max' => 30, 'label' => '1-30 Hari'],
        ['name' => '31-60', 'min' => 31, 'max' => 60, 'label' => '31-60 Hari'],
        ['name' => '61-90', 'min' => 61, 'max' => 90, 'label' => '61-90 Hari'],
        ['name' => '90+', 'min' => 91, 'max' => null, 'label' => 'Lebih 90 Hari'],
    ],
],
```

### Aging Calculation Service

```php
// app/Services/Accounting/AgingService.php
class AgingService
{
    public function getReceivablesAging(): Collection
    {
        $today = now()->startOfDay();
        $buckets = config('accounting.aging.buckets');

        return Invoice::unpaid()
            ->with('contact')
            ->get()
            ->groupBy(function ($invoice) use ($today, $buckets) {
                $daysOverdue = $today->diffInDays($invoice->due_date, false);
                // Negative means overdue
                $daysOverdue = abs(min(0, $daysOverdue));

                return $this->getBucketName($daysOverdue, $buckets);
            })
            ->map(function ($invoices, $bucket) {
                return [
                    'bucket' => $bucket,
                    'count' => $invoices->count(),
                    'total' => $invoices->sum('amount_due'),
                    'invoices' => $invoices,
                ];
            });
    }

    private function getBucketName(int $days, array $buckets): string
    {
        foreach ($buckets as $bucket) {
            $min = $bucket['min'] ?? PHP_INT_MIN;
            $max = $bucket['max'] ?? PHP_INT_MAX;

            if ($days >= $min && $days <= $max) {
                return $bucket['name'];
            }
        }

        return '90+';
    }
}
```

### Aging Report Query

```php
public function getAgingReport(string $type = 'receivable'): array
{
    $model = $type === 'receivable' ? Invoice::class : Bill::class;
    $today = now();

    return DB::table($model::getTableName())
        ->where('status', '!=', 'paid')
        ->selectRaw("
            CASE
                WHEN due_date >= ? THEN 'current'
                WHEN DATEDIFF(?, due_date) BETWEEN 1 AND 30 THEN '1-30'
                WHEN DATEDIFF(?, due_date) BETWEEN 31 AND 60 THEN '31-60'
                WHEN DATEDIFF(?, due_date) BETWEEN 61 AND 90 THEN '61-90'
                ELSE '90+'
            END as bucket,
            COUNT(*) as count,
            SUM(amount_due) as total
        ", [$today, $today, $today, $today])
        ->groupBy('bucket')
        ->get()
        ->keyBy('bucket')
        ->toArray();
}
```

### Contact Aging Summary

```php
public function getContactAging(Contact $contact): array
{
    $aging = $this->getReceivablesAging()
        ->map(fn ($bucket) => $bucket['invoices']
            ->where('contact_id', $contact->id));

    return [
        'contact' => $contact,
        'total_outstanding' => $aging->flatten()->sum('amount_due'),
        'buckets' => $aging->map(fn ($invoices) => [
            'count' => $invoices->count(),
            'total' => $invoices->sum('amount_due'),
        ]),
        'oldest_invoice' => $aging->flatten()->sortBy('due_date')->first(),
    ];
}
```

### Report Output Example

```
AGING RECEIVABLES REPORT
As of: 25 January 2024

Customer          | Current  | 1-30     | 31-60    | 61-90    | 90+      | Total
------------------+----------+----------+----------+----------+----------+-----------
PT Maju Jaya      | 5,000,000| 2,000,000|        - |        - |        - |  7,000,000
CV Sukses Abadi   |        - | 1,500,000| 3,000,000|        - |        - |  4,500,000
PT Gemilang       |        - |        - |        - | 2,500,000| 1,000,000|  3,500,000
------------------+----------+----------+----------+----------+----------+-----------
TOTAL             | 5,000,000| 3,500,000| 3,000,000| 2,500,000| 1,000,000| 15,000,000
```

---

## References

- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
- [Sales Cycle](../02-domain/sales-cycle.md)

