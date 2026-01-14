---
adr: "0018"
title: "Subcontractor 5% Retention"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [manufacturing, payables]
related_adrs: [0019]
related_modules: [manufacturing, projects]
impact: medium
---

# ADR-0018: Subcontractor 5% Retention

## AI Agent Quick Reference

**Use this ADR when:**
- Working with subcontractor invoices
- Understanding retention tracking
- Implementing retention release
- Building subcontractor reports

**Key takeaway:** 5% of subcontractor payments are held as retention until warranty period ends.

---

## Decision

Implement 5% retention on subcontractor invoices, released after warranty period (typically 3-12 months).

---

## Context

Indonesian construction/manufacturing contracts commonly include:
- **Retention**: 5% withheld from each payment
- **Purpose**: Guarantee quality during warranty
- **Release**: After defect liability period (3-12 months)

---

## Implementation

### Subcontractor Invoice Fields

```php
$table->foreignId('subcontractor_id');        // Contact (vendor)
$table->foreignId('work_order_id');
$table->bigInteger('gross_amount');
$table->decimal('retention_percent', 5, 2);   // 5.00
$table->bigInteger('retention_amount');        // gross × 5%
$table->bigInteger('net_payable');            // gross - retention
$table->date('retention_due_date');           // When retention releases
$table->boolean('retention_released');
$table->timestamp('retention_released_at');
```

### Calculation

```php
$grossAmount = 100_000_000_00;  // Rp 100,000,000
$retentionPercent = 5.00;
$retentionAmount = (int) round($grossAmount * $retentionPercent / 100);
$netPayable = $grossAmount - $retentionAmount;

// Results:
// Retention: Rp 5,000,000
// Net Payable: Rp 95,000,000
```

### Retention Release

```php
public function releaseRetention(SubcontractorInvoice $invoice): void
{
    if ($invoice->retention_released) {
        throw new Exception('Retensi sudah dirilis.');
    }

    // Create additional payment for retention
    $this->paymentService->create([
        'contact_id' => $invoice->subcontractor_id,
        'amount' => $invoice->retention_amount,
        'reference' => "Retention release: {$invoice->invoice_number}",
    ]);

    $invoice->update([
        'retention_released' => true,
        'retention_released_at' => now(),
    ]);
}
```

### Reporting

```php
// Outstanding retention
SubcontractorInvoice::where('retention_released', false)
    ->sum('retention_amount');

// Retention due for release
SubcontractorInvoice::where('retention_released', false)
    ->where('retention_due_date', '<=', now())
    ->get();
```

---

## References

- [ADR-0019: Down Payment Application](./0019-down-payment-application.md)
- [Purchasing Cycle](../02-domain/purchasing-cycle.md)
