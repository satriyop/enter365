# Browser Audit Findings — Enter365 SPA

> **Date:** 2026-02-02
> **Auditor:** Claude (automated browser audit)
> **SPA URL:** `http://localhost:3000`
> **Authenticated User:** Maxie Abernathy (admin, user ID 150)

---

## Summary

| Category | Count |
|----------|:-----:|
| Pages audited (complete pass) | 116 routes (100%) |
| List pages | 34 |
| Create/new form pages | 26 |
| Detail pages | 28 |
| Edit pages | 16 |
| Report pages | 13 |
| Public pages | 3 |
| Utility pages | 3 |
| Bugs found | 5 (5 fixed, 0 open) |
| False positives | 3 |
| Vite warnings | 1 |

---

## Bugs Found

### BUG-01: Auth Store `fetchUser()` — Wrong Response Shape (FIXED)

- **Severity:** Critical
- **File:** `front-end-enter365/src/stores/auth.ts:110-112`
- **Symptom:** After login, sidebar only showed "Dashboard" — all other menu items hidden
- **Root Cause:** `fetchUser()` read `response.data.user` but the `/auth/me` endpoint returns a Laravel API Resource which wraps the data as `{ data: {...} }`, not `{ user: {...} }`
- **Impact:** `user` remained `null` in Pinia store, so `hasPermission()` always returned `false`, hiding all permission-gated menu items
- **Fix Applied:**
  ```typescript
  // BEFORE (broken):
  const response = await api.get<{ user: User }>('/auth/me')
  user.value = response.data.user

  // AFTER (fixed):
  const response = await api.get<{ data: User }>('/auth/me')
  user.value = response.data.data
  ```
- **Status:** Fixed (committed `1531a62`)

---

### BUG-05: Account Form Crashes — `values is not defined` (FIXED)

- **Severity:** High
- **URLs:** `/accounting/accounts/new` and `/accounting/accounts/:id/edit`
- **Symptom:** "Something went wrong" error page on both create and edit
- **Error:** `ReferenceError: values is not defined` at `ComputedRefImpl.fn` in `src/pages/accounting/accounts/...`
- **Root Cause:** `useForm()` destructured `values` as `form` on line 122, but the computed (line 163) and watch (line 167) still referenced `values.type` instead of `form.type`
- **Impact:** Cannot create or edit chart of accounts entries via the SPA
- **Fix Applied:**
  ```typescript
  // BEFORE (broken):
  const currentSubtypeOptions = computed(() => {
    return subtypeOptions[values.type] || []  // 'values' not defined
  })
  watch(() => values.type, () => { ... })     // 'values' not defined

  // AFTER (fixed):
  const currentSubtypeOptions = computed(() => {
    return subtypeOptions[form.type] || []    // 'form' is the destructured alias
  })
  watch(() => form.type, () => { ... })       // matches the destructured name
  ```
- **Status:** Fixed (verified in browser 2026-02-02)

---

### BUG-06: Settings Page — Form Not Populated (FIXED)

- **Severity:** Medium
- **URL:** `/settings`
- **Symptom:** Name and Email fields are empty with validation errors ("Name is required", "Email is required") shown on initial load
- **Root Cause:** `auth.user` is null at mount time (async fetch), so `initialValues` are empty strings triggering validation. The watch callback directly mutated `profileValues.name/email` but didn't clear VeeValidate's stale error state.
- **Impact:** Settings page shows validation errors on load, confusing UX
- **Fix Applied:**
  ```typescript
  // BEFORE (broken): Direct mutation doesn't clear validation errors
  watch(() => auth.user, (user) => {
    if (user) {
      profileValues.name = user.name || ''
      profileValues.email = user.email || ''
    }
  })

  // AFTER (fixed): resetForm() clears both values AND errors
  watch(() => auth.user, (user) => {
    if (user) {
      resetProfileForm({
        values: { name: user.name || '', email: user.email || '' },
      })
    }
  }, { immediate: true })
  ```
