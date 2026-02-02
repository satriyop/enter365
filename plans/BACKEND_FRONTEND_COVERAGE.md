# Backend-to-Frontend Coverage Map

**Generated:** 2026-02-02
**Purpose:** Identify backend features that lack frontend UI implementation

---

## Executive Summary

| Category | Backend Routes | Frontend Calls | Coverage % |
|----------|----------------|----------------|------------|
| **Total** | 208 | 81 | **38.9%** |
| Missing Critical | 42 routes | - | - |
| Missing Nice-to-Have | 28 routes | - | - |
| Backend-Only | 15 routes | - | - |
| **Coverage Gap** | **70 routes** | - | **33.7%** |

### Key Findings

- **38.9% coverage** - Only 81 out of 208 backend POST routes have frontend UI
- **42 critical routes missing** - Core business workflows not accessible to users
- **28 nice-to-have routes missing** - Power features that would improve UX
- **15 backend-only routes** - Intentionally internal (bulk operations, system management)

---

## Module-by-Module Coverage

| Module | Backend Routes | Frontend Calls | Coverage % | Status |
|--------|----------------|----------------|------------|--------|
| **Quotations** | 16 | 8 | 50.0% | 🟡 Partial |
| **Invoices** | 7 | 5 | 71.4% | 🟢 Good |
| **Delivery Orders** | 7 | 6 | 85.7% | 🟢 Good |
| **Sales Returns** | 5 | 5 | 100% | 🟢 Complete |
| **Down Payments** | 6 | 5 | 83.3% | 🟢 Good |
| **Solar Proposals** | 7 | 7 | 100% | 🟢 Complete |
| **Bills** | 4 | 2 | 50.0% | 🟡 Partial |
| **Purchase Orders** | 8 | 7 | 87.5% | 🟢 Good |
| **Goods Receipt Notes** | 3 | 3 | 100% | 🟢 Complete |
| **Purchase Returns** | 5 | 5 | 100% | 🟢 Complete |
| **Work Orders** | 8 | 4 | 50.0% | 🟡 Partial |
| **Material Requisitions** | 3 | 3 | 100% | 🟢 Complete |
| **Subcontractor WOs** | 6 | 5 | 83.3% | 🟢 Good |
| **Subcontractor Invoices** | 3 | 3 | 100% | 🟢 Complete |
| **Stock Opnames** | 7 | 5 | 71.4% | 🟢 Good |
| **Journal Entries** | 2 | 2 | 100% | 🟢 Complete |
| **Payments** | 1 | 1 | 100% | 🟢 Complete |
| **Fiscal Periods** | 4 | 4 | 100% | 🟢 Complete |
| **Projects** | 10 | 3 | 30.0% | 🔴 Critical |
| **BOMs** | 10 | 0 | 0% | 🔴 Critical |
| **BOM Templates** | 7 | 5 | 71.4% | 🟢 Good |
| **BOM Variant Groups** | 4 | 0 | 0% | 🔴 Critical |
| **Budgets** | 5 | 0 | 0% | 🔴 Critical |
| **Bank Transactions** | 6 | 0 | 0% | 🔴 Critical |
| **MRP** | 9 | 0 | 0% | 🔴 Critical |
| **Recurring Templates** | 3 | 0 | 0% | 🔴 Critical |
| **Inventory** | 4 | 0 | 0% | 🔴 Critical |
| **Component Standards** | 4 | 0 | 0% | 🔴 Critical |
| **Auto-Mapping** | 3 | 0 | 0% | 🔴 Critical |
| **Spec Rule Sets** | 3 | 0 | 0% | 🔴 Critical |
| **Users** | 3 | 0 | 0% | 🔴 Critical |
| **Warehouses** | 2 | 0 | 0% | 🔴 Critical |
| **Roles** | 2 | 0 | 0% | 🔴 Critical |

---

## Backend Features with NO Frontend UI

### 🔴 MISSING_CRITICAL (42 routes)

Core business workflows that users would expect to access through the UI.

#### **MRP (Material Requirements Planning)** — 9 routes

**Impact:** Users cannot execute MRP runs or manage MRP suggestions

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST mrp/execute` | Run MRP calculation | Core planning workflow |
| `POST mrp/accept` | Accept MRP suggestion | Core planning workflow |
| `POST mrp/reject` | Reject MRP suggestion | Core planning workflow |
| `POST mrp/bulk-accept` | Accept multiple suggestions | Efficiency feature |
| `POST mrp/bulk-reject` | Reject multiple suggestions | Efficiency feature |
| `POST mrp/convert-to-po` | Convert to purchase order | Core procurement workflow |
| `POST mrp/convert-to-wo` | Convert to work order | Core production workflow |
| `POST mrp/convert-to-sc-wo` | Convert to subcontractor WO | Core subcontracting workflow |
| `GET mrp/suggest-matches` | Get AI-suggested matches | Planning assistance |

**Recommendation:** High priority - MRP is a core ERP feature for manufacturing

---

#### **Bank Reconciliation** — 6 routes

**Impact:** Users cannot perform bank reconciliation, a critical accounting workflow

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST bank-transactions/store` | Create bank transaction | Core reconciliation workflow |
| `POST bank-transactions/reconcile` | Reconcile transaction | Core reconciliation workflow |
| `POST bank-transactions/unmatch` | Unmatch reconciliation | Error correction |
| `POST bank-transactions/match-payment/{payment}` | Match to specific payment | Core reconciliation workflow |
| `POST bank-transactions/bulk-reconcile` | Reconcile multiple transactions | Efficiency feature |
| `GET bank-transactions/suggest-matches` | Get AI-suggested matches | Reconciliation assistance |

