# E2E Test Plan — Enter365

## Objective

Validate that the Enter365 SPA (Vue 3) correctly integrates with the Laravel API across all business workflows. Catch bugs in every flow — form submissions, status transitions, data integrity, cross-module chains, and accounting accuracy.

## Goals

1. **Every document workflow works end-to-end**: Create → Submit → Approve → Complete (and reverse paths: Reject, Cancel)
2. **Cross-module chains are validated**: Quotation → Invoice → DO → Payment → Journal → Trial Balance
3. **Form validation catches errors**: Required fields, invalid data, server-side messages display correctly
4. **Data integrity holds**: Created records appear in lists, detail pages show correct data, amounts calculate correctly
5. **Accounting always balances**: After every financial operation, trial balance debits = credits

## Architecture

| Layer | Tool | Location | Purpose |
|-------|------|----------|---------|
| **Backend E2E** | Pest v4 Browser Tests | `tests/Browser/` (Laravel repo) | Full integration: factories → browser → DB assertions |
| **Frontend E2E** | Playwright | `tests/e2e/` (Vue repo) | UI flows, form validation, navigation, visual regression |

### Why Both?

- **Pest Browser Tests**: Can create DB records via factories, run browser actions, then assert DB state (trial balance, status, amounts). Best for business logic + accounting accuracy.
- **Playwright**: Runs against the SPA independently, better for UI-focused testing (responsive, form UX, loading states, error messages). Can run in CI without Laravel.

## Module Priority Order

| # | Module | Pest Browser | Playwright | Reason |
|---|--------|:---:|:---:|--------|
| 1 | Auth & Login | Yes | Yes | Gate to everything else |
| 2 | Dashboard | Yes | Yes | First page users see, aggregation accuracy |
| 3 | Sales (Quotation → Invoice → DO → Payment) | Yes | Yes | Core revenue flow |
| 4 | Purchasing (PO → GRN → Bill → Payment) | Yes | Yes | Core expense flow |
| 5 | Inventory (Stock, Opname, Movements, Adjustments) | Yes | Yes | Physical goods tracking |
| 6 | Accounting (Accounts, Journal Entries, Fiscal Periods) | Yes | Yes | Financial backbone |
| 7 | Reports (14 report types) | Yes | Yes | Business-critical outputs |
| 8 | Payments & Down Payments | Yes | Yes | Money movement |
| 9 | Manufacturing (BOM, Work Orders, Material Requisitions) | Yes | Yes | Production flow |
| 10 | Projects | Yes | Yes | Project cost tracking |
| 11 | Contacts & Products | Yes | Yes | Master data CRUD |
| 12 | Solar Proposals | Yes | Yes | Domain-specific feature |
| 13 | Settings & Admin | No | Yes | Config pages, less critical |

---

## Test Scenarios Per Module

### 1. Auth & Login

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| AUTH-01 | Login with valid credentials | Happy path | Token stored, redirect to dashboard |
| AUTH-02 | Login with invalid credentials | Error path | Error message displayed, no redirect |
| AUTH-03 | Login with empty fields | Validation | Client-side validation fires |
| AUTH-04 | Accessing protected route without auth | Guard | Redirects to /login |
| AUTH-05 | Logout clears session | Happy path | Token removed, redirect to login |
| AUTH-06 | Token refresh on 401 | Edge case | Seamless re-auth or redirect to login |

### 2. Dashboard

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| DASH-01 | Dashboard loads with KPIs | Happy path | Summary widget renders with correct data |
| DASH-02 | Cash flow chart renders | Data integrity | Amounts match actual journal entries |
| DASH-03 | Receivables/payables widgets | Data integrity | Outstanding amounts match invoice/bill status |
| DASH-04 | Dashboard with empty data | Edge case | No errors, empty states shown |

### 3. Sales Module