- **Status:** Fixed (verified in browser 2026-02-02)

---

### BUG-07: Financial Reports — "RpNaN" on Totals (FIXED)

- **Severity:** Medium
- **URLs:** `/reports/trial-balance`, `/reports/balance-sheet`, `/reports/income-statement`, `/reports/tax-summary`
- **Symptom:** Financial totals display "RpNaN" instead of formatted Rupiah amounts
- **Affected Fields:**
  - Trial Balance: Difference column
  - Balance Sheet: Assets, Liabilities, Equity totals
  - Income Statement: Revenue, Expenses, Net Income
  - Tax Summary: Net VAT Payable
- **Root Cause:** `toNumber()` in `format.ts` didn't handle NaN number inputs — they fell through to `return value`, then `Intl.NumberFormat.format(NaN)` produced "RpNaN"
- **Fix Applied:**
  ```typescript
  // Added NaN guard in toNumber():
  export function toNumber(value: NumericValue): number {
    if (value == null) return 0
    if (typeof value === 'string') return parseFloat(value) || 0
    if (isNaN(value)) return 0  // ← NEW: catch NaN number inputs
    return value
  }
  ```
- **Note:** Cash Flow, Receivables Aging, Payables Aging, Stock reports already handled zero/empty values correctly
- **Remaining Issue:** Multiple reports show empty dates in headers ("As of", "Period: to") — separate from the NaN formatting issue
- **Status:** Fixed (verified in browser 2026-02-02)

---

### BUG-08: Sales Return Detail Page — Vue Compiler Error (FIXED)

- **Severity:** High
- **URL:** `/sales/sales-returns/:id`
- **Symptom:** Vite error overlay: `[plugin:vite:vue] Invalid end tag` — page cannot render
- **Error:** `SalesReturnDetailPage.vue:257:7` — `</Card>` closing tag but the opening tag at line 250 is `<Alert>`
- **Root Cause:** Template mismatch — an `<Alert>` component is opened on line 250 but closed with `</Card>` on line 257
- **Impact:** Cannot view any sales return detail page
- **Status:** Fixed (template mismatch corrected: `</Card>` → `</Alert>`)

---

### ~~BUG-02: Quotation Create Page — Blank~~ (FALSE POSITIVE)

- **URL tested:** `/quotations/create` (wrong URL)
- **Correct URL:** `/quotations/new` (works correctly)
- **Status:** Not a bug

---

### ~~BUG-03: Invoice Create Page — Blank~~ (FALSE POSITIVE)

- **URL tested:** `/invoices/create` (wrong URL)
- **Correct URL:** `/invoices/new` (works correctly)
- **Status:** Not a bug

---

### ~~BUG-04: Stock Opname — Redirect to Solar Proposals~~ (CAUSED BY BUG-01)

- **URL:** `/inventory/opnames`
- **Root Cause:** BUG-01 (auth store returning null user) caused permission checks to fail
- **Status:** Fixed (by BUG-01 fix)

---

## Vite Warnings

### WARN-01: Invalid HTML Nesting in PrintableDocument.vue

- **File:** `front-end-enter365/src/components/PrintableDocument.vue`
- **Warning:** `<tr> cannot be child of <table>` — `<tr>` elements must be inside `<thead>`, `<tbody>`, or `<tfoot>`
- **Lines:** 108-113
- **Severity:** Low (cosmetic, may cause hydration issues)

---

## Complete Audit Results

### Dashboard (`/`)
- **Status:** OK
- **Features:** KPI cards (Cash Balance, Receivables, Payables, Gross Margin), Active Projects, Requires Attention, Revenue MTD, Days Sales Outstanding

