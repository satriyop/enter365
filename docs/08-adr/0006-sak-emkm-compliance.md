---
adr: "0006"
title: "SAK EMKM Accounting Standard Compliance"
status: accepted
date: 2024-11-01
deciders: [Product Team, Architecture Team]
tags: [domain, compliance, indonesian-context, accounting]
related_adrs: [0008, 0011, 0012, 0027]
related_modules: [core-accounting, financial-reports]
impact: high
---

# ADR-0006: SAK EMKM Accounting Standard Compliance

## AI Agent Quick Reference

**Use this ADR when:**
- Implementing accounting features
- Understanding chart of accounts structure
- Working with financial reports
- Validating Indonesian regulatory compliance

**Key takeaway:** Enter365 follows SAK EMKM (Indonesian SME accounting standard) which requires accrual accounting, double-entry bookkeeping, and specific financial statement formats.

---

## Context

Enter365 targets Indonesian SMEs (Usaha Mikro, Kecil, dan Menengah) in the electrical panel and solar EPC industries. These businesses must comply with Indonesian accounting standards for:

- Tax reporting (DJP - Direktorat Jenderal Pajak)
- Bank loan applications
- Business partner due diligence
- Government procurement eligibility

### What is SAK EMKM?

**SAK EMKM** = Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah

Indonesian Financial Accounting Standard for Micro, Small, and Medium Entities, issued by the Indonesian Accountants Association (IAI - Ikatan Akuntan Indonesia).

---

## Decision Drivers

1. **Regulatory Compliance** - Must meet Indonesian tax authority requirements
2. **Bank Requirements** - SMEs need compliant books for loans
3. **Simplicity** - SAK EMKM is simpler than full PSAK (Indonesian IFRS)
4. **Market Fit** - Target customers are SMEs that must use SAK EMKM
5. **Competitive Advantage** - Many competitors lack proper SAK EMKM compliance

---

## SAK EMKM Requirements

### 1. Accrual Basis Accounting

Transactions recorded when they occur, not when cash is exchanged.

```php
// File: /app/Services/Accounting/JournalService.php

// Invoice creates receivable immediately (not when paid)
$journalEntry = JournalEntry::create([
    'entry_date' => $invoice->invoice_date,  // Event date
    'source_type' => Invoice::class,
    'source_id' => $invoice->id,
]);

// Debit: Accounts Receivable
// Credit: Sales Revenue
```

### 2. Double-Entry Bookkeeping

Every transaction affects at least two accounts; debits must equal credits.

```php
// File: /app/Models/Accounting/JournalEntry.php

public function isBalanced(): bool
{
    $totalDebit = $this->lines->sum('debit');
    $totalCredit = $this->lines->sum('credit');

    return $totalDebit === $totalCredit;
}
```

### 3. Chart of Accounts Structure

SAK EMKM defines account categories:

| Code Range | Type | Indonesian | English |
|------------|------|------------|---------|
| 1-xxxx | Asset | Aset | Asset |
| 2-xxxx | Liability | Kewajiban | Liability |
| 3-xxxx | Equity | Ekuitas | Equity |
| 4-xxxx | Revenue | Pendapatan | Revenue |
| 5-xxxx | Expense | Beban | Expense |

```php
// File: /app/Models/Accounting/Account.php

public const TYPE_ASSET = 'asset';
public const TYPE_LIABILITY = 'liability';
public const TYPE_EQUITY = 'equity';
public const TYPE_REVENUE = 'revenue';
public const TYPE_EXPENSE = 'expense';

// Subtypes
public const SUBTYPE_CURRENT_ASSET = 'current';
public const SUBTYPE_FIXED_ASSET = 'fixed';
public const SUBTYPE_CURRENT_LIABILITY = 'current';
public const SUBTYPE_LONG_TERM_LIABILITY = 'long_term';
```

### 4. Required Financial Statements

SAK EMKM requires:
- **Neraca** (Balance Sheet) - Financial position
- **Laporan Laba Rugi** (Income Statement) - Performance

