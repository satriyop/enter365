---
adr: "0011"
title: "Double-Entry Bookkeeping"
status: accepted
date: 2024-11-01
deciders: [Architecture Team, Accounting Advisor]
tags: [accounting, domain, core]
related_adrs: [0006, 0008, 0012]
related_modules: [core-accounting]
impact: high
---

# ADR-0011: Double-Entry Bookkeeping

## AI Agent Quick Reference

**Use this ADR when:**
- Creating journal entries
- Understanding debit/credit rules
- Debugging balance issues
- Implementing new transaction types

**Key takeaway:** Every transaction creates a balanced journal entry where total debits equal total credits.

---

## Context

Enter365 is an accounting system that must maintain accurate financial records. The fundamental principle of accounting is double-entry bookkeeping, used worldwide for 500+ years.

---

## Decision

All financial transactions are recorded using double-entry bookkeeping. Every transaction affects at least two accounts, and debits must always equal credits.

---

## Implementation

### Journal Entry Structure

```
JournalEntry (Header)
├── entry_number: JE-202401-0001
├── entry_date: 2024-01-15
├── description: "Invoice INV-202401-0001"
├── source: Invoice #123 (polymorphic)
└── lines: [
    ├── Line 1: Debit Accounts Receivable Rp 11,100,000
    └── Line 2: Credit Sales Revenue Rp 10,000,000
    └── Line 3: Credit PPN Keluaran Rp 1,100,000
]
```

### Debit/Credit Rules

| Account Type | Debit | Credit |
|--------------|-------|--------|
| Asset | Increase | Decrease |
| Expense | Increase | Decrease |
| Liability | Decrease | Increase |
| Equity | Decrease | Increase |
| Revenue | Decrease | Increase |

### Balance Validation

```php
// File: /app/Models/Accounting/JournalEntry.php

public function isBalanced(): bool
{
    $totalDebit = $this->lines->sum('debit');
    $totalCredit = $this->lines->sum('credit');

    return $totalDebit === $totalCredit;
}

// Enforced before saving
protected static function booted(): void
{
    static::saving(function (JournalEntry $entry) {
        if (!$entry->isBalanced()) {
            throw new UnbalancedJournalException(
                'Jurnal tidak balance: Debit ≠ Credit'
            );
        }
    });
}
```

### Common Journal Entries

**Sales Invoice:**
```
DR Accounts Receivable    Rp 11,100,000
CR Sales Revenue          Rp 10,000,000
CR PPN Keluaran           Rp  1,100,000
```

**Payment Received:**
```
DR Cash/Bank              Rp 11,100,000
CR Accounts Receivable    Rp 11,100,000
```

**Vendor Bill:**
```
DR Inventory/Expense      Rp 10,000,000
DR PPN Masukan            Rp  1,100,000
CR Accounts Payable       Rp 11,100,000
```

---

## Consequences

### Positive
- Complete audit trail
- Self-checking (must balance)
- Standard accounting practice
- Financial statements derivable

### Negative
- More complex than single-entry
- Requires accounting knowledge
- Every transaction needs multiple entries

---

## References

- [ADR-0006: SAK EMKM Compliance](./0006-sak-emkm-compliance.md)
- [ADR-0012: Chart of Accounts Hierarchy](./0012-chart-of-accounts-hierarchy.md)