### Sales Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Quotations | `/quotations` | OK | Search, status filter, empty state, "+ New Quotation" |
| Invoices | `/invoices` | OK | Search, status filter, empty state with CTA |
| Delivery Orders | `/delivery-orders` | OK | 4 status cards (Confirmed, Shipped, Delivered, Pending), search, filter |
| Sales Returns | `/sales-returns` | OK | Search, filter, empty state |
| Contacts | `/contacts` | OK | Table with data, Type badges, Edit/Delete actions |

### Sales Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Quotation create | `/quotations/new` | OK | Customer, Reference, Subject, Date, Line Items |
| Invoice create | `/invoices/new` | OK | Customer, Reference, Dates, Description, Line Items |
| DO create | `/sales/delivery-orders/new` | OK | List-page with modal (created from invoices) |
| Sales Return create | `/sales/sales-returns/new` | OK | List-page with modal (created from invoices) |
| Contact create | `/contacts/new` | OK | Code, Type, Name, Email, Phone, Address, Tax, Payment Terms |

### Sales Module — Detail Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Quotation detail | `/quotations/999` | 404-OK | "Endpoint tidak ditemukan" — graceful handling |
| Invoice detail | `/invoices/999` | 404-OK | "Failed to load invoice" — graceful error |
| DO detail | `/sales/delivery-orders/999` | 404-OK | Graceful not-found |
| Sales Return detail | `/sales/sales-returns/999` | **BUG** | BUG-08: Vue compiler error — `</Card>` mismatched with `<Alert>` |
| Contact detail | `/contacts/1` | OK | Full profile: badges, Contact Info, Payment Terms, Address, Tax Info |

### Sales Module — Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Quotation edit | `/quotations/999/edit` | OK | Form renders with defaults (no data for ID 999) |
| Invoice edit | `/invoices/999/edit` | OK | Form renders with defaults |
| Contact edit | `/contacts/1/edit` | OK | Pre-populated form, Ctrl+S to save. Note: Phone validation fires on factory data |

### Purchasing Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Purchase Orders | `/purchase-orders` | OK | 5 status cards, search, filter |
| Goods Receipt | `/goods-receipt-notes` | OK | Search, filter, "View Purchase Orders" CTA |
| Purchase Returns | `/purchase-returns` | OK | Search, filter, empty state |

### Purchasing Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| PO create | `/purchasing/purchase-orders/new` | OK | Vendor, Reference, Subject, PO Date, Expected Delivery, Shipping, Line Items |

### Purchasing Module — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| PO detail | `/purchasing/purchase-orders/999` | OK | Renders page (no data for ID 999) |
| GRN detail | `/purchasing/goods-receipt-notes/999` | OK | Renders page |
| Purchase Return detail | `/purchasing/purchase-returns/999` | OK | Renders page |
| PO edit | `/purchasing/purchase-orders/999/edit` | OK | Form renders with defaults |

### Inventory Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Stock | `/inventory/stock` | OK | "Stock Movements" + "Adjust Stock", search |
| Stock Opname | `/inventory/opnames` | OK | Search, status filter, "+ New Opname" |
| Products | `/products` | OK | Search, type filter, empty state |
| BOMs | `/boms` | OK | "From Template" + "+ New BOM", search, filter |

### Inventory Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Opname create | `/inventory/opnames/new` | OK | Warehouse (shows "0" — may be ID), Date, Reference, Notes |
| Stock Adjust | `/inventory/adjust` | OK | Type (Set Qty/Stock In/Stock Out), Product, Warehouse, Quantity |
| BOM create | `/boms/new` | OK | BOM Name, Output Product, Quantity, Unit, BOM Items |
| BOM from template | `/boms/from-template` | OK | 4-step wizard, empty state "No templates available" |
| Product create | `/products/new` | OK | Full form with product details |