Enter365 additionally provides:
- Cash Flow Statement
- Trial Balance
- Aging Reports
- COGS Reports

### 5. Fiscal Year

Indonesian fiscal year is calendar year (January 1 - December 31).

```php
// File: /database/seeders/FiscalPeriodSeeder.php

FiscalPeriod::create([
    'name' => 'Tahun Buku 2024',
    'start_date' => '2024-01-01',
    'end_date' => '2024-12-31',
    'status' => 'open',
]);
```

### 6. Tax Compliance (PPN)

PPN (Pajak Pertambahan Nilai) = VAT at 11%.

```php
// File: /config/accounting.php

'tax' => [
    'default_rate' => 11.00,  // PPN 11%
    'tax_name' => 'PPN',
],
```

---

## Decision

**Chosen option:** Full SAK EMKM compliance as the foundation of Enter365's accounting system.

All accounting features are designed to meet SAK EMKM requirements, ensuring:
- Accrual-based recording
- Double-entry enforcement
- SAK EMKM chart of accounts structure
- Compliant financial statements
- Indonesian fiscal year (Jan-Dec)
- PPN tax calculations

---

## Rationale

### Why SAK EMKM (not full PSAK):

1. **Target Market** - SMEs are required to use SAK EMKM, not full PSAK
2. **Simplicity** - SAK EMKM is less complex than PSAK
3. **Practicality** - Most SMEs don't need IFRS-level reporting
4. **Compliance** - Meets all regulatory requirements

### Why Strict Compliance:

1. **Trust** - Customers trust SAK EMKM-compliant books
2. **Banks** - Loan applications require proper books
3. **Tax** - DJP audits verify proper accounting
4. **Differentiation** - Competitors often lack compliance

---

## Consequences

### Positive

- Full regulatory compliance
- Bank-ready financial statements
- Tax audit readiness
- Customer trust and confidence
- Clear accounting structure

### Negative

- More complex than simple cash accounting
- Requires understanding of accounting principles
- Some flexibility limited by standard

### Neutral

- Staff need basic accounting training
- Reports follow Indonesian format (not international)

---

## Implementation Notes

### Chart of Accounts Seeder

```php
// File: /database/seeders/ChartOfAccountsSeeder.php

$accounts = [
    // ASSETS (1-xxxx)
    ['code' => '1-1000', 'name' => 'Kas dan Setara Kas', 'type' => 'asset', 'subtype' => 'current'],
    ['code' => '1-1100', 'name' => 'Kas', 'type' => 'asset', 'subtype' => 'current'],
    ['code' => '1-1200', 'name' => 'Bank', 'type' => 'asset', 'subtype' => 'current'],
    ['code' => '1-2000', 'name' => 'Piutang Usaha', 'type' => 'asset', 'subtype' => 'current'],
    ['code' => '1-3000', 'name' => 'Persediaan', 'type' => 'asset', 'subtype' => 'current'],
    ['code' => '1-4000', 'name' => 'Aset Tetap', 'type' => 'asset', 'subtype' => 'fixed'],

    // LIABILITIES (2-xxxx)
    ['code' => '2-1000', 'name' => 'Hutang Usaha', 'type' => 'liability', 'subtype' => 'current'],
    ['code' => '2-2000', 'name' => 'Hutang Pajak', 'type' => 'liability', 'subtype' => 'current'],
    ['code' => '2-3000', 'name' => 'Hutang Bank', 'type' => 'liability', 'subtype' => 'long_term'],

    // EQUITY (3-xxxx)
    ['code' => '3-1000', 'name' => 'Modal', 'type' => 'equity', 'subtype' => 'capital'],
    ['code' => '3-2000', 'name' => 'Laba Ditahan', 'type' => 'equity', 'subtype' => 'retained_earnings'],

    // REVENUE (4-xxxx)
    ['code' => '4-1000', 'name' => 'Pendapatan Usaha', 'type' => 'revenue', 'subtype' => 'operating'],
    ['code' => '4-2000', 'name' => 'Pendapatan Lain-lain', 'type' => 'revenue', 'subtype' => 'other'],

    // EXPENSES (5-xxxx)
    ['code' => '5-1000', 'name' => 'Harga Pokok Penjualan', 'type' => 'expense', 'subtype' => 'cogs'],
    ['code' => '5-2000', 'name' => 'Beban Operasional', 'type' => 'expense', 'subtype' => 'operating'],
];
```

