# Browser Audit Findings — Enter365 SPA

> **Date:** 2026-02-02
> **Auditor:** Claude (automated browser audit)
> **SPA URL:** `http://localhost:3000`
> **Authenticated User:** Maxie Abernathy (admin, user ID 150)

---

## Summary

| Category | Count |
|----------|:-----:|
| Pages audited | 31 |
| Working correctly | 31 |
| Bugs found | 1 (fixed) |
| False positives | 3 (caused by BUG-01) |
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
- **Status:** Fixed (uncommitted)

---

### ~~BUG-02: Quotation Create Page — Blank~~ (FALSE POSITIVE)

- **URL tested:** `/quotations/create` (wrong URL)
- **Correct URL:** `/quotations/new` (works correctly)
- **Explanation:** During the audit, the wrong URL was tested. The route uses `/new` not `/create`. The URL `/quotations/create` matches the `/:id` route with `id="create"`, producing a blank detail page. The actual create form at `/quotations/new` renders correctly with all fields (Customer, Reference, Subject, Date, Line Items).
- **Status:** Not a bug

---

### ~~BUG-03: Invoice Create Page — Blank~~ (FALSE POSITIVE)

- **URL tested:** `/invoices/create` (wrong URL)
- **Correct URL:** `/invoices/new` (works correctly)
- **Explanation:** Same as BUG-02. The actual create form at `/invoices/new` renders correctly.
- **Status:** Not a bug

---

### ~~BUG-04: Stock Opname — Redirect to Solar Proposals~~ (CAUSED BY BUG-01)

- **URL:** `/inventory/opnames`
- **Symptom:** During initial audit (before BUG-01 fix), navigating to `/inventory/opnames` redirected to `/solar-proposals`
- **Root Cause:** BUG-01 (auth store `fetchUser()` returning null user) caused all permission checks to fail. The navigation guard redirected to an unexpected route when the user lacked permissions.
- **Current Status:** Works correctly after BUG-01 fix. The Stock Opname list page renders with search, status filter, and "+ New Opname" button.
- **Status:** Fixed (by BUG-01 fix)

---

## Vite Warnings

### WARN-01: Invalid HTML Nesting in PrintableDocument.vue

- **File:** `front-end-enter365/src/components/PrintableDocument.vue`
- **Warning:** `<tr> cannot be child of <table>` — `<tr>` elements must be inside `<thead>`, `<tbody>`, or `<tfoot>`
- **Lines:** 108-113
- **Severity:** Low (cosmetic, may cause hydration issues)

---

## Pages Audited — Detailed Results

### Dashboard (`/`)
- **Status:** Working
- **Features:** KPI cards (Cash Balance, Receivables, Payables, Gross Margin), Active Projects section, Requires Attention section, Revenue MTD, Days Sales Outstanding, Active Projects count

### Sales Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Quotations list | `/quotations` | OK | Search, status filter, empty state, "+ New Quotation" button |
| Quotation create | `/quotations/new` | OK | Full form: Customer, Reference, Subject, Date, Line Items |
| Invoices list | `/invoices` | OK | Search, status filter, empty state with CTA |
| Invoice create | `/invoices/new` | OK | Full form: Customer, Reference, Dates, Description, Line Items |
| Delivery Orders | `/delivery-orders` | OK | Status summary cards (Confirmed, Shipped, Delivered, Pending), search, filter |
| Sales Returns | `/sales-returns` | OK | Search, filter, empty state |
| Contacts | `/contacts` | OK | Table with data (Code, Name, Type badges, Contact Info, Status), Edit/Delete actions |

### Purchasing Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Purchase Orders | `/purchase-orders` | OK | 5 status cards (Draft, Pending, Approved, Partial, Received), search, filter |
| Goods Receipt Notes | `/goods-receipt-notes` | OK | Search, filter, contextual empty state with "View Purchase Orders" CTA |
| Purchase Returns | `/purchase-returns` | OK | Search, filter, empty state |

