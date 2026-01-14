---
adr: "0026"
title: "PPN VAT Calculation"
status: accepted
date: 2024-11-15
deciders: [Product Team, Tax Advisor]
tags: [tax, indonesian-context, domain]
related_adrs: [0006, 0011]
related_modules: [accounting]
impact: high
---

# ADR-0026: PPN VAT Calculation

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing tax calculations
- Working with invoices and bills
- Understanding PPN (VAT) rules
- Building tax reports

**Key takeaway:** PPN is 11% VAT applied to taxable goods/services, stored separately for reporting.

---

## Decision

Implement Indonesian PPN (Pajak Pertambahan Nilai / VAT) at 11% rate with proper tracking for tax reporting.

---

## Context

Indonesian businesses must:
1. Charge PPN on taxable sales
2. Claim PPN credit on purchases
3. Report net PPN to tax authority (DJP)
4. Issue tax invoices (Faktur Pajak)

---

## Implementation

### Tax Rate Configuration

```php
// config/accounting.php
'tax' => [
    'ppn' => [
        'rate' => 11,              // 11%
        'name' => 'PPN',
        'description' => 'Pajak Pertambahan Nilai',
    ],
    'pph' => [
        'rates' => [
            'pph21' => 5,          // Employee income tax
            'pph23' => 2,          // Service withholding
        ],
    ],
],
```

### Tax Calculation

```php
// In Invoice/Bill calculation
public function calculateTax(): void
{
    $taxRate = config('accounting.tax.ppn.rate') / 100;

    foreach ($this->items as $item) {
        if ($item->is_taxable) {
            $item->tax_amount = (int) round($item->subtotal * $taxRate);
        }
    }

    $this->tax_amount = $this->items->sum('tax_amount');
    $this->total = $this->subtotal + $this->tax_amount;
}
```

### Database Storage

```php
// Invoices/Bills store tax separately
$table->bigInteger('subtotal');           // Before tax
$table->bigInteger('tax_amount');         // PPN amount
$table->bigInteger('total');              // subtotal + tax

// Line items
$table->boolean('is_taxable')->default(true);
$table->bigInteger('tax_amount')->default(0);
```

### PPN Reporting

```
Sales PPN (Output Tax):
  Sum of tax_amount from all invoices in period

Purchase PPN (Input Tax):
  Sum of tax_amount from all bills in period

Net PPN Payable:
  Output Tax - Input Tax
  If positive: Pay to DJP
  If negative: Claim refund or carry forward
```

### Journal Entries

```
Sale with PPN:
DR Accounts Receivable       Rp 11,100,000
CR Sales Revenue             Rp 10,000,000
CR PPN Output (Liability)    Rp  1,100,000

Purchase with PPN:
DR Inventory                 Rp 10,000,000
DR PPN Input (Asset)         Rp  1,100,000
CR Accounts Payable          Rp 11,100,000

Monthly PPN Settlement:
DR PPN Output                Rp 1,100,000
CR PPN Input                 Rp   800,000
CR PPN Payable               Rp   300,000
```

### Tax Accounts

| Account | Type | Purpose |
|---------|------|---------|
| PPN Masukan | Asset | Input tax from purchases |
| PPN Keluaran | Liability | Output tax from sales |
| PPN Kurang Bayar | Liability | Net PPN payable |
| PPN Lebih Bayar | Asset | Net PPN receivable |

---

## References

- [ADR-0006: SAK EMKM Compliance](./0006-sak-emkm-compliance.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