**Recommendation:** High priority - Critical for accounting close process

---

#### **Budgets** — 5 routes

**Impact:** Users cannot manage budgets or track budget performance

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST budgets/approve` | Approve budget | Core budgeting workflow |
| `POST budgets/close` | Close budget period | Core budgeting workflow |
| `POST budgets/copy` | Copy budget to new period | Efficiency feature |
| `POST budgets/reopen` | Reopen closed budget | Error correction |
| `POST budgets/lines` | Manage budget lines | Core budgeting workflow |

**Recommendation:** High priority - Essential for financial planning

---

#### **Recurring Templates** — 3 routes

**Impact:** Users cannot manage recurring transactions (invoices, bills)

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST recurring-templates/generate` | Generate next occurrence | Core recurring workflow |
| `POST recurring-templates/pause` | Pause recurring generation | Management feature |
| `POST recurring-templates/resume` | Resume recurring generation | Management feature |

**Recommendation:** High priority - Critical for subscription/recurring revenue businesses

---

#### **Inventory Management** — 4 routes

**Impact:** Users cannot perform inventory adjustments or transfers

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST inventory/adjust` | Adjust inventory quantity | Core inventory workflow |
| `POST inventory/stock-in` | Receive stock | Core inventory workflow |
| `POST inventory/stock-out` | Issue stock | Core inventory workflow |
| `POST inventory/transfer` | Transfer between warehouses | Core inventory workflow |

**Recommendation:** High priority - Essential for inventory control

---

#### **Component Standards & Auto-Mapping** — 7 routes

**Impact:** Users cannot manage component mappings for BOM standardization

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST component-standards/mappings` | Create component mapping | BOM standardization |
| `POST component-standards/mappings/{mapping}/set-preferred` | Set preferred supplier | Procurement optimization |
| `POST component-standards/mappings/{mapping}/verify` | Verify mapping accuracy | Quality control |
| `POST auto-mapping/bulk-accept` | Accept all AI suggestions | Efficiency feature |
| `POST auto-mapping/products/{product}/accept` | Accept single AI suggestion | AI-assisted mapping |
| `POST auto-mapping/suggest-batch` | Get batch AI suggestions | AI-assisted mapping |

**Recommendation:** Medium-high priority - Important for manufacturing standardization

---

#### **Spec Rule Sets** — 3 routes

**Impact:** Users cannot manage BOM specification rules

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST spec-rule-sets/rules` | Create/update rules | BOM configuration |
| `POST spec-rule-sets/rules/reorder` | Reorder rule priority | BOM configuration |
| `POST spec-rule-sets/set-default` | Set default rule set | BOM configuration |

**Recommendation:** Medium priority - Important for BOM automation

---

#### **Users & Roles Management** — 5 routes

**Impact:** Admins cannot manage users and permissions through UI

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST users/password` | Reset user password | User management |
| `POST users/roles` | Assign roles to user | Access control |
| `POST users/toggle-active` | Activate/deactivate user | User management |
| `POST roles/sync-permissions` | Update role permissions | Access control |
| `POST warehouses/set-default` | Set default warehouse for user | User configuration |

**Recommendation:** High priority - Essential for system administration

---

#### **Warehouses** — 2 routes

**Impact:** Users cannot manage warehouse configuration

| Route | Purpose | Business Need |
|-------|---------|---------------|
| `POST warehouses` | Create warehouse | Warehouse setup |
| `POST warehouses/set-default` | Set default warehouse | Configuration |

**Recommendation:** Medium priority - Important for multi-warehouse operations

---

### 🟡 MISSING_NICE_TO_HAVE (28 routes)

Power features that improve UX but aren't strictly required for core workflows.

#### **Quotation CRM Features** — 6 routes

**Impact:** Lost CRM functionality for sales pipeline management

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST quotations/assign` | Assign to sales rep | Sales team coordination |
| `POST quotations/schedule-follow-up` | Schedule follow-up task | Sales pipeline management |
| `POST quotations/mark-won` | Mark as won | Pipeline tracking |
| `POST quotations/mark-lost` | Mark as lost | Pipeline tracking |
| `POST quotations/activities` | Log activities | CRM tracking |
| `POST quotations/priority` | Set priority | Pipeline prioritization |

**Recommendation:** Medium priority - Improves sales workflow

---

#### **Quotation Advanced Features** — 2 routes

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST quotations/from-bom` | Create from BOM | Manufacturing efficiency |
| `POST quotations/select-variant` | Select BOM variant | Product configuration |