### Inventory Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Stock | `/inventory/stock` | OK | "Stock Movements" + "Adjust Stock" buttons, search |
| Stock Opname | `/inventory/opnames` | OK | Search, status filter, "+ New Opname" button (was redirecting before BUG-01 fix) |
| Products | `/products` | OK | Search, type filter, empty state |
| BOMs | `/boms` | OK | "From Template" + "+ New BOM" buttons, search, filter |

### Accounting Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Chart of Accounts | `/accounting/accounts` | OK | Tree structure with data, search, type filter, Expand/Collapse All, Indonesian labels (Aset, Liabilitas, etc.) |
| Journal Entries | `/accounting/journal-entries` | OK | Table with data, search, date range filter, status filter, Rp format amounts |
| Fiscal Periods | `/accounting/fiscal-periods` | OK | Table with data, "Tahun Fiskal 1999", Lock/View actions |
| Reports Hub | `/reports` | OK | Comprehensive — 14 report types across Financial, Sales, Purchase, Inventory, Tax sections |

### Finance Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Payments | `/payments` | OK | Table with data, search, type filter |
| Down Payments | `/down-payments` | OK | 4 summary cards, search, type/status filters |
| Bills | `/bills` | OK | Table with data, search, status filter, Edit/Delete actions |

### Manufacturing Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Work Orders | `/manufacturing/work-orders` | OK | Search, filter, empty state |
| Material Requisitions | `/manufacturing/material-requisitions` | OK | Search, filter, empty state |
| Subcontractor WO | `/manufacturing/subcontractor-work-orders` | OK | Search, status filter, "+ New Work Order" button, empty state |
| Subcontractor Invoices | `/manufacturing/subcontractor-invoices` | OK | Search, status filter, empty state, "View Work Orders" CTA |

### Projects

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Projects | `/projects` | OK | Search, filter, empty state |

### Solar Module

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Solar Proposals | `/solar-proposals` | OK | Status cards, search, filter |
| Solar Calculator | `/solar-calculator` | OK | Public page, form with kVA options, Indonesian language |

### Settings & Admin

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| Company Profiles | `/company-profiles` | OK | Search, empty state |
| Component Library | `/settings/component-library` | OK | Summary stats (Standards, Brand Mappings, Products Mapped, Unmapped, Coverage), search, category/brand/status filters, Auto-Map/Import/New Standard buttons |
| Users | `/users` | OK | Table with data, role badges (Administrator), status (Active), Edit/Password/Deactivate/Delete actions, pagination |
| Variant Groups | `/boms/variant-groups` | OK | Search, empty state, "Go to BOMs" contextual CTA |

### Error Pages

| Page | URL | Status | Notes |
|------|-----|:------:|-------|
| 404 Page | `/nonexistent-page` | OK | Clean design with "Back to Dashboard" button |

---

## UI Observations

### Positive Patterns
1. **Consistent layout** across all list pages (search + filters + table/cards)
2. **Contextual empty states** — most empty pages have relevant CTA buttons (e.g., GRN empty state links to Purchase Orders)
3. **Status summary cards** on high-traffic pages (Delivery Orders, Purchase Orders, Down Payments)
4. **Indonesian localization** in accounting section (Aset, Liabilitas, Ekuitas)
5. **Rupiah formatting** (Rp) on financial amounts
6. **Dark mode toggle** available in header

### Areas for Improvement
1. **No data in most modules** — audit was primarily testing page rendering with empty/minimal data; CRUD workflows not tested
2. **Sidebar requires scrolling** — Settings, Solar, and Manufacturing sections are below the fold in the sidebar

---

## Recommended Next Steps

1. **Commit BUG-01 fix** — The auth store fix in `src/stores/auth.ts` needs to be committed
2. **Fix WARN-01** — Wrap `<tr>` elements in `<tbody>` in PrintableDocument.vue
3. **Seed test data** — Many modules have empty states; seeding data would enable testing CRUD workflows
4. **Proceed with E2E test setup** — Foundation is ready for Pest Browser Tests and Playwright
