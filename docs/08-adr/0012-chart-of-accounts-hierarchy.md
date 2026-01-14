---
adr: "0012"
title: "Chart of Accounts Hierarchy"
status: accepted
date: 2024-11-01
deciders: [Architecture Team, Accounting Advisor]
tags: [accounting, data-model]
related_adrs: [0006, 0011]
related_modules: [core-accounting]
impact: high
---

# ADR-0012: Chart of Accounts Hierarchy

## AI Agent Quick Reference

**Use this ADR when:**
- Creating new accounts
- Understanding account types
- Building financial reports
- Working with account balances

**Key takeaway:** Accounts follow SAK EMKM structure: 1-Asset, 2-Liability, 3-Equity, 4-Revenue, 5-Expense.

---

## Decision

Chart of accounts uses a hierarchical structure with account codes following Indonesian SAK EMKM conventions.

---

## Implementation

### Account Code Structure

```
X-XXXX
│ │
│ └─ Sequential number within type
└─── Account type (1-5)
```

### Account Types

| Code | Type | Indonesian | Normal Balance |
|------|------|------------|----------------|
| 1-xxxx | Asset | Aset | Debit |
| 2-xxxx | Liability | Kewajiban | Credit |
| 3-xxxx | Equity | Ekuitas | Credit |
| 4-xxxx | Revenue | Pendapatan | Credit |
| 5-xxxx | Expense | Beban | Debit |

### Subtypes

```php
// File: /app/Models/Accounting/Account.php

public const SUBTYPES = [
    'asset' => ['current', 'fixed', 'other'],
    'liability' => ['current', 'long_term'],
    'equity' => ['capital', 'retained_earnings'],
    'revenue' => ['operating', 'other'],
    'expense' => ['cogs', 'operating', 'other'],
];
```

### Default Chart of Accounts

```
1-1001  Kas (Cash)
1-1002  Bank
1-1100  Piutang Usaha (Accounts Receivable)
1-1300  PPN Masukan (Input VAT)
1-3000  Persediaan (Inventory)
1-4000  Aset Tetap (Fixed Assets)

2-1100  Hutang Usaha (Accounts Payable)
2-1200  PPN Keluaran (Output VAT)
2-3000  Hutang Bank (Bank Loan)

3-1000  Modal (Capital)
3-2000  Laba Ditahan (Retained Earnings)

4-1001  Pendapatan Usaha (Sales Revenue)
4-2000  Pendapatan Lain (Other Income)

5-1001  Harga Pokok Penjualan (COGS)
5-2000  Beban Operasional (Operating Expense)
```

### Parent-Child Hierarchy

```php
$table->foreignId('parent_id')->nullable();

// Example hierarchy:
// 1-1000 Kas dan Setara Kas (parent)
//   ├── 1-1001 Kas
//   └── 1-1002 Bank
```

### Balance Calculation

```php
public function getBalanceAttribute(): int
{
    $debits = $this->journalLines()->sum('debit');
    $credits = $this->journalLines()->sum('credit');

    return $this->isDebitNormal()
        ? $debits - $credits
        : $credits - $debits;
}
```

---

## References

- [ADR-0006: SAK EMKM Compliance](./0006-sak-emkm-compliance.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)