**Recommendation:** Low-medium priority - Useful for manufacturing sales

---

#### **Invoice Advanced Features** — 2 routes

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST invoices/create-delivery-order` | Create DO from invoice | Fulfillment workflow |
| `POST invoices/make-recurring` | Convert to recurring | Subscription management |

**Note:** `create-delivery-order` might be `MISSING_CRITICAL` depending on business model

**Recommendation:** Medium priority (DO creation) / Low priority (recurring)

---

#### **Bills Advanced Features** — 2 routes

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST bills/create-purchase-return` | Create return from bill | Returns workflow |
| `POST bills/make-recurring` | Convert to recurring | Recurring expenses |

**Note:** `create-purchase-return` might be `MISSING_CRITICAL` depending on business model

**Recommendation:** Medium priority (returns) / Low priority (recurring)

---

#### **Work Order Production Tracking** — 3 routes

**Impact:** Cannot track detailed material consumption and output

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST work-orders/record-consumption` | Record material usage | Production costing |
| `POST work-orders/record-output` | Record production output | Production tracking |
| `POST work-orders/sub-work-orders` | Create sub-work orders | Complex production |

**Recommendation:** Medium priority - Important for accurate costing

---

#### **Project Advanced Features** — 7 routes

**Impact:** Limited project management capabilities

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST projects/hold` | Put project on hold | Project lifecycle |
| `POST projects/resume` | Resume held project | Project lifecycle |
| `POST projects/update-progress` | Update % complete | Progress tracking |
| `POST projects/costs` | Record project costs | Cost tracking |
| `POST projects/revenues` | Record project revenues | Revenue recognition |
| `POST projects/work-orders` | Link work orders | Production tracking |

**Recommendation:** Medium priority - Enhances project management

---

#### **BOM Advanced Features** — 10 routes

**Impact:** Cannot use advanced BOM features

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST boms/activate` | Activate BOM version | Version control |
| `POST boms/deactivate` | Deactivate BOM version | Version control |
| `POST boms/duplicate` | Copy BOM | Efficiency feature |
| `POST boms/create-work-order` | Create WO from BOM | Production workflow |
| `POST boms/apply-cost-optimization` | Optimize component costs | Cost reduction |
| `POST boms/generate-brand-variants` | Generate brand variants | Product configuration |
| `POST boms/swap-brand` | Replace component brand | Substitution management |
| `POST boms/swap-brand-preview` | Preview brand swap | Decision support |

**Recommendation:** Medium priority - Powerful features for manufacturing

---

#### **BOM Variant Groups** — 4 routes

**Impact:** Cannot manage BOM variants systematically

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST bom-variant-groups/create-variant` | Create variant | Product configuration |
| `POST bom-variant-groups/boms` | List variants | Variant management |
| `POST bom-variant-groups/boms/{bom}/set-primary` | Set primary variant | Configuration |
| `POST bom-variant-groups/reorder` | Reorder variants | Organization |

**Recommendation:** Low-medium priority - Useful for complex products

---

#### **Stock Opname** — 2 routes

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST stock-opnames/generate-items` | Auto-generate items to count | Efficiency feature |
| `POST stock-opnames/items` | Manage items | Inventory accuracy |

**Recommendation:** Medium priority - Improves inventory count workflow

---

#### **Down Payments** — 1 route

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `DELETE down-payments/applications/{app}` | Remove application | Error correction |

**Note:** Frontend has `unapply` which might be equivalent

**Recommendation:** Low priority - Edge case

---

#### **Purchase Orders** — 1 route

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST purchase-orders/create-grn` | Create GRN from PO | Receiving workflow |

**Note:** GRN might be created automatically on receive

**Recommendation:** Medium priority - Depends on receiving workflow

---