### Financial Report Service

```php
// File: /app/Services/Accounting/FinancialReportService.php

public function getBalanceSheet(?string $asOfDate = null): array
{
    $asOfDate = $asOfDate ?? now()->toDateString();

    return [
        // Neraca SAK EMKM format
        'as_of_date' => $asOfDate,
        'assets' => [
            'current_assets' => $this->getCurrentAssets($asOfDate),
            'fixed_assets' => $this->getFixedAssets($asOfDate),
            'total' => $this->getTotalAssets($asOfDate),
        ],
        'liabilities' => [
            'current_liabilities' => $this->getCurrentLiabilities($asOfDate),
            'long_term_liabilities' => $this->getLongTermLiabilities($asOfDate),
            'total' => $this->getTotalLiabilities($asOfDate),
        ],
        'equity' => [
            'capital' => $this->getCapital($asOfDate),
            'retained_earnings' => $this->getRetainedEarnings($asOfDate),
            'total' => $this->getTotalEquity($asOfDate),
        ],
    ];
}

public function getIncomeStatement(string $startDate, string $endDate): array
{
    return [
        // Laporan Laba Rugi SAK EMKM format
        'period' => ['start' => $startDate, 'end' => $endDate],
        'revenue' => $this->getRevenue($startDate, $endDate),
        'cogs' => $this->getCOGS($startDate, $endDate),
        'gross_profit' => $this->getGrossProfit($startDate, $endDate),
        'operating_expenses' => $this->getOperatingExpenses($startDate, $endDate),
        'operating_income' => $this->getOperatingIncome($startDate, $endDate),
        'other_income_expense' => $this->getOtherIncomeExpense($startDate, $endDate),
        'net_income' => $this->getNetIncome($startDate, $endDate),
    ];
}
```

### Debit/Credit Rules

```php
// File: /app/Models/Accounting/Account.php

/**
 * SAK EMKM debit/credit rules:
 * - Assets & Expenses: Debit increases, Credit decreases
 * - Liabilities, Equity & Revenue: Credit increases, Debit decreases
 */
public function isDebitNormal(): bool
{
    return in_array($this->type, [
        self::TYPE_ASSET,
        self::TYPE_EXPENSE,
    ]);
}

public function isCreditNormal(): bool
{
    return in_array($this->type, [
        self::TYPE_LIABILITY,
        self::TYPE_EQUITY,
        self::TYPE_REVENUE,
    ]);
}
```

---

## Validation

**Verification Steps:**

1. Chart of accounts follows 1-5 code range structure
2. Journal entries must balance (debit = credit)
3. Balance sheet balances: Assets = Liabilities + Equity
4. Fiscal periods follow calendar year
5. PPN calculations at 11%

**Tests:**

```php
// File: /tests/Feature/FinancialReports/BalanceSheetTest.php

it('generates SAK EMKM compliant balance sheet', function () {
    $report = app(FinancialReportService::class)->getBalanceSheet();

    // Balance sheet equation must hold
    expect($report['assets']['total'])
        ->toBe($report['liabilities']['total'] + $report['equity']['total']);
});
```

---

## References

- [SAK EMKM (IAI)](https://iaiglobal.or.id/v03/files/file_standar/SAK%20EMKM.pdf)
- [Direktorat Jenderal Pajak](https://www.pajak.go.id/)
- ADR-0008: Integer Currency Storage
- ADR-0011: Double-Entry Bookkeeping
- ADR-0012: Chart of Accounts Hierarchy
- ADR-0027: Indonesian Fiscal Year
- `/docs/GLOSSARY.md` - Indonesian accounting terms

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Product Team
**Reviewers:** Accounting Advisor, Backend Team
