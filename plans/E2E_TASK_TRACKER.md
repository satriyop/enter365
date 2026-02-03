# E2E Task Tracker — Backend (Pest Browser Tests)

> **Reference:** See `plans/E2E_TEST_PLAN.md` for full test scenarios and objectives.
>
> **Objective:** Validate SPA + API integration using Pest v4 Browser Tests with factory data and DB assertions.
>
> **Location:** `tests/Browser/`

## Status Key
- `[ ]` Pending
- `[>]` In Progress
- `[x]` Done
- `[!]` Blocked (note reason)

---

## Phase 1: Foundation

- [x] **SETUP-01**: Install and configure Pest v4 Browser Testing
  - [x] Verify `pestphp/pest` v4 is installed
  - [x] Configure browser test environment (`phpunit.xml` + `tests/Pest.php`)
  - [x] Create `tests/Browser/` directory structure
  - [x] Write a smoke test that visits the SPA login page
  - [x] Write auth browser tests (login, error, redirect)

- [x] **AUDIT-01**: Browser audit of all SPA modules
  - [x] Visit every module in the SPA via browser (31 pages audited)
  - [x] Fill in the Audit Checklist in `E2E_TEST_PLAN.md`
  - [x] Document bugs found in `plans/AUDIT_FINDINGS.md` (4 bugs, 1 warning)

---

## Phase 2: Core Business — Auth & Sales

- [x] **AUTH-PEST-01**: Login/Logout browser tests
  - [x] Login with valid credentials → dashboard redirect
  - [x] Login with invalid credentials → error message
  - [x] Protected route redirect → login page
  - [x] Logout → token cleared

- [x] **SALES-PEST-01**: Quotation CRUD + workflow
  - [x] Create quotation with line items via SPA form
  - [x] Verify quotation appears in list
  - [x] Submit quotation → status changes in UI
  - [x] Approve quotation → status changes in UI
  - [x] Convert to invoice → invoice created

- [x] **SALES-PEST-02**: Invoice post + payment
  - [x] Create invoice → verify in list
  - [x] Post invoice → journal entry created (assert DB: AR debit, Revenue credit)
  - [x] Record 60% payment → status = Partial (assert DB: paid_amount)
  - [x] Record 40% payment → status = Paid (assert DB: AR = 0)
  - [x] **Assert trial balance holds after each step**

- [x] **SALES-PEST-03**: Delivery order from invoice
  - [x] Create DO from invoice → items match
  - [x] Confirm → Ship → status transitions
  - [x] **Assert stock decreased correctly**

- [x] **SALES-PEST-04**: Sales return from invoice
  - [x] Create return → verify in list
  - [x] Submit → Approve → Complete via UI workflow
  - [x] Cancel via dropdown menu
  - [x] Activity timeline, invoice link verification
  - Note: Frontend bugs documented (status mismatch, missing form page)

---

## Phase 3: Core Business — Purchasing

- [x] **PURCH-PEST-01**: Purchase order CRUD + workflow
  - [x] Create PO with line items
  - [x] Submit → Approve → status transitions
  - [x] Reject PO with reason
  - [x] Cancel PO with reason
  - [x] List page verification
  - [x] Button visibility at each status

- [ ] **PURCH-PEST-02**: GRN from PO
  - [ ] Create GRN from approved PO
  - [ ] Start receiving → set quantities → complete
  - [ ] **Assert stock increased correctly**
  - [ ] Partial receive → second GRN → PO fully received

- [ ] **PURCH-PEST-03**: Bill from PO + payment
  - [ ] Convert PO to bill
  - [ ] Post bill → journal entry (assert DB: AP credit, Expense debit)
  - [ ] Pay bill → status = Paid
  - [ ] **Assert trial balance holds**

- [ ] **PURCH-PEST-04**: Purchase return
  - [ ] Create return from bill
  - [ ] Submit → Approve → inventory reduced (assert DB)
  - [ ] **Assert return journal balanced, trial balance holds**

---

## Phase 4: Inventory & Stock

- [ ] **INV-PEST-01**: Stock levels and movements
  - [ ] Verify stock levels page shows correct quantities
  - [ ] Stock adjustment (increase) → movement recorded
  - [ ] Stock adjustment (decrease) → movement recorded
  - [ ] Transfer between warehouses