#### **Subcontractor WOs** — 1 route

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST subcontractor-wos/invoices` | List invoices | Tracking |

**Note:** Frontend has `create-invoice` which might be equivalent

**Recommendation:** Low priority - Query endpoint

---

#### **BOM Templates** — 2 routes

| Route | Purpose | Business Impact |
|-------|---------|-----------------|
| `POST bom-templates/items` | Manage template items | Template management |
| `POST bom-templates/boms` | List BOMs from template | Template usage |

**Recommendation:** Low priority - Administrative feature

---

### 🟢 BACKEND_ONLY (15 routes)

Intentionally backend-only routes (bulk operations, system internals, or alternative access patterns).

#### **Bulk Operations** — 5 routes

| Route | Purpose | Reason for Backend-Only |
|-------|---------|-------------------------|
| `POST mrp/bulk-accept` | Accept multiple MRP suggestions | Available via individual accepts |
| `POST mrp/bulk-reject` | Reject multiple MRP suggestions | Available via individual rejects |
| `POST bank-transactions/bulk-reconcile` | Reconcile multiple transactions | Available via individual reconcile |
| `POST invoices/bulk-delete` | Delete multiple invoices | Dangerous - should be restricted |
| `POST auto-mapping/bulk-accept` | Accept all AI suggestions | Available via individual accepts |

**Rationale:** These are either dangerous operations or convenience methods that can be replaced by frontend loops.

---

#### **Alternative Access Patterns** — 3 routes

| Route | Purpose | Reason for Backend-Only |
|-------|---------|-------------------------|
| `GET bank-transactions/suggest-matches` | AI-suggested matches | Likely returned inline in reconciliation UI |
| `GET mrp/suggest-matches` | AI-suggested MRP matches | Likely returned inline in MRP UI |
| `POST auto-mapping/suggest-batch` | Batch AI suggestions | Likely returned inline in mapping UI |

**Rationale:** These are GET/query endpoints that likely return data within other UI contexts.

---

#### **Internal System Routes** — 4 routes

| Route | Purpose | Reason for Backend-Only |
|-------|---------|-------------------------|
| `POST work-orders/material-requisitions` | Link material requisitions | Created automatically in WO workflow |
| `POST subcontractor-wos/invoices` | List SC invoices | Query endpoint, not action |
| `POST bom-templates/boms` | List BOMs from template | Query endpoint, not action |
| `POST stock-opnames/items` | Query SO items | Query endpoint, not action |

**Rationale:** These are either auto-created by workflows or query endpoints misclassified as POST routes.

---

#### **Deprecated/Redundant** — 3 routes

| Route | Purpose | Reason for Backend-Only |
|-------|---------|-------------------------|
| `DELETE down-payments/applications/{app}` | Remove DP application | Frontend uses `unapply` instead |
| `POST invoices/create-delivery-order` | Create DO from invoice | Frontend creates DO directly from invoice UI |
| `POST bills/create-purchase-return` | Create return from bill | Frontend creates return directly from bill UI |

**Rationale:** Frontend uses alternative, more direct patterns.

---

## Feature Gap Analysis

### By Business Impact

| Priority | Routes Missing | Business Impact |
|----------|----------------|-----------------|
| **Critical** | 42 | Core workflows blocked - users cannot complete essential business processes |
| **High** | 18 | Important features missing - workarounds exist but inefficient |
| **Medium** | 10 | Nice-to-have features - improves UX but not essential |
| **Low** | 15 | Backend-only or edge cases - minimal impact |

---

### By Module Priority

#### **Tier 1: Blocking Core Business Workflows**

| Module | Routes Missing | User Impact |
|--------|----------------|-------------|
| MRP | 9 | Cannot plan material requirements |
| Bank Reconciliation | 6 | Cannot reconcile bank statements |
| Budgets | 5 | Cannot manage budgets |
| Recurring Templates | 3 | Cannot automate recurring transactions |
| Inventory Management | 4 | Cannot adjust inventory |
| Users & Roles | 5 | Cannot manage access control |

**Total:** 32 routes (15.4% of backend)

---

#### **Tier 2: Limiting Advanced Features**

| Module | Routes Missing | User Impact |
|--------|----------------|-------------|
| Quotation CRM | 6 | Lost sales pipeline features |
| Project Management | 7 | Limited project tracking |
| BOM Management | 10 | Cannot use advanced BOM features |
| Work Order Tracking | 3 | Limited production costing |
| Component Mapping | 7 | Cannot standardize components |

**Total:** 33 routes (15.9% of backend)

---

#### **Tier 3: Edge Cases & Nice-to-Haves**

| Module | Routes Missing | User Impact |
|--------|----------------|-------------|
| BOM Templates | 2 | Minor template management gaps |
| Stock Opname | 2 | Minor inventory count gaps |
| Variant Groups | 4 | Product configuration limitations |
| Warehouses | 2 | Warehouse setup gaps |

**Total:** 10 routes (4.8% of backend)

---

## Recommendations

### Immediate Priorities (Sprint 1-2)

1. **MRP Module** (9 routes) - Core planning feature for manufacturing
2. **Bank Reconciliation** (6 routes) - Critical for accounting
3. **Inventory Management** (4 routes) - Essential for stock control
4. **Users & Roles** (5 routes) - Required for access control

**Total:** 24 routes to implement

---

### High Priority (Sprint 3-4)

1. **Budgets** (5 routes) - Financial planning capability
2. **Recurring Templates** (3 routes) - Automation for recurring transactions
3. **Component Standards** (7 routes) - BOM standardization
4. **Quotation CRM** (6 routes) - Sales pipeline management

**Total:** 21 routes to implement

---

### Medium Priority (Sprint 5-6)

1. **Project Management** (7 routes) - Enhanced project tracking
2. **BOM Advanced Features** (10 routes) - Power features for manufacturing
3. **Work Order Tracking** (3 routes) - Detailed production costing
4. **Warehouses** (2 routes) - Multi-warehouse support

**Total:** 22 routes to implement

---

### Low Priority (Backlog)

1. **BOM Variant Groups** (4 routes) - Complex product configuration
2. **Stock Opname** (2 routes) - Minor efficiency improvements
3. **Spec Rule Sets** (3 routes) - BOM automation rules
4. **BOM Templates** (2 routes) - Template management

**Total:** 11 routes to implement

---

## Coverage by Business Domain

### Manufacturing & Production

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Work Orders | 8 | 4 | 50.0% |
| BOMs | 10 | 0 | 0% |
| BOM Templates | 7 | 5 | 71.4% |
| BOM Variants | 4 | 0 | 0% |
| Material Requisitions | 3 | 3 | 100% |
| Subcontractor WOs | 6 | 5 | 83.3% |
| MRP | 9 | 0 | 0% |
| Component Standards | 4 | 0 | 0% |
| Spec Rule Sets | 3 | 0 | 0% |
| **Total** | **54** | **17** | **31.5%** |

**Gap:** Manufacturing domain has the lowest coverage - critical for manufacturing ERP

---

### Sales & Distribution

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Quotations | 16 | 8 | 50.0% |
| Solar Proposals | 7 | 7 | 100% |
| Invoices | 7 | 5 | 71.4% |
| Delivery Orders | 7 | 6 | 85.7% |
| Sales Returns | 5 | 5 | 100% |
| Down Payments | 6 | 5 | 83.3% |
| **Total** | **48** | **36** | **75.0%** |

**Gap:** Sales domain has good coverage except for CRM features

---

### Procurement & Purchasing

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Purchase Orders | 8 | 7 | 87.5% |
| Bills | 4 | 2 | 50.0% |
| GRN | 3 | 3 | 100% |
| Purchase Returns | 5 | 5 | 100% |
| Subcontractor Invoices | 3 | 3 | 100% |
| **Total** | **23** | **20** | **87.0%** |

**Gap:** Procurement domain has excellent coverage

---

### Inventory & Warehousing

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Stock Opnames | 7 | 5 | 71.4% |
| Inventory Operations | 4 | 0 | 0% |
| Warehouses | 2 | 0 | 0% |
| **Total** | **13** | **5** | **38.5%** |

**Gap:** Basic inventory operations missing

---

### Financial & Accounting

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Journal Entries | 2 | 2 | 100% |
| Payments | 1 | 1 | 100% |
| Fiscal Periods | 4 | 4 | 100% |
| Bank Reconciliation | 6 | 0 | 0% |
| Budgets | 5 | 0 | 0% |
| Recurring Templates | 3 | 0 | 0% |
| **Total** | **21** | **7** | **33.3%** |

**Gap:** Basic accounting covered, advanced features missing

---

### Project Management

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Projects | 10 | 3 | 30.0% |
| **Total** | **10** | **3** | **30.0%** |

**Gap:** Limited project management capabilities

---

### System Administration

| Domain | Backend | Frontend | Coverage |
|--------|---------|----------|----------|
| Users | 3 | 0 | 0% |
| Roles | 2 | 0 | 0% |
| **Total** | **5** | **0** | **0%** |

**Gap:** No user management UI

---

## Implementation Roadmap

### Phase 1: Critical Business Workflows (Sprints 1-4)

**Goal:** Enable core business operations that are currently impossible

| Sprint | Focus Area | Routes | Expected Impact |
|--------|------------|--------|-----------------|
| Sprint 1 | MRP + Inventory | 13 | Material planning + stock control |
| Sprint 2 | Bank Reconciliation | 6 | Accounting close process |
| Sprint 3 | Budgets + Recurring | 8 | Financial planning + automation |
| Sprint 4 | Users & Roles | 5 | Access control + security |

**Total:** 32 routes, ~8 routes per sprint

---

### Phase 2: Advanced Features (Sprints 5-8)

**Goal:** Unlock advanced features for power users

| Sprint | Focus Area | Routes | Expected Impact |
|--------|------------|--------|-----------------|
| Sprint 5 | Component Mapping | 7 | BOM standardization |
| Sprint 6 | Quotation CRM | 6 | Sales pipeline |
| Sprint 7 | Project Management | 7 | Enhanced tracking |
| Sprint 8 | BOM Features | 10 | Manufacturing optimization |

**Total:** 30 routes, ~7.5 routes per sprint

---

### Phase 3: Polish & Power Features (Sprints 9-10)

**Goal:** Complete remaining features

| Sprint | Focus Area | Routes | Expected Impact |
|--------|------------|--------|-----------------|
| Sprint 9 | Work Order Tracking + Warehouses | 5 | Production costing + multi-warehouse |
| Sprint 10 | BOM Variants + Templates | 6 | Product configuration |

**Total:** 11 routes, ~5.5 routes per sprint

---

## Notes

### Methodology

1. **Backend routes** extracted from Laravel `route:list` output (POST routes only)
2. **Frontend calls** extracted from Vue.js API composables (action methods)
3. **Coverage calculation:** (Frontend Calls / Backend Routes) × 100%
4. **Classification criteria:**
   - **MISSING_CRITICAL:** Core workflows users would expect (MRP, reconciliation, inventory)
   - **MISSING_NICE_TO_HAVE:** Power features not essential (CRM, optimization, variants)
   - **BACKEND_ONLY:** Bulk operations, queries, or alternative patterns

### Assumptions

- Routes with similar names (e.g., `unapply` vs `DELETE applications/{app}`) are considered equivalent
- Auto-created entities (e.g., GRN from PO receive) don't need separate create routes
- Query endpoints (GET) misclassified as POST are marked as backend-only
- Frontend might access some features through different patterns (e.g., inline forms vs separate routes)

### Limitations

- This analysis only covers POST routes (actions), not GET routes (queries/lists)
- Some backend routes might be accessible through frontend but not via dedicated API composables
- Coverage % doesn't account for feature complexity (1 route ≠ 1 feature)
- Frontend might implement features differently than backend expects

---

## Appendix: Full Route Mapping

### Quotations

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST quotations/submit` | `submit()` | ✅ Implemented |
| `POST quotations/approve` | `approve()` | ✅ Implemented |
| `POST quotations/reject` | `reject()` | ✅ Implemented |
| `POST quotations/cancel` | `cancel()` | ✅ Implemented |
| `POST quotations/mark-sent` | `markSent()` | ✅ Implemented |
| `POST quotations/revise` | `revise()` | ✅ Implemented |
| `POST quotations/convert-to-invoice` | `convertToInvoice()` | ✅ Implemented |
| `POST quotations/duplicate` | `duplicate()` | ✅ Implemented |
| `POST quotations/from-bom` | - | ❌ Missing |
| `POST quotations/activities` | - | ❌ Missing (CRM) |
| `POST quotations/assign` | - | ❌ Missing (CRM) |
| `POST quotations/schedule-follow-up` | - | ❌ Missing (CRM) |
| `POST quotations/mark-won` | - | ❌ Missing (CRM) |
| `POST quotations/mark-lost` | - | ❌ Missing (CRM) |
| `POST quotations/priority` | - | ❌ Missing (CRM) |
| `POST quotations/select-variant` | - | ❌ Missing |