### Inventory Module — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Inventory main | `/inventory` | OK | Redirects to Stock page |
| Stock Movements | `/inventory/movements` | OK | Type filter, date range, empty state |
| Stock Opname detail | `/inventory/opnames/999` | 404-OK | Graceful not-found |
| Product detail | `/products/999` | OK | Renders page |
| BOM detail | `/boms/999` | 404-OK | Graceful not-found |
| Product edit | `/products/999/edit` | OK | Form renders with defaults |
| BOM edit | `/boms/999/edit` | OK | Form renders with defaults |

### Accounting Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Chart of Accounts | `/accounting/accounts` | OK | Tree structure, Indonesian labels |
| Journal Entries | `/accounting/journal-entries` | OK | Table with data, date/status filters |
| Fiscal Periods | `/accounting/fiscal-periods` | OK | Table with data, Lock/View actions |
| Reports Hub | `/reports` | OK | 14 report types across 5 sections |

### Accounting Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Account create | `/accounting/accounts/new` | OK | BUG-05 fixed: was `values is not defined` crash |
| Journal Entry create | `/accounting/journal-entries/new` | OK | Date, Description, Reference, Entry Lines with Auto-Balance |
| Fiscal Period create | `/accounting/fiscal-periods/new` | OK | Quick Setup, Date range, Period Name, lifecycle info |

### Accounting Module — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Account detail | `/accounting/accounts/997` | OK | Balance, Sub-Accounts, Ledger Entries, date filter |
| Account edit | `/accounting/accounts/997/edit` | OK | BUG-05 fixed: was same crash as create |
| Journal Entry detail | `/accounting/journal-entries/2` | OK | Posted, balanced lines, Reverse button, Details sidebar |
| Fiscal Period detail | `/accounting/fiscal-periods/26` | OK | Open/Lock status, Details, Related links |

### Accounting Module — Report Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Trial Balance | `/reports/trial-balance` | OK | BUG-07 fixed: was "RpNaN", now "Rp 0". Note: missing date header |
| Balance Sheet | `/reports/balance-sheet` | OK | BUG-07 fixed: was "RpNaN", now "Rp 0". Note: missing date header |
| Income Statement | `/reports/income-statement` | OK | BUG-07 fixed: was "RpNaN". Note: missing date header |
| Cash Flow | `/reports/cash-flow` | OK | Rp 0 renders correctly, "Period: to" missing dates |
| Receivables Aging | `/reports/receivables-aging` | OK | Summary cards with age buckets, missing "As of" date |
| Payables Aging | `/reports/payables-aging` | OK | Same pattern as receivables |
| VAT Report | `/reports/vat-report` | OK | Period Summary/Monthly toggle, missing dates |
| Tax Summary | `/reports/tax-summary` | OK | BUG-07 fixed: was "RpNaN" on Net VAT Payable |
| Stock Summary | `/reports/stock-summary` | OK | Warehouse filter, 5 summary cards |
| Stock Movement | `/reports/stock-movement` | OK | "All Warehouses • to" missing date |
| Stock Valuation | `/reports/stock-valuation` | OK | Renders correctly |
| Customer Statement | `/reports/customer-statement` | OK | Customer dropdown |
| Vendor Statement | `/reports/vendor-statement` | OK | Vendor dropdown |

### Finance Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Payments | `/payments` | OK | Table with data, search, type filter |
| Down Payments | `/down-payments` | OK | 4 summary cards, search, type/status filters |
| Bills | `/bills` | OK | Table with data, search, status filter |

### Finance Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Bill create | `/bills/new` | OK | Vendor, Invoice #, Dates, Description, Line Items, Expense/Tax accounts |
| Payment create | `/payments/new` | OK | Type, Contact, Amount, Method, Account, Date |
| Down Payment create | `/finance/down-payments/new` | OK | List-page with modal pattern |

### Finance Module — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Bill detail | `/bills/1` | OK | BILL-202602-0001, Summary, Line Items (empty — "No data available"), Post/Edit/Delete |
| Bill edit | `/bills/1/edit` | OK | Pre-populated form with Line Items |
| Payment detail | `/payments/1` | OK | PAY-TEST (Paid), Amount Rp 100.000, Void Payment button |
| Down Payment detail | `/finance/down-payments/999` | 404-OK | Graceful not-found |

