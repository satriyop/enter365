---
status: done
priority: P2
persona: Accounting
---

# Browser E2E: bank reconciliation

## Problem

Service tests cover match/reconcile (`BankReconciliationServiceTest`).  
FE `useBankTransactions` + pages exist. No browser E2E.

## Acceptance

- [x] Create or import bank transaction via UI
- [x] Match to payment (or unmatch) — match → unmatch → rematch
- [x] Reconcile; assert DB status
- [x] Optional: book vs bank report page loads with coherent balances

## Related

- `tests/Browser/BankReconciliationTest.php`
- `app/Services/Accounting/BankReconciliationService.php`
- `front-end-enter365/src/api/useBankTransactions.ts`
- `front-end-enter365/src/pages/accounting/bank-reconciliation/*`

## FE fix required for chain

`useMatchSuggestions` expected `{ data: MatchSuggestion[] }` with `payment_id` fields;  
API returns `{ transaction, suggestions: [{ id, number, amount, date, description }] }`.