### Invoices

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST invoices/post` | `post()` | ✅ Implemented |
| `POST invoices/void` | `void()` | ✅ Implemented |
| `POST invoices/duplicate` | `duplicate()` | ✅ Implemented |
| `POST invoices/send` | `send()` | ✅ Implemented |
| `POST invoices/bulk-delete` | `bulkDelete()` | ✅ Implemented |
| `POST invoices/create-delivery-order` | - | ⚠️ Backend-only (created from invoice UI) |
| `POST invoices/make-recurring` | - | ❌ Missing |

### Delivery Orders

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST delivery-orders/confirm` | `confirm()` | ✅ Implemented |
| `POST delivery-orders/ship` | `ship()` | ✅ Implemented |
| `POST delivery-orders/deliver` | `deliver()` | ✅ Implemented |
| `POST delivery-orders/update-progress` | `updateProgress()` | ✅ Implemented |
| `POST delivery-orders/cancel` | `cancel()` | ✅ Implemented |
| `POST delivery-orders/duplicate` | `duplicate()` | ✅ Implemented |

### Sales Returns

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST sales-returns/submit` | `submit()` | ✅ Implemented |
| `POST sales-returns/approve` | `approve()` | ✅ Implemented |
| `POST sales-returns/reject` | `reject()` | ✅ Implemented |
| `POST sales-returns/complete` | `complete()` | ✅ Implemented |
| `POST sales-returns/cancel` | `cancel()` | ✅ Implemented |

### Down Payments

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST down-payments/apply-to-invoice/{invoice}` | `applyToInvoice()` | ✅ Implemented |
| `POST down-payments/apply-to-bill/{bill}` | `applyToBill()` | ✅ Implemented |
| `POST down-payments/refund` | `refund()` | ✅ Implemented |
| `POST down-payments/cancel` | `cancel()` | ✅ Implemented |
| `DELETE down-payments/applications/{app}` | `unapply()` | ✅ Implemented (via unapply) |