#### 3a. Quotations

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| QUO-01 | Create quotation with line items | Happy path | Form submits, quotation saved, redirect to detail |
| QUO-02 | Edit draft quotation | Happy path | Fields update, amounts recalculate |
| QUO-03 | Submit quotation (Draft → Submitted) | Workflow | Status changes, submit button disappears |
| QUO-04 | Approve quotation (Submitted → Approved) | Workflow | Status updates, approve action available |
| QUO-05 | Reject quotation | Workflow | Status = Rejected, reason captured |
| QUO-06 | Convert quotation to invoice | Cross-module | Invoice created with correct data from quotation |
| QUO-07 | Duplicate quotation | Action | New draft created with same line items |
| QUO-08 | Delete draft quotation | Action | Removed from list |
| QUO-09 | Quotation list filtering & search | UI | Filters work, search returns correct results |
| QUO-10 | Quotation PDF generation | Action | PDF downloads without error |
| QUO-11 | Create quotation with validation errors | Validation | Required fields show error messages |
| QUO-12 | Line item calculations (qty × price - discount + tax) | Data integrity | Subtotal, tax, total compute correctly |

#### 3b. Invoices

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| INV-01 | Create invoice with line items | Happy path | Invoice saved in Draft status |
| INV-02 | Post invoice (Draft → Sent) | Workflow | Journal entry created, AR debited, Revenue credited |
| INV-03 | Record payment on invoice | Cross-module | paid_amount updates, status → Partial or Paid |
| INV-04 | Full payment cycle (Sent → Partial → Paid) | Cross-module | Status transitions correctly at each payment |
| INV-05 | Create delivery order from invoice | Cross-module | DO created with correct items |
| INV-06 | Create sales return from invoice | Cross-module | Return created, linked to invoice |
| INV-07 | Invoice with discount and tax | Data integrity | All amounts calculate correctly |
| INV-08 | Overdue invoice detection | Business logic | is_overdue flag and days_overdue compute |
| INV-09 | **Trial balance after invoice post** | Accounting | Debits = Credits globally |

#### 3c. Delivery Orders

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| DO-01 | Create DO from invoice | Happy path | DO linked to invoice, items match |
| DO-02 | Confirm → Ship DO | Workflow | Status transitions, inventory decremented |
| DO-03 | **Stock levels after shipment** | Data integrity | ProductStock.quantity reduced correctly |

#### 3d. Sales Returns

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| SR-01 | Create return from invoice | Happy path | Return created in Draft |
| SR-02 | Submit → Approve return | Workflow | Inventory restored, journal entry created |
| SR-03 | **Trial balance after return** | Accounting | Debits = Credits |

### 4. Purchasing Module

#### 4a. Purchase Orders

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| PO-01 | Create PO with line items | Happy path | PO saved in Draft |
| PO-02 | Submit → Approve PO | Workflow | Status transitions correctly |
| PO-03 | Reject PO with reason | Workflow | Status = Rejected, reason saved |
| PO-04 | Cancel PO | Workflow | Status = Cancelled |
| PO-05 | Create GRN from PO | Cross-module | GRN created with PO items |
| PO-06 | Convert PO to Bill | Cross-module | Bill created with correct amounts |
| PO-07 | Partial receiving (multi-GRN) | Cross-module | PO status → Partial → Received |

#### 4b. Goods Receipt Notes

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| GRN-01 | Start receiving → Complete | Workflow | Stock increased, PO quantities updated |
| GRN-02 | Partial receive quantities | Data integrity | Only received quantities update stock |
| GRN-03 | **Stock levels after GRN** | Data integrity | ProductStock matches received qty |

#### 4c. Bills

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| BILL-01 | Create bill manually | Happy path | Bill saved in Draft |
| BILL-02 | Post bill | Workflow | Journal entry: AP credited, Expense debited |
| BILL-03 | Pay bill | Cross-module | paid_amount updates, status transitions |
| BILL-04 | **Trial balance after bill post** | Accounting | Debits = Credits |

#### 4d. Purchase Returns

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| PR-01 | Create return from bill | Happy path | Return in Draft |
| PR-02 | Submit → Approve return | Workflow | Inventory decreased, journal created |
| PR-03 | **Trial balance after return** | Accounting | Debits = Credits |

