---
adr: "0027"
title: "Indonesian Fiscal Year"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [accounting, indonesian-context]
related_adrs: [0006, 0011]
related_modules: [accounting]
impact: medium
---

# ADR-0027: Indonesian Fiscal Year

## AI Agent Quick Reference

**Use this ADR when:**
- Working with fiscal periods
- Implementing period-close features
- Building financial reports
- Understanding accounting periods

**Key takeaway:** Indonesian fiscal year is January-December, periods cannot be reopened after closing.

---

## Decision

Use calendar year (January-December) as fiscal year, matching Indonesian tax year requirements.

---

## Context

Indonesia uses:
1. Calendar year for tax reporting (January-December)
2. Monthly tax filing periods
3. Annual financial statement submission
4. No option for non-calendar fiscal years for most SMEs

---

## Implementation

### Fiscal Period Model

```php
// FiscalPeriod - represents one month
$table->integer('year');                  // 2024
$table->integer('month');                 // 1-12
$table->string('name');                   // "January 2024"
$table->date('start_date');
$table->date('end_date');
$table->boolean('is_closed')->default(false);
$table->timestamp('closed_at')->nullable();
$table->foreignId('closed_by')->nullable();
```

### Period Status Flow

```
Open → Closed
  ↑
  └── No Reopen (by design)
```

### Transaction Date Validation

```php
public function validateTransactionDate(Carbon $date): void
{
    $period = FiscalPeriod::where('year', $date->year)
        ->where('month', $date->month)
        ->first();

    if (!$period) {
        throw new FiscalPeriodNotFoundException($date);
    }

    if ($period->is_closed) {
        throw new FiscalPeriodClosedException($period);
    }
}
```

### Period Close Process

```php
public function closePeriod(FiscalPeriod $period): void
{
    DB::transaction(function () use ($period) {
        // Validate all journals balanced
        $this->validateJournalsBalanced($period);

        // Post any pending entries
        $this->postPendingEntries($period);

        // Generate period-end reports
        $this->generatePeriodReports($period);

        // Mark as closed
        $period->update([
            'is_closed' => true,
            'closed_at' => now(),
            'closed_by' => auth()->id(),
        ]);
    });
}
```

### Year-End Close

```php
public function closeYear(int $year): void
{
    DB::transaction(function () use ($year) {
        // Close all remaining open months
        FiscalPeriod::where('year', $year)
            ->where('is_closed', false)
            ->get()
            ->each(fn ($p) => $this->closePeriod($p));

        // Close income/expense to retained earnings
        $this->closeIncomeToRetainedEarnings($year);

        // Create opening balances for next year
        $this->createOpeningBalances($year + 1);
    });
}
```

### Retained Earnings Close

```php
private function closeIncomeToRetainedEarnings(int $year): void
{
    $income = $this->getYearEndBalance($year, 'income');
    $expense = $this->getYearEndBalance($year, 'expense');
    $netIncome = $income - $expense;

    // Create closing entry
    JournalEntry::create([
        'date' => Carbon::create($year, 12, 31),
        'description' => "Year-end close {$year}",
        'lines' => [
            ['account' => 'income_summary', 'debit' => $income],
            ['account' => 'expense_summary', 'credit' => $expense],
            ['account' => 'retained_earnings',
             $netIncome > 0 ? 'credit' : 'debit' => abs($netIncome)],
        ],
    ]);
}
```

### Period Utilities

```php
public function getCurrentPeriod(): FiscalPeriod
{
    return FiscalPeriod::where('year', now()->year)
        ->where('month', now()->month)
        ->firstOrFail();
}

public function getOpenPeriods(): Collection
{
    return FiscalPeriod::where('is_closed', false)
        ->orderBy('year')
        ->orderBy('month')
        ->get();
}
```

---

## References

- [ADR-0006: SAK EMKM Compliance](./0006-sak-emkm-compliance.md)
- [ADR-0011: Double-Entry Bookkeeping](./0011-double-entry-bookkeeping.md)