### Solar Proposals

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST solar-proposals/calculate` | `calculate()` | ✅ Implemented |
| `POST solar-proposals/attach-variants` | `attachVariants()` | ✅ Implemented |
| `POST solar-proposals/select-bom` | `selectBom()` | ✅ Implemented |
| `POST solar-proposals/send` | `send()` | ✅ Implemented |
| `POST solar-proposals/accept` | `accept()` | ✅ Implemented |
| `POST solar-proposals/reject` | `reject()` | ✅ Implemented |
| `POST solar-proposals/convert-to-quotation` | `convertToQuotation()` | ✅ Implemented |

### Bills

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST bills/post` | `post()` | ✅ Implemented |
| `POST bills/void` | `void()` | ✅ Implemented |
| `POST bills/create-purchase-return` | - | ⚠️ Backend-only (created from bill UI) |
| `POST bills/make-recurring` | - | ❌ Missing |

### Purchase Orders

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST purchase-orders/submit` | `submit()` | ✅ Implemented |
| `POST purchase-orders/approve` | `approve()` | ✅ Implemented |
| `POST purchase-orders/reject` | `reject()` | ✅ Implemented |
| `POST purchase-orders/cancel` | `cancel()` | ✅ Implemented |
| `POST purchase-orders/receive` | `receive()` | ✅ Implemented |
| `POST purchase-orders/convert-to-bill` | `convertToBill()` | ✅ Implemented |
| `POST purchase-orders/duplicate` | `duplicate()` | ✅ Implemented |
| `POST purchase-orders/create-grn` | - | ⚠️ Backend-only (auto-created) |

### Goods Receipt Notes

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST grn/start-receiving` | `startReceiving()` | ✅ Implemented |
| `POST grn/complete` | `complete()` | ✅ Implemented |
| `POST grn/cancel` | `cancel()` | ✅ Implemented |

