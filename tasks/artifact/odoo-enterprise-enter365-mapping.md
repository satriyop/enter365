# Odoo Enterprise ↔ Enter365 Feature Mapping

**Date:** 2026-08-11  
**Status:** Product mapping (discussion) — no code changes yet  
**Vision:** Enter365 = Odoo-like SME ERP core + industry add-ons (NEX solar, Vahana electrical panel)

---

## 1. Product model correction

| Salah (sebelumnya) | Benar (target) |
|--------------------|----------------|
| Projects / Manufacturing = vertical NEX/Vahana, default OFF | Projects / Manufacturing = **ERP umum** ala Odoo apps |
| “General company” = trading-only tanpa MFG/Project | “General company” = **core Odoo suite** (Sales, Purchase, Inventory, Accounting, + optional Project & Manufacturing) |
| Solar + BrandSwap campur di manufacturing pack | **Extract** jadi add-on terpisah |

```
┌─────────────────────────────────────────────────────────────┐
│  ENTER365 CORE (Odoo-like Enterprise suite)                 │
│  Sales · Purchase · Inventory · Accounting · Tax ID        │
│  Manufacturing (BOM/WO/MR/MRP/Subcon) · Project             │
│  Contacts · Users · Reports · Bank recon · Multi-currency   │
└───────────────────────────┬─────────────────────────────────┘
                            │
          ┌─────────────────┴─────────────────┐
          ▼                                   ▼
┌─────────────────────┐             ┌─────────────────────────┐
│ ADD-ON: NEX (Solar) │             │ ADD-ON: Vahana (Panel)  │
│ Solar proposal      │             │ Component standards     │
│ PLN tariff / solar  │             │ Brand swap              │
│   irradiation data  │             │ Spec validation rules   │
│ Public calculator   │             │ Cross-ref / mapping UI  │
│ ROI / financial     │             │ Cost optimization UI*   │
│   projections       │             │   (*if panel-specific)  │
└─────────────────────┘             └─────────────────────────┘
```

\* Cost optimization may stay core if it only does generic BOM cost compare; panel-specific brand logic stays in Vahana.

---

## 2. Odoo Enterprise apps → Enter365

Legend:

| Code | Meaning |
|------|---------|
| **CORE** | Harus ada di penawaran Enter365 “Enterprise-like”; default ON (atau always-on) |
| **PACK** | Fitur Odoo-like, optional install/toggle, **bukan** industry vertical |
| **NEX** | Extract → add-on solar EPC |
| **VAHANA** | Extract → add-on panel electrical |
| **GAP** | Ada di Odoo Enterprise, belum / tipis di Enter365 |
| **EXTRA** | Ada di Enter365, tidak ada padanan Odoo standar (lokal/ID) |

### 2.1 Sales

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| Sales (Quotations → SO) | Quotations (+ convert to Invoice) | **CORE** | Tidak ada Sales Order entity terpisah; Quotation → Invoice adalah flow utama |
| CRM (Leads/Opportunities) | — / partial via Quotation follow-up | **GAP** | QuotationActivity, follow-up, outcome ≈ thin CRM, bukan full pipeline |
| Subscriptions / Recurring | RecurringTemplate + RecurringService | **CORE** / **PACK** | Odoo Subscriptions = Enterprise; kita sudah punya |
| Rental | — | **GAP** | Tidak prioritas |
| Point of Sale | — | **GAP** | Tidak prioritas SME B2B |
| Down payments | DownPayment | **CORE** | Umum di ID / B2B |
| Delivery / shipping from SO | DeliveryOrder | **CORE** | |
| Sales returns / credit notes | SalesReturn | **CORE** | |
| Customer portal (SO track) | Partial (public solar proposal link only) | **GAP** / **NEX** | Public `/p/` = solar only |

### 2.2 Purchase

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| Purchase (RFQ/PO) | PurchaseOrder | **CORE** | |
| Vendor bills | Bill | **CORE** | Always-on accounting |
| Goods receipt | GoodsReceiptNote (GRN) | **CORE** | |
| Purchase returns | PurchaseReturn | **CORE** | |
| Landed costs | LandedCost | **CORE** / **PACK** | Odoo Inventory Enterprise-ish; kita sudah |
| Dropshipping | — | **GAP** | |

### 2.3 Inventory

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| Inventory / stock | InventoryService, ProductStock, movements | **CORE** | |
| Multi-warehouse | Warehouse | **CORE** | |
| Stock adjustment / opname | StockOpname | **CORE** | |
| Barcode / lot-serial advanced | — / partial | **GAP** | Cost layers FIFO/avg ada; full lot/serial UI tipis |
| Quality | — | **GAP** | |
| Maintenance | — | **GAP** | |
| Product master | Product, ProductCategory | **CORE** | |

