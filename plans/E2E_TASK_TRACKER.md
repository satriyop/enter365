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
- `[~]` Partial (note what's missing)
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

- [x] **AUTH-PEST-01**: Login/Logout browser tests (4 tests)
  - [x] Login with valid credentials → dashboard redirect
  - [x] Login with invalid credentials → error message
  - [x] Protected route redirect → login page
  - [x] Logout → token cleared

- [x] **SALES-PEST-01**: Quotation CRUD + workflow (5 tests)
  - [x] Create quotation with line items via SPA form
  - [x] Verify quotation appears in list
  - [x] Submit quotation → status changes in UI
  - [x] Approve quotation → status changes in UI
  - [x] Convert to invoice → invoice created

- [x] **SALES-PEST-02**: Invoice post + payment + accounting (5 tests)
  - [x] Create invoice → verify in list
  - [x] Post invoice → JE created (DB: AR debit, Revenue credit, Tax credit)
  - [x] Record 60% payment → status = Partial (DB: paid_amount)
  - [x] Record 40% payment → status = Paid (DB: AR = 0)
  - [x] **Assert trial balance holds after all operations**

- [x] **SALES-PEST-03**: Delivery order from invoice (4 tests)
  - [x] Create DO from invoice → items match
  - [x] Confirm → Ship → Delivered status transitions
  - [x] **Assert stock decreased correctly**

- [x] **SALES-PEST-04**: Sales return from invoice + accounting (10 tests)
  - [x] Create return → verify in list
  - [x] Submit → Approve → Complete via UI workflow
  - [x] Cancel via dropdown menu
  - [x] Activity timeline, invoice link verification
  - [x] **Approve creates correct JE (Sales Returns debit, PPN debit, AR credit)**
  - [x] **Trial balance holds after return**
  - Note: Frontend status bugs fixed (submitted vs pending)

---

## Phase 3: Core Business — Purchasing

- [x] **PURCH-PEST-01**: Purchase order CRUD + workflow (7 tests)
  - [x] Create PO with line items
  - [x] Submit → Approve → status transitions
  - [x] Reject PO with reason
  - [x] Cancel PO with reason
  - [x] List page verification
  - [x] Button visibility at each status

- [x] **PURCH-PEST-02**: GRN from PO (4 tests)
  - [x] Create GRN from approved PO → items match
  - [x] Complete full receiving workflow with stock increase
  - [x] **Assert stock increased correctly**
  - [x] Partial receive → second GRN → PO fully received

- [x] **PURCH-PEST-03**: Bill CRUD + payment + accounting (5 tests)
  - [ ] Convert PO to bill (no direct PO→Bill UI flow; bills created via form)
  - [x] Create bill via SPA form
  - [x] Post bill → JE created (DB: Expense debit, PPN debit, AP credit)
  - [x] Partial + full payment → status transitions
  - [x] **AP balance zeroed, trial balance holds**

- [x] **PURCH-PEST-04**: Purchase return + accounting (5 tests)
  - [x] View draft, submit, approve, complete via UI
  - [x] **Approve creates correct JE (AP debit, Purchase Returns credit, PPN credit)**
  - [x] **Trial balance holds after return**
  - [x] List page verification
  - Note: JE handler account codes fixed (5-2001→5-1004)

---

## Phase 4: Inventory & Stock

- [ ] **INV-PEST-01**: Stock levels and movements
  - [ ] Verify stock levels page shows correct quantities
  - [ ] Stock adjustment (increase) → movement recorded
  - [ ] Stock adjustment (decrease) → movement recorded
  - [ ] Transfer between warehouses
  - [ ] **Assert adjustment creates correct JE (Inventory Adjustment account 5-2900)**

- [ ] **INV-PEST-02**: Stock opname workflow
  - [ ] Create opname → generate items
  - [ ] Start counting → update counts
  - [ ] Submit review → approve
  - [ ] **Assert variance adjustments create correct movements and JE**

---

## Phase 5: Accounting & Reports

- [ ] **ACC-PEST-01**: Chart of accounts CRUD
  - [ ] Create account → appears in list
  - [ ] View account ledger → correct entries

- [x] **ACC-PEST-02**: Journal entries (5 tests)
  - [x] Create manual journal entry with balanced lines
  - [x] Post entry → status changes
  - [x] Reverse entry → reversal JE created, trial balance holds
  - [x] **Reject unbalanced entry** (debit ≠ credit) — frontend validation + DB check
  - [x] List page verification

- [x] **ACC-PEST-03**: Fiscal periods (2 tests)
  - [x] Lock and unlock a fiscal period via list page UI
  - [x] Close period → JE posting rejected with "closed fiscal period" error
  - Note: Period reopened after test to avoid breaking other tests

- [~] **RPT-PEST-01**: Financial reports accuracy (3 of 5 done)
  - [x] Trial Balance: page loads, TOTAL visible, DB debits = credits
  - [x] Balance Sheet: page loads, ASSETS/LIABILITIES/EQUITY sections, DB A = L + E + Net Income
  - [x] Income Statement: page loads, Revenue/Expenses sections, DB entries verified
  - [ ] Aging reports: correct grouping by date ranges (deferred)
  - [ ] Tax reports: correct tax amounts (deferred)

---

## Phase 6: Payments & Down Payments

- [x] **PAY-PEST-01**: Payment flows (7 tests)
  - [x] Create payment for invoice → paid_amount updates, JE created
  - [x] **Create payment for bill → JE verified (AP debit, Cash credit)**
  - [x] Void payment → amounts reverted, JE reversed
  - [x] **Payment JE lines verified (Cash debit, AR credit)**
  - [x] **Void reversal JE lines verified + trial balance**
  - [x] **Void bill payment → reversal JE (Cash debit, AP credit) + trial balance**
  - [x] List page verification
  - Note: Bill state machine doesn't support Paid→Received reverse; paid_amount correctly zeroed

- [x] **DP-PEST-01**: Down payment flows (8 tests)
  - [x] Create down payment via service → JE created (Cash debit, DP Liability credit)
  - [x] Apply to invoice → JE created (DP Liability debit, AR credit), trial balance
  - [x] **Apply to bill → JE created (AP debit, DP Asset credit), trial balance**
  - [x] **Refund receivable DP → JE created (DP Liability debit, Cash credit), trial balance**
  - [x] Cancel → JE reversed (reversal lines verified)
  - [x] View detail page + list page
  - Note: DP account mapping bug fixed (hardcoded codes→config, accounts seeded)

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

## Bugs Fixed During E2E Testing

| Bug | File(s) | Fix |
|-----|---------|-----|
| JE handler account codes (Sales Return) | `app/Domain/Sales/SalesReturns/Handlers/JournalEntryHandler.php` | `'4-2001'` → `'4-1004'` |
| JE handler account codes (Purchase Return) | `app/Domain/Purchasing/PurchaseReturns/Handlers/JournalEntryHandler.php` | `'5-2001'` → `'5-1004'` |
| PR frontend status mismatch | `PurchaseReturnDetailPage.vue`, `PurchaseReturnListPage.vue`, `usePurchaseReturns.ts` | `'pending'` → `'submitted'` |
| DP model hardcoded account codes | `app/Models/Sales/DownPayment.php` | Hardcoded `'2130'`/`'1140'` → `config('accounting.default_accounts.dp_*')` |
| DP service silent cash fallback | `app/Services/Sales/DownPaymentService.php` | Silent fallback → `RuntimeException` on missing account |
| DP config wrong account mapping | `config/accounting.php` | `'2-2100'`/`'1-1500'` → `'2-1700'`/`'1-1700'` |
| Missing DP accounts in CoA | `database/seeders/ChartOfAccountsSeeder.php` | Added `1-1700` (Uang Muka Pembelian) and `2-1700` (Uang Muka Penjualan) |

---

## Current Progress

| Phase | Total Tasks | Done | Partial | Remaining |
|-------|:-----------:|:----:|:-------:|:---------:|
| 1. Foundation | 2 | 2 | 0 | 0 |
| 2. Auth & Sales | 5 | 5 | 0 | 0 |
| 3. Purchasing | 4 | 4 | 0 | 0 |
| 4. Inventory | 2 | 0 | 0 | 2 |
| 5. Accounting & Reports | 4 | 2 | 1 | 1 |
| 6. Payments & DP | 2 | 2 | 0 | 0 |
| 7. Manufacturing & Projects | 3 | 0 | 0 | 3 |
| 8. Chain Tests | 5 | 0 | 0 | 5 |
| **Total** | **27** | **15** | **1** | **11** |

**Test counts:** 76 tests, 684 assertions across 15 test files.