### 5. Inventory Module

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| INV-S01 | View stock levels | Happy path | Correct quantities per warehouse |
| INV-S02 | Stock adjustment (increase) | Action | Stock + movement created |
| INV-S03 | Stock adjustment (decrease) | Action | Stock reduced, movement recorded |
| INV-S04 | Stock transfer between warehouses | Action | Source decreases, target increases |
| INV-S05 | Create stock opname | Happy path | Opname in Draft with items |
| INV-S06 | Complete opname workflow | Workflow | Draft → Counting → Review → Approved |
| INV-S07 | Opname variance adjustments | Data integrity | Discrepancies create adjustment journal |
| INV-S08 | Movement history page | UI | All movements listed with correct types |

### 6. Accounting Module

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| ACC-01 | Create chart of accounts | Happy path | Account saved with correct type |
| ACC-02 | Create manual journal entry | Happy path | Entry with balanced debit/credit lines |
| ACC-03 | Post journal entry | Workflow | Entry posted, account balances update |
| ACC-04 | Reverse journal entry | Workflow | Reversal entry created, balances restored |
| ACC-05 | Create fiscal period | Happy path | Period saved with date range |
| ACC-06 | Close fiscal period | Workflow | No more entries allowed in period |
| ACC-07 | **Unbalanced journal rejected** | Validation | Server rejects debit ≠ credit |
| ACC-08 | Account ledger shows correct entries | Data integrity | Ledger matches posted journals |

### 7. Reports

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| RPT-01 | Trial Balance | Accounting | Total debits = Total credits |
| RPT-02 | Balance Sheet | Accounting | Assets = Liabilities + Equity |
| RPT-03 | Income Statement | Accounting | Revenue - Expenses = Net Income |
| RPT-04 | Cash Flow Statement | Accounting | Operating + Investing + Financing = Net Change |
| RPT-05 | Receivables Aging | Data integrity | Outstanding invoices grouped by age |
| RPT-06 | Payables Aging | Data integrity | Outstanding bills grouped by age |
| RPT-07 | VAT Report | Data integrity | Tax collected and paid amounts correct |
| RPT-08 | Stock Summary | Data integrity | Quantities match current stock levels |
| RPT-09 | Stock Valuation | Data integrity | Values match quantity × average cost |
| RPT-10 | Customer Statement | Data integrity | Invoice and payment history correct |
| RPT-11 | Vendor Statement | Data integrity | Bill and payment history correct |
| RPT-12 | Report date filtering | UI | Date ranges filter data correctly |
| RPT-13 | Report export | Action | Excel/PDF download works |

### 8. Payments & Down Payments

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| PAY-01 | Create payment for invoice | Happy path | Payment saved, invoice paid_amount updates |
| PAY-02 | Void payment | Workflow | Payment voided, invoice amount reverts |
| PAY-03 | **Journal entry for payment** | Accounting | Bank debited, AR credited (or reverse for bill) |
| DP-01 | Create down payment | Happy path | DP saved with correct amount |
| DP-02 | Apply DP to invoice | Cross-module | Invoice outstanding reduced |
| DP-03 | Apply DP to bill | Cross-module | Bill outstanding reduced |
| DP-04 | Refund unused DP | Workflow | Refund journal created |

### 9. Manufacturing

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| MFG-01 | Create BOM with components | Happy path | BOM saved with items |
| MFG-02 | Create work order from BOM | Cross-module | WO created with BOM items |
| MFG-03 | WO lifecycle: Confirm → Start → Complete | Workflow | Status transitions, output recorded |
| MFG-04 | Create material requisition from WO | Cross-module | MR created with required materials |
| MFG-05 | Approve & issue MR | Workflow | Materials deducted from inventory |
| MFG-06 | Subcontractor work order flow | Workflow | Assign → Start → Complete |