- [ ] **INV-PEST-02**: Stock opname workflow
  - [ ] Create opname → generate items
  - [ ] Start counting → update counts
  - [ ] Submit review → approve
  - [ ] **Assert variance adjustments create correct movements**

---

## Phase 5: Accounting & Reports

- [ ] **ACC-PEST-01**: Chart of accounts CRUD
  - [ ] Create account → appears in list
  - [ ] View account ledger → correct entries

- [ ] **ACC-PEST-02**: Journal entries
  - [ ] Create manual journal entry with balanced lines
  - [ ] Post entry → account balances update
  - [ ] Reverse entry → balances restored
  - [ ] **Reject unbalanced entry** (debit ≠ credit)

- [ ] **ACC-PEST-03**: Fiscal periods
  - [ ] Create period → appears in list
  - [ ] Close period → no more entries accepted

- [ ] **RPT-PEST-01**: Financial reports accuracy
  - [ ] Trial Balance: debits = credits
  - [ ] Balance Sheet: A = L + E
  - [ ] Income Statement: Revenue - Expenses = Net
  - [ ] Aging reports: correct grouping by date ranges
  - [ ] Tax reports: correct tax amounts

---

## Phase 6: Payments & Down Payments

- [ ] **PAY-PEST-01**: Payment flows
  - [ ] Create payment for invoice → invoice paid_amount updates
  - [ ] Create payment for bill → bill paid_amount updates
  - [ ] Void payment → amounts reverted
  - [ ] **Assert journal entries for each payment**

- [ ] **DP-PEST-01**: Down payment flows
  - [ ] Create down payment
  - [ ] Apply to invoice → outstanding reduced
  - [ ] Apply to bill → outstanding reduced
  - [ ] Refund → journal created

---

## Phase 7: Manufacturing & Projects

- [ ] **MFG-PEST-01**: BOM and Work Orders
  - [ ] Create BOM with components
  - [ ] Create work order from BOM
  - [ ] WO: Confirm → Start → Record consumption → Complete
  - [ ] **Assert materials consumed from inventory**

- [ ] **MFG-PEST-02**: Material Requisitions
  - [ ] Create MR from work order
  - [ ] Approve → Issue materials
  - [ ] **Assert stock decreased**

- [ ] **PRJ-PEST-01**: Project lifecycle
  - [ ] Create project → add costs and revenues
  - [ ] Project lifecycle: Planning → Active → Completed
  - [ ] **Assert profitability calculation**

---

## Phase 8: Cross-Module Chain Tests

- [ ] **CHAIN-PEST-01**: Full Sales Cycle
  - [ ] Quotation → Invoice → DO → Payment
  - [ ] Assert: Invoice Paid, Stock reduced, AR = 0, Bank increased, Trial balance

- [ ] **CHAIN-PEST-02**: Full Purchase Cycle
  - [ ] PO → GRN → Bill → Payment
  - [ ] Assert: Bill Paid, Stock increased, AP = 0, Bank decreased, Trial balance

- [ ] **CHAIN-PEST-03**: Sales + Return
  - [ ] Invoice → DO → Return → Payment void
  - [ ] Assert: Stock restored, journals balanced

- [ ] **CHAIN-PEST-04**: Purchase + Return
  - [ ] PO → GRN → Bill → Return
  - [ ] Assert: Stock reduced, journals balanced

- [ ] **CHAIN-PEST-05**: Manufacturing Chain
  - [ ] BOM → WO → MR → Output
  - [ ] Assert: Materials consumed, finished goods created

---

## Current Progress

| Phase | Total Tasks | Done | Remaining |
|-------|:-----------:|:----:|:---------:|
| 1. Foundation | 2 | 2 | 0 |
| 2. Auth & Sales | 5 | 5 | 0 |
| 3. Purchasing | 4 | 1 | 3 |
| 4. Inventory | 2 | 0 | 2 |
| 5. Accounting & Reports | 4 | 0 | 4 |
| 6. Payments | 2 | 0 | 2 |
| 7. Manufacturing & Projects | 3 | 0 | 3 |
| 8. Chain Tests | 5 | 0 | 5 |
| **Total** | **27** | **8** | **19** |