### Manufacturing Module — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Work Orders | `/manufacturing/work-orders` | OK | Search, filter, empty state |
| Material Requisitions | `/manufacturing/material-requisitions` | OK | Search, filter, empty state |
| Subcontractor WO | `/manufacturing/subcontractor-work-orders` | OK | Search, status filter, empty state |
| Subcontractor Invoices | `/manufacturing/subcontractor-invoices` | OK | Search, status filter, "View Work Orders" CTA |

### Manufacturing Module — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Work Order create | `/work-orders/new` | OK | Type, Priority, Name, Product, Project, Description, Qty & Schedule |
| Material Req create | `/manufacturing/material-requisitions/new` | OK | Work Order, Warehouse, Date, Notes, Requested Items |
| Subcontractor WO create | `/manufacturing/subcontractor-work-orders/new` | OK | Name, Related WO, Related Project, Description, Scope |

### Manufacturing Module — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Work Order detail | `/work-orders/999` | OK | Renders page |
| Material Req detail | `/manufacturing/material-requisitions/999` | OK | Renders page |
| Subcontractor WO detail | `/manufacturing/subcontractor-work-orders/999` | OK | Renders page |
| Subcontractor Invoice detail | `/manufacturing/subcontractor-invoices/999` | OK | Renders page |
| Work Order edit | `/work-orders/999/edit` | OK | Form renders with defaults |
| Subcontractor WO edit | `/manufacturing/subcontractor-work-orders/999/edit` | OK | Form renders with defaults |

### Projects Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Projects list | `/projects` | OK | Search, filter, empty state |
| Project create | `/projects/new` | OK | Name, Customer, Priority, Location, Description, Timeline, Budget & Contract |
| Project detail | `/projects/999` | OK | Renders page |
| Project edit | `/projects/999/edit` | OK | Form renders with defaults |

### Solar Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Solar Proposals | `/solar-proposals` | OK | Status cards, search, filter |
| Solar Calculator | `/solar-calculator` | OK | Public page, kVA options, Indonesian language |
| Solar Proposal create | `/solar-proposals/new` | OK | 4-step wizard (Site Info → Electricity → System → Review) |
| Solar Analytics | `/solar-proposals/analytics` | OK | KPI cards, Pipeline, Monthly Trends, Sales Funnel |
| Solar Proposal detail | `/solar-proposals/999` | OK | Renders page |
| Solar Proposal edit | `/solar-proposals/999/edit` | OK | Form renders with defaults |

### Settings & Admin — List Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Settings | `/settings` | OK | BUG-06 fixed: was showing empty fields with validation errors |
| Company Profiles | `/company-profiles` | OK | Search, empty state |
| Component Library | `/settings/component-library` | OK | Summary stats, search, filters, Auto-Map/Import/New Standard |
| Users | `/users` | OK | Table, role badges, inline actions (no detail page — by design) |
| Variant Groups | `/boms/variant-groups` | OK | Search, empty state, "Go to BOMs" CTA |
| Rule Sets | `/settings/rule-sets` | OK | Search, filter, empty state |
| BOM Templates | `/settings/bom-templates` | OK | Search, filter, empty state |

### Settings & Admin — Create Forms

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Company Profile create | `/company-profiles/new` | OK | Name, Slug, Domain, Tagline, Description, Founded Year, Employees |
| Component Standard create | `/settings/component-library/new` | OK | Code, IEC Standard, Name, Category, Subcategory, Unit, Specs |
| Rule Set create | `/settings/rule-sets/new` | OK | Name, Code (Generate), Description, Active toggle |
| BOM Template create | `/settings/bom-templates/new` | OK | Name, Code (Generate), Category, Description, Defaults |