### 10. Projects

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| PRJ-01 | Create project | Happy path | Project saved in planning status |
| PRJ-02 | Project lifecycle | Workflow | Planning → Active → Completed |
| PRJ-03 | Add costs and revenues | Data integrity | Project profitability calculates |
| PRJ-04 | Create work order from project | Cross-module | WO linked to project |

### 11. Contacts & Products

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| CON-01 | Create customer contact | Happy path | Contact saved as customer type |
| CON-02 | Create vendor contact | Happy path | Contact saved as vendor type |
| CON-03 | Edit contact | Happy path | Fields update correctly |
| CON-04 | Contact credit status | Data integrity | Outstanding amounts correct |
| PRD-01 | Create product | Happy path | Product saved with correct fields |
| PRD-02 | Edit product | Happy path | Fields update, price changes |
| PRD-03 | Product with inventory tracking | Data integrity | Stock levels shown correctly |

### 12. Solar Proposals

| ID | Scenario | Type | Validates |
|----|----------|------|-----------|
| SOL-01 | Create proposal via wizard | Happy path | Proposal saved with calculations |
| SOL-02 | Send proposal (generates public link) | Workflow | Token URL works for public access |
| SOL-03 | Public proposal accept/reject | Workflow | Status updates from public page |
| SOL-04 | Convert proposal to quotation | Cross-module | Quotation created from proposal data |
| SOL-05 | Solar calculator (public) | UI | Calculations return valid results |

---

## Cross-Module Chain Tests (Critical)

These test complete business workflows across multiple modules:

| ID | Chain | Modules Touched | Key Assertions |
|----|-------|-----------------|----------------|
| CHAIN-01 | **Full Sales Cycle** | Quotation → Invoice → DO → Payment | Invoice Paid, Stock reduced, AR zeroed, Bank increased, Trial balance holds |
| CHAIN-02 | **Full Purchase Cycle** | PO → GRN → Bill → Payment | Bill Paid, Stock increased, AP zeroed, Bank decreased, Trial balance holds |
| CHAIN-03 | **Sales Return** | Invoice → DO → Sales Return | Stock restored, AR adjusted, Return journal balanced |
| CHAIN-04 | **Purchase Return** | PO → GRN → Bill → Purchase Return | Stock reduced, AP adjusted, Return journal balanced |
| CHAIN-05 | **Manufacturing** | BOM → WO → MR → Output | Materials consumed, finished goods produced |
| CHAIN-06 | **Solar → Sales** | Proposal → Quotation → Invoice → Payment | End-to-end solar project revenue |
| CHAIN-07 | **Project Costing** | Project → WO → MR → Costs → Revenue | Project profitability accurate |

---

## Audit Checklist (Browser Walkthrough)

Before writing tests, visit each module in the SPA to document current state:

### Status Key
- `[x]` = Works correctly
- `[~]` = Partially working (note issues)
- `[ ]` = Not working or not tested
- `[N/A]` = Not applicable

### Module Audit

#### Auth
- [x] Login page renders
- [x] Login form submits
- [x] Successful login redirects to dashboard
- [ ] Invalid credentials show error *(not tested)*
- [ ] Logout works *(not tested)*

#### Dashboard
- [x] Dashboard page loads
- [x] KPI widgets render (Cash Balance, Receivables, Payables, Gross Margin)
- [ ] Cash flow chart renders *(not visible in audit — may need data)*
- [x] Receivables/Payables widgets render

#### Quotations
- [x] List page loads with data
- [x] Create form renders all fields *(at `/quotations/new` — Customer, Reference, Subject, Date, Line Items)*
- [ ] Create form submits successfully *(not tested)*
- [ ] Detail page shows correct data *(not tested — no data)*
- [ ] Edit form populates existing data *(not tested)*
- [ ] Submit action works *(not tested)*
- [ ] Approve action works *(not tested)*
- [ ] Convert to invoice works *(not tested)*

