---
status: open
priority: P2
persona: Accounting
---

# Browser E2E: bank reconciliation

## Problem

Service tests cover match/reconcile (`BankReconciliationServiceTest`).  
FE `useBankTransactions` + pages exist. No browser E2E.

## Acceptance

- [ ] Create or import bank transaction via UI
- [ ] Match to payment (or unmatch)
- [ ] Reconcile; assert DB status
- [ ] Optional: book vs bank report page loads with coherent balances

## Related

- `app/Services/Accounting/BankReconciliationService.php`
- `front-end-enter365/src/api/useBankTransactions.ts`
- `front-end-enter365/src/pages/accounting/bank-reconciliation/*`