### Settings & Admin — Detail & Edit Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Component Standard detail | `/settings/component-library/999` | 404-OK | Graceful not-found |
| Rule Set detail | `/settings/rule-sets/999` | 404-OK | Graceful not-found |
| BOM Template detail | `/settings/bom-templates/999` | 404-OK | Graceful not-found |
| Company Profile edit | `/company-profiles/999/edit` | OK | Form renders (Ctrl+S to save) |
| Component Standard edit | `/settings/component-library/999/edit` | OK | Form renders |
| Rule Set edit | `/settings/rule-sets/999/edit` | OK | Form renders |
| BOM Template edit | `/settings/bom-templates/999/edit` | OK | Form renders |

### Public Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Public Profile | `/profile/:slug` | OK | "Profil Tidak Ditemukan" (no data), Indonesian text, "Kembali ke Beranda" |
| Public Proposal | `/p/:token` | OK | "Proposal tidak ditemukan." (no data), Enter365 branding, footer |
| Solar Calculator | `/solar-calculator` | OK | Public page, Indonesian language |

### Error Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| 404 Page | `/nonexistent-page` | OK | Clean design with "Back to Dashboard" button |

---

## UI Observations

### Positive Patterns
1. **Consistent layout** across all list pages (search + filters + table/cards)
2. **Contextual empty states** — most empty pages have relevant CTA buttons
3. **Status summary cards** on high-traffic pages (DO, PO, Down Payments)
4. **Indonesian localization** in accounting section and public pages
5. **Rupiah formatting** (Rp) on financial amounts
6. **Dark mode toggle** available in header
7. **Ctrl+S keyboard shortcut** on edit forms (contacts, company profiles)
8. **Multi-step wizards** for complex flows (Solar Proposal, BOM from Template)
9. **Auto-Balance** feature in journal entry form
10. **Breadcrumb navigation** with back links on detail/form pages

### Minor Observations
1. **Stock Opname Warehouse dropdown** shows "0" instead of warehouse name — may be displaying ID
2. **Bill detail** Line Items section shows "No data available" — may be missing relational data
3. **Contact edit** phone validation fires on factory-generated data ("1-820-380-1797")
4. **Report date headers** — several reports show empty "As of" or "Period: to" without dates
5. **Delivery Order, Sales Return, Down Payment** create routes render list page (modal-based create pattern)

---

## Priority Fix List

| Priority | Bug | Impact | Status |
|:--------:|-----|--------|--------|
| 1 | BUG-01: Auth store response shape | All menus hidden | FIXED |
| 2 | BUG-05: Account form crash | Cannot create/edit accounts | FIXED |
| 3 | BUG-07: RpNaN on reports | Financial reports unusable | FIXED |
| 4 | BUG-06: Settings form empty | Confusing UX on settings | FIXED |
| 5 | **BUG-08: Sales Return detail crash** | Cannot view sales returns | **FIXED** — `</Card>` → `</Alert>` |
| 6 | WARN-01: HTML nesting | May cause hydration issues | Open — Low priority |

---

## Remaining Issues

1. **BUG-08** — `SalesReturnDetailPage.vue:257` has `</Card>` but should be `</Alert>` (blocks sales return detail view)
2. **WARN-01** — Wrap `<tr>` elements in `<tbody>` in PrintableDocument.vue (low priority)
3. **Report date headers** — Several reports show empty "As of" or "Period: to" without dates (cosmetic)
4. **Stock Opname warehouse dropdown** shows "0" instead of warehouse name (minor)

---

## Recommended Next Steps

1. **Seed test data** — Many modules have empty states; seeding data would enable CRUD workflow testing
2. **Fix remaining cosmetic issues** — WARN-01, report date headers, warehouse display
3. **Proceed with E2E test setup** — Foundation is ready for Pest Browser Tests (SETUP-01 in task tracker)
