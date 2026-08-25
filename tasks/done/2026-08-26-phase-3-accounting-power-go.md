---
status: done
date: 2026-08-26
type: done
phase: 3
verdict: Go
persona: Accounting
---

# Phase 3 accounting power features — Go

**Verdict: Go** for bank reconciliation, budget vs actual, and recurring generate on a `general` tenant.

## Evidence (2026-08-26)

| Suite | Result | What it proves |
|-------|--------|----------------|
| `BankReconciliationTest` | **2 passed** | SPA create bank txn → match → unmatch → rematch → reconcile (`reconciled_at`); report loads Book Balance for 1-1010 |
| `BudgetServiceTest` | **26 passed** | Create/copy/approve/close; vs-actual collection |
| `BudgetApiTest` comparison | **passed** | `data.comparison[].budgeted` + `data.totals.total_budgeted` |
| `BudgetCompareTest` | **1 passed** | SPA **Budget vs Actual** shows seeded `12.345.000` and TOTAL |
| `RecurringServiceTest` | **7 passed** | `generateDueDocuments` creates Invoice with totals |
| `RecurringGenerateTest` | **1 passed** | **Generate Now** creates `invoices.recurring_template_id` |

## Root cause fixed this phase

SPA `useBudgetComparison` reads `response.data.data` with `budgeted` / `totals`. The API returned an unwrapped `{ comparison: [{ budget_amount }] }`. Table stayed on “Loading comparison data…”. Controller now wraps `data` and maps field names. Skill #53.

## Out of this Go

Monthly breakdown SPA shape still differs from `getMonthlyBreakdown()` (per-month nested vs per-account jan–dec). Not required for vs-actual. Year-end close, PPh, multi-currency.