### 2.4 Manufacturing (Odoo MRP)

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| BOM | Bom, BomItem, BomTemplate, VariantGroup | **PACK** (Odoo-like) | **Bukan** vertical; default bisa ON untuk “manufacturing company” atau OFF untuk pure trading |
| Manufacturing / Work Orders | WorkOrder, materials, cost | **PACK** | |
| Material requisitions / pick components | MaterialRequisition | **PACK** | |
| MRP / replenishment suggestions | MrpRun, MrpDemand, MrpSuggestion | **PACK** | |
| Subcontracting | SubcontractorWorkOrder, SubcontractorInvoice | **PACK** | Odoo Enterprise strength |
| PLM | — | **GAP** | |
| Shop Floor | — | **GAP** | |
| Repairs | — | **GAP** | |
| **Brand swap / alternate brand** | BrandSwap*, ProductEquivalence, ComponentBrandMapping | **VAHANA** | Panel: ganti merek komponen setara |
| **Component standards catalog** | ComponentStandard, mapping import/export | **VAHANA** | Standar spek panel listrik |
| **Spec validation rules** | SpecValidationRule(Set), SpecValidationService | **VAHANA** | Validasi BOM terhadap rule panel |
| **Cost optimization (brand-aware)** | CostOptimizationService | **VAHANA** (if brand) / **PACK** (if pure cost) | Pisah: generic BOM cost = core pack; brand matrix = Vahana |

### 2.5 Project / Services

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| Project | Project, ProjectCost, ProjectRevenue | **PACK** (Odoo-like) | EPC, jasa, construction-style — **umum**, bukan hanya NEX |
| Tasks | Task | **PACK** | |
| Timesheets | — | **GAP** | |
| Field Service | — | **GAP** | |
| Planning / Helpdesk | — | **GAP** | |
| Project profitability reports | ProjectReportService | **PACK** | Tied to `projects` flag |
| Job / project costing strategies | JobCostingStrategy, ProjectBasedCostingStrategy | **PACK** | Accounting strategies for MFG/Project |

### 2.6 Finance / Accounting

| Odoo App / capability | Enter365 | Class | Notes |
|-----------------------|----------|-------|-------|
| Accounting / Invoicing | Invoice, JournalEntry, CoA, FiscalPeriod | **CORE** (always-on) | |
| Bank reconciliation | BankReconciliation + ID bank parsers | **CORE** + **EXTRA** | BCA/BNI/Mandiri parsers = ID value-add |
| Multi-currency / FX | Currency, ExchangeRate, FxRevaluation | **CORE** | |
| Budgets | Budget | **CORE** / **PACK** | |
| Expenses | — | **GAP** | |
| Analytic / cost centers advanced | Partial via Project costs | **GAP** | |
| Year-end close | YearEndCloseService | **CORE** | |
| Financial reports (P&L, BS, CF, aging) | Report services | **CORE** | |
| COGS / inventory strategies | COGS*, Inventory*, Returns strategies | **CORE** | Configurable policy — Odoo-like flexibility |

### 2.7 Tax (Indonesia) — Odoo localization counterpart

| Capability | Enter365 | Class | Notes |
|------------|----------|-------|-------|
| e-Faktur export | EfakturExportService | **EXTRA** / **CORE-ID** | Bukan Odoo global; wajib SME ID |
| NSFP ranges | NsfpService | **EXTRA** / **CORE-ID** | |
| PPh withholding | PphCalculationService | **PACK-ID** | Saat ini flag `pph_withholding` OFF default — OK as opt-in |

### 2.8 Solar (NEX only)

| Capability | Enter365 | Class | Notes |
|------------|----------|-------|-------|
| Solar proposal + ROI calc | SolarProposal, SolarCalculationService | **NEX** | Tidak ada di Odoo standar |
| PLN tariff / irradiance data | PlnTariff, IndonesiaSolarData | **NEX** | |
| Public solar calculator | PublicSolar* controllers | **NEX** | |
| Excel multi-sheet export | SolarProposalExport | **NEX** | |

### 2.9 HR / Website / Marketing / Studio

| Odoo area | Enter365 | Class |
|-----------|----------|-------|
| HR, Payroll, Fleet, Time Off | — | **GAP** (out of scope for now) |
| Website, eCommerce, Blog | — | **GAP** |
| Marketing Automation, Email | — | **GAP** |
| Studio / no-code | — | **GAP** |
| Documents / Sign | Attachments only | **GAP** |
| Approvals engine generic | Document status machines per entity | **CORE-ish** | Pattern domain state machine, bukan app terpisah |

### 2.10 Platform