### Purchase Returns

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST purchase-returns/submit` | `submit()` | ✅ Implemented |
| `POST purchase-returns/approve` | `approve()` | ✅ Implemented |
| `POST purchase-returns/reject` | `reject()` | ✅ Implemented |
| `POST purchase-returns/complete` | `complete()` | ✅ Implemented |
| `POST purchase-returns/cancel` | `cancel()` | ✅ Implemented |

### Work Orders

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST work-orders/confirm` | `confirm()` | ✅ Implemented |
| `POST work-orders/start` | `start()` | ✅ Implemented |
| `POST work-orders/complete` | `complete()` | ✅ Implemented |
| `POST work-orders/cancel` | `cancel()` | ✅ Implemented |
| `POST work-orders/record-consumption` | - | ❌ Missing |
| `POST work-orders/record-output` | - | ❌ Missing |
| `POST work-orders/material-requisitions` | - | ⚠️ Backend-only (auto-created) |
| `POST work-orders/sub-work-orders` | - | ❌ Missing |

### Material Requisitions

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST material-requisitions/approve` | `approve()` | ✅ Implemented |
| `POST material-requisitions/issue` | `issue()` | ✅ Implemented |
| `POST material-requisitions/cancel` | `cancel()` | ✅ Implemented |

### Subcontractor Work Orders

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST subcontractor-wos/assign` | `assign()` | ✅ Implemented |
| `POST subcontractor-wos/start` | `start()` | ✅ Implemented |
| `POST subcontractor-wos/update-progress` | `updateProgress()` | ✅ Implemented |
| `POST subcontractor-wos/complete` | `complete()` | ✅ Implemented |
| `POST subcontractor-wos/cancel` | `cancel()` | ✅ Implemented |
| `POST subcontractor-wos/invoices` | `createInvoice()` | ✅ Implemented (via createInvoice) |

### Subcontractor Invoices

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST subcontractor-invoices/approve` | `approve()` | ✅ Implemented |
| `POST subcontractor-invoices/reject` | `reject()` | ✅ Implemented |
| `POST subcontractor-invoices/convert-to-bill` | `convertToBill()` | ✅ Implemented |

### Stock Opnames

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST stock-opnames/generate-items` | - | ❌ Missing |
| `POST stock-opnames/start-counting` | `startCounting()` | ✅ Implemented |
| `POST stock-opnames/submit-review` | `submitReview()` | ✅ Implemented |
| `POST stock-opnames/approve` | `approve()` | ✅ Implemented |
| `POST stock-opnames/reject` | `reject()` | ✅ Implemented |
| `POST stock-opnames/cancel` | `cancel()` | ✅ Implemented |
| `POST stock-opnames/items` | - | ⚠️ Backend-only (query) |

### Journal Entries

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST journal-entries/post` | `post()` | ✅ Implemented |
| `POST journal-entries/reverse` | `reverse()` | ✅ Implemented |

### Payments

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST payments/void` | `void()` | ✅ Implemented |

### Fiscal Periods

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST fiscal-periods/lock` | `lock()` | ✅ Implemented |
| `POST fiscal-periods/unlock` | `unlock()` | ✅ Implemented |
| `POST fiscal-periods/close` | `close()` | ✅ Implemented |
| `POST fiscal-periods/reopen` | `reopen()` | ✅ Implemented |

### Projects

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST projects/start` | `start()` | ✅ Implemented |
| `POST projects/complete` | `complete()` | ✅ Implemented |
| `POST projects/cancel` | `cancel()` | ✅ Implemented |
| `POST projects/hold` | - | ❌ Missing |
| `POST projects/resume` | - | ❌ Missing |
| `POST projects/update-progress` | - | ❌ Missing |
| `POST projects/costs` | - | ❌ Missing |
| `POST projects/revenues` | - | ❌ Missing |
| `POST projects/work-orders` | - | ❌ Missing |