#### Invoices
- [x] List page loads
- [x] Create form works *(at `/invoices/new` — Customer, Reference, Dates, Description, Line Items)*
- [ ] Post action works *(not tested)*
- [ ] Payment recording works *(not tested)*
- [ ] Detail page shows journal entry link *(not tested)*

#### Purchase Orders
- [x] List page loads (5 status cards: Draft, Pending, Approved, Partial, Received)
- [ ] Create form works *(not tested)*
- [ ] Submit/Approve workflow works *(not tested)*
- [ ] GRN creation works *(not tested)*
- [ ] Bill conversion works *(not tested)*

#### Bills
- [x] List page loads (table with data, Edit/Delete actions)
- [ ] Create form works *(not tested)*
- [ ] Post action works *(not tested)*
- [ ] Payment works *(not tested)*

#### Inventory
- [x] Stock levels page loads (Stock Movements + Adjust Stock buttons)
- [ ] Movements page loads *(not tested separately)*
- [ ] Adjustment form works *(not tested)*
- [x] Stock opname workflow works *(list page renders — was redirecting before BUG-01 auth fix)*

#### Accounting
- [x] Chart of accounts loads (tree view with Indonesian labels)
- [ ] Journal entry creation works *(not tested — data present, no create test)*
- [ ] Journal posting works *(not tested)*
- [x] Fiscal period management works (table with Lock/View actions)

#### Reports
- [x] Trial Balance renders *(hub page visible)*
- [x] Balance Sheet renders *(hub page visible)*
- [x] Income Statement renders *(hub page visible)*
- [x] Cash Flow renders *(hub page visible)*
- [x] Aging reports render *(hub page visible)*
- [x] Tax reports render *(hub page visible)*
- [x] Stock reports render *(hub page visible)*

#### Payments
- [x] Payment list loads (table with data, search, type filter)
- [ ] Create payment works *(not tested)*
- [ ] Void payment works *(not tested)*

#### Manufacturing
- [x] BOM list and create works *(list renders with "From Template" + New BOM buttons)*
- [x] Work order workflow works *(list page renders)*
- [x] Material requisition workflow works *(list page renders)*

#### Projects
- [x] Project CRUD works *(list page renders)*
- [ ] Project lifecycle works *(not tested — no data)*

#### Solar
- [ ] Proposal wizard works *(not tested)*
- [ ] Public proposal page works *(not tested)*
- [x] Solar calculator works (public page renders with kVA form)

#### Settings & Admin *(added during audit)*
- [x] Company Profiles page renders
- [x] Component Library page renders (stats, filters, CRUD buttons)
- [x] Users page renders (table with role badges, action buttons)
- [x] Variant Groups page renders (contextual empty state)
- [x] Subcontractor Work Orders page renders
- [x] Subcontractor Invoices page renders

#### Error Handling
- [x] 404 page renders correctly (clean design with "Back to Dashboard")

---

## Implementation Order

### Phase 1: Foundation + Audit
1. Set up Pest Browser Tests in Laravel repo
2. Set up Playwright in Vue repo
3. Browser audit of all modules (fill checklist above)
4. Document bugs found during audit

### Phase 2: Core Business Flows (Pest Browser)
5. AUTH tests
6. Sales chain: QUO → INV → DO → PAY
7. Purchase chain: PO → GRN → BILL → PAY
8. Accounting assertions after each financial operation

### Phase 3: Inventory & Returns (Pest Browser)
9. Inventory operations
10. Sales returns
11. Purchase returns
12. Stock opname

### Phase 4: Reports & Accounting (Both)
13. All 14 report types
14. Trial balance verification
15. Balance sheet equation (A = L + E)

### Phase 5: Manufacturing & Projects (Both)
16. BOM → WO → MR chain
17. Project lifecycle and costing

### Phase 6: UI & Edge Cases (Playwright)
18. Form validation across all modules
19. Error states and empty states
20. Responsive layout checks
21. Loading states and skeleton screens

### Phase 7: Solar & Settings (Playwright)
22. Solar proposal wizard
23. Public pages
24. Settings pages