| Capability | Enter365 | Class |
|------------|----------|-------|
| Contacts | Contact | **CORE** |
| Users / Roles / Permissions | User, Role, Permission | **CORE** |
| Company profile | CompanyProfile | **CORE** |
| Audit / status history | AuditLog, StatusHistory | **CORE** |
| Feature packs / middleware | FeatureManager, `feature:` middleware | **CORE** platform |
| Multi-company / multi-tenant | — | **GAP** (explicitly deferred) |

---

## 3. What must be **extracted** (add-on candidates)

### 3.1 NEX — Solar EPC add-on

| Item | Code / surface | Extract how |
|------|----------------|-------------|
| Feature key | `solar_proposals` | Keep flag; **only** true industry OFF-by-default |
| Models | `SolarProposal`, `IndonesiaSolarData`, `PlnTariff` | Domain stays; gated |
| Services | `Solar/*` | Gated routes + nav |
| API | `SolarProposalController`, `SolarDataController`, `PublicSolar*` | Already separable |
| FE | `/solar-proposals`, `/solar-calculator`, `/p/*` | Already feature-gated |
| Export | `SolarProposalExport` + sheets | Bundle with NEX |

**Does NOT belong only to NEX:** generic `projects` (EPC project tracking is Odoo Project). NEX may *use* projects heavily, but projects stay Odoo-like pack.

### 3.2 Vahana — Electrical panel add-on

| Item | Code / surface | Extract how |
|------|----------------|-------------|
| New feature key (proposed) | `electrical_panel` or `brand_swap` + `component_standards` + `spec_validation` | Split from generic `manufacturing` |
| Models | `ComponentStandard`, `ComponentBrandMapping`, `SpecValidationRule`, `SpecValidationRuleSet` | Vahana pack |
| Services | `BrandSwap*`, `Component*`, `SpecValidation*`, brand-aware parts of `CostOptimization` / `ProductEquivalence` | Vahana pack |
| Controllers | `ComponentStandardController`, `ComponentBrandMappingController`, `ComponentCrossReferenceController`, `ComponentMappingImportController`, `SpecValidationRuleSetController` | Gate with new flag |
| BOM coupling | `BomItem.component_standard_id`, `Bom.spec_rule_set_id` | Optional FKs: null when Vahana OFF; validation skipped |

**Stays in Manufacturing (Odoo-like) pack:** Bom, BomTemplate, BomVariantGroup, WorkOrder, MaterialRequisition, MRP, Subcontracting, WorkOrder reports.

---

## 4. Proposed feature flag model (revised)

### 4.1 Layers

```
always_on (no flag)
  invoices, bills, payments, journal_entries, accounts, fiscal_periods,
  reports (core financial), users, roles, contacts, company_profile

core_erp (default ON for “enterprise-like” deploy)
  products, quotations, delivery_orders, sales_returns, down_payments,
  purchase_orders, goods_receipt_notes, purchase_returns,
  inventory, stock_opname, warehouses,
  budgeting, recurring, multi_currency, bank_reconciliation
  + tax ID: efaktur/nsfp (recommend ON for ID market)

odoo_packs (optional, Odoo apps — NOT industry verticals)
  manufacturing  → master switch for BOM+WO+MR+MRP+subcon (or keep granular keys)
  bom, work_orders, material_requisitions, mrp, subcontracting
  projects

industry_addons (default OFF)
  solar_proposals          → NEX
  electrical_panel         → Vahana (brand swap + standards + spec validation)
  pph_withholding          → tax pack-ID (optional)
```

### 4.2 Preset suggestion

| Preset | Meaning | ON beyond always_on |
|--------|---------|---------------------|
| `general` | Trading / jasa UKM | `core_erp` only |
| `services` | Jasa / EPC light | `core_erp` + `projects` |
| `manufacturing` | Pabrik / workshop generik | `core_erp` + manufacturing packs |
| `enterprise` | Odoo-like full apps (no industry) | `core_erp` + all `odoo_packs` |
| `nex` | Solar EPC | `enterprise` packs needed + `solar_proposals` (+ usually `projects`) |
| `vahana` | Panel electrical | manufacturing packs + `electrical_panel` |
| `full` | Demo all | everything |

**Important:** Do **not** equate `manufacturing` preset with Vahana. Vahana = manufacturing + electrical_panel.

### 4.3 Default flip vs current code

Current `config/features.php` treats MFG + projects as vertical OFF in `general`.  
**After product agreement**, recommended:

| Key | Current `general` | Proposed `general` | Proposed `enterprise` |
|-----|-------------------|--------------------|------------------------|
| projects | OFF | OFF (optional pack) | **ON** |
| manufacturing / bom / wo / mr / mrp / subcon | OFF | OFF (optional pack) | **ON** |
| solar_proposals | OFF | **OFF** (NEX only) | OFF |
| electrical_panel (new) | n/a (mixed in MFG) | **OFF** | OFF |
| pph_withholding | OFF | OFF | OFF or ON for ID |

So for “penawaran Odoo Enterprise samakan”, sales pitch uses **`FEATURE_PRESET=enterprise`**, not `general`.  
`general` remains lightweight trading. Industry = add-on on top of enterprise/manufacturing/services.

---

## 5. Enter365 module inventory by class (summary)

### CORE / always-on
- Accounting stack, Invoice/Bill/Payment, CoA, Fiscal, Reports  
- Contacts, Users/Roles  
- Products, Quotation, DO, SR, DP, PO, GRN, PR, Inventory, Opname, Warehouse  
- Bank recon, multi-currency, budgeting, recurring (recommend)

### Odoo-like PACKS (umum — bukan NEX/Vahana)
- **Manufacturing:** BOM, WO, MR, MRP, Subcontracting  
- **Project:** Project, Task, project costs/revenue/reports  
- Manufacturing accounting strategies (WIP, job costing)

### NEX add-on
- Solar proposal lifecycle + calculation  
- PLN/solar master data  
- Public calculator & share links  
- Solar Excel export

### VAHANA add-on
- Component standards & brand mappings  
- Brand swap preview/execution  
- Spec validation rule sets  
- Cross-reference / import-export component mapping  
- Panel-specific cost optimization if brand-driven

### EXTRA (Indonesia / Enter365 differentiators)
- e-Faktur, NSFP, PPh  
- Local bank statement parsers  
- Indonesian UX/messages  
- Domain state machines + service-layer accounting cascade (quality layer)

### GAP vs Odoo Enterprise (not extract — future roadmap if needed)
- Full CRM pipeline, POS, HR, Website/eCommerce  
- Timesheets, Field Service, Quality, PLM, Shop Floor  
- Multi-company / multi-tenant  
- Subscriptions depth (we have basic recurring)  
- Generic customer portal beyond solar public page

---

## 6. Coupling risks (extract carefully)

| Coupling | Risk | Approach |
|----------|------|----------|
| `BomItem` → `component_standard_id` | BOM forms assume standards | Make optional; hide UI when Vahana OFF |
| `Bom` → `spec_rule_set_id` | Validate on save | Skip validation when flag OFF |
| Solar proposal → BOM variants / quotation | NEX may create quotation/BOM | Keep integration points; gate entry routes only |
| Project used by solar EPC | Projects must stay usable without solar | Already separate domains |
| CostOptimization brand logic | UI under manufacturing | Split API or gate endpoints by `electrical_panel` |

---

## 7. Penawaran / packaging (sales view)

| SKU (concept) | Includes | Like Odoo |
|---------------|----------|-----------|
| **Enter365 Core** | Sales + Purchase + Inventory + Accounting + Tax ID | Accounting + Sales + Purchase + Inventory |
| **+ Manufacturing** | BOM, WO, MR, MRP, Subcon | Manufacturing app |
| **+ Projects** | Project, tasks, profitability | Project app |
| **Enter365 Enterprise** | Core + Manufacturing + Projects + power accounting tools | “Full apps” deploy |
| **Add-on NEX** | Solar proposals & data | Industry vertical (not Odoo stock) |
| **Add-on Vahana** | Brand swap, standards, spec validation | Industry vertical (not Odoo stock) |

---

## 8. Implementation status

| Step | Status |
|------|--------|
| Classification agreed (MFG/projects = odoo packs) | done |
| `electrical_panel` flag + BE route gate | done |
| Presets: `enterprise`, `services`, `vahana`, `nex` alias | done |
| FE nav/router + in-page BOM brand UI gates | done (A1–A5) |
| BE soft-skip + omit industry resource fields | done (A3/A11) |
| Profile-aware seeders (enterprise, solar, panel) | done (A6–A8) |
| BOM templates stay generic pack; brand hooks optional | done (A12) |
| Reports hub pack filters | done (A13) |
| Physical ElectricalPanel namespace move | deferred (soft-skip + README A10) |
| Solar stays OFF on enterprise | done (config) |
| Thin CRM pack | future |

---

## 9. One-line product statement

> **Enter365** delivers Odoo Enterprise–class SME ERP (Sales, Purchase, Inventory, Accounting, Manufacturing, Projects) with Indonesian tax/banking extras, plus **optional industry add-ons** for solar EPC (**NEX**) and electrical panel manufacturing (**Vahana**).

---

*Artifact only — implementation deferred until product sign-off.*