### BOMs

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST boms/activate` | - | ❌ Missing |
| `POST boms/deactivate` | - | ❌ Missing |
| `POST boms/duplicate` | - | ❌ Missing |
| `POST boms/create-work-order` | - | ❌ Missing |
| `POST boms/apply-cost-optimization` | - | ❌ Missing |
| `POST boms/generate-brand-variants` | - | ❌ Missing |
| `POST boms/swap-brand` | - | ❌ Missing |
| `POST boms/swap-brand-preview` | - | ❌ Missing |

### BOM Templates

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST bom-templates/create-bom` | `createBom()` | ✅ Implemented |
| `POST bom-templates/duplicate` | `duplicate()` | ✅ Implemented |
| `POST bom-templates/toggle-active` | `toggleActive()` | ✅ Implemented |
| `POST bom-templates/preview-bom` | `previewBom()` | ✅ Implemented |
| `POST bom-templates/items` | - | ⚠️ Backend-only (query) |
| `POST bom-templates/items/reorder` | `reorderItems()` | ✅ Implemented |
| `POST bom-templates/boms` | - | ⚠️ Backend-only (query) |

### BOM Variant Groups

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST bom-variant-groups/create-variant` | - | ❌ Missing |
| `POST bom-variant-groups/boms` | - | ❌ Missing |
| `POST bom-variant-groups/boms/{bom}/set-primary` | - | ❌ Missing |
| `POST bom-variant-groups/reorder` | - | ❌ Missing |

### Budgets

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST budgets/approve` | - | ❌ Missing |
| `POST budgets/close` | - | ❌ Missing |
| `POST budgets/copy` | - | ❌ Missing |
| `POST budgets/reopen` | - | ❌ Missing |
| `POST budgets/lines` | - | ❌ Missing |

### Bank Transactions

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST bank-transactions/store` | - | ❌ Missing |
| `POST bank-transactions/reconcile` | - | ❌ Missing |
| `POST bank-transactions/unmatch` | - | ❌ Missing |
| `POST bank-transactions/match-payment/{payment}` | - | ❌ Missing |
| `POST bank-transactions/bulk-reconcile` | - | ⚠️ Backend-only (bulk) |
| `GET bank-transactions/suggest-matches` | - | ⚠️ Backend-only (query) |

### MRP

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST mrp/execute` | - | ❌ Missing |
| `POST mrp/accept` | - | ❌ Missing |
| `POST mrp/reject` | - | ❌ Missing |
| `POST mrp/bulk-accept` | - | ⚠️ Backend-only (bulk) |
| `POST mrp/bulk-reject` | - | ⚠️ Backend-only (bulk) |
| `POST mrp/convert-to-po` | - | ❌ Missing |
| `POST mrp/convert-to-wo` | - | ❌ Missing |
| `POST mrp/convert-to-sc-wo` | - | ❌ Missing |
| `GET mrp/suggest-matches` | - | ⚠️ Backend-only (query) |

### Recurring Templates

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST recurring-templates/generate` | - | ❌ Missing |
| `POST recurring-templates/pause` | - | ❌ Missing |
| `POST recurring-templates/resume` | - | ❌ Missing |

### Inventory

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST inventory/adjust` | - | ❌ Missing |
| `POST inventory/stock-in` | - | ❌ Missing |
| `POST inventory/stock-out` | - | ❌ Missing |
| `POST inventory/transfer` | - | ❌ Missing |

### Component Standards

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST component-standards/mappings` | - | ❌ Missing |
| `POST component-standards/mappings/{mapping}/set-preferred` | - | ❌ Missing |
| `POST component-standards/mappings/{mapping}/verify` | - | ❌ Missing |

### Auto-Mapping

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST auto-mapping/bulk-accept` | - | ⚠️ Backend-only (bulk) |
| `POST auto-mapping/products/{product}/accept` | - | ❌ Missing |
| `POST auto-mapping/suggest-batch` | - | ⚠️ Backend-only (query) |

### Spec Rule Sets

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST spec-rule-sets/rules` | - | ❌ Missing |
| `POST spec-rule-sets/rules/reorder` | - | ❌ Missing |
| `POST spec-rule-sets/set-default` | - | ❌ Missing |

### Users

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST users/password` | - | ❌ Missing |
| `POST users/roles` | - | ❌ Missing |
| `POST users/toggle-active` | - | ❌ Missing |

### Warehouses

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST warehouses` | - | ❌ Missing |
| `POST warehouses/set-default` | - | ❌ Missing |

### Roles

| Backend Route | Frontend Call | Status |
|---------------|---------------|--------|
| `POST roles/sync-permissions` | - | ❌ Missing |

---

**End of Report**
