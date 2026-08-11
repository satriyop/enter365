# Enter365 — Feature Presets, Odoo Packs & Industry Add-ons Map

**Date:** 2026-08-12  
**Status:** Working map (in progress — isolation ~done; FE residual / full pitch polish open)  
**Source of truth (code):** `config/features.php`, `config/addons.php`, `routes/api.php`, `routes/addons/*`  
**Related:** `tasks/artifact/odoo-enterprise-enter365-mapping.md`

---

## 1. One-liner (sales / internal)

> **Enter365** = ERP SME kelas Odoo (Sales–Purchase–Inventory–Accounting + Manufacturing + Projects), plus **add-on industri opsional**: **Vahana** (panel listrik) dan **NEX** (solar EPC).

---

## 2. Three layers (bukan satu “pack industri”)

| Layer | Analogi Odoo | Isi | Default |
|-------|--------------|-----|---------|
| **1. Core ERP** | Apps dasar | Sales, Purchase, Inventory, Accounting tools, master data | **ON** |
| **2. Odoo packs** | Optional apps | Manufacturing family, Projects | **OFF** di `general` |
| **3. Industry add-ons** | Vertical modules | **Vahana** (`electrical_panel`), **NEX** (`solar_proposals`) | **OFF** |

```
┌──────────────────────────────────────────────────────────┐
│  CORE ERP  (selalu ON di preset normal)                  │
│  Master · Sales · Purchase · Inventory · Accounting      │
│  Invoice · Payment · Journal · CoA · Contacts · Products │
└──────────────────────────────────────────────────────────┘
         │ optional apps (Odoo-like packs)
         ├─ manufacturing family: BOM · WO · MR · MRP · Subcon
         └─ projects
         │ verticals (industry add-ons, default OFF)
         ├─ VAHANA  → electrical_panel
         └─ NEX     → solar_proposals
```

### Istilah yang benar

| Istilah | Artinya | Contoh |
|---------|---------|--------|
| **Odoo pack** | Fitur generik, optional install | `bom`, `work_orders`, `projects` |
| **Industry add-on** | Vertical spesifik customer | Vahana, NEX |
| **Bukan pack industri** | Manufacturing **bukan** vertical | `FEATURE_PRESET=manufacturing` ≠ Vahana |

**Vahana ≠ manufacturing.** Manufacturing bisa hidup tanpa Vahana (`enterprise` / `manufacturing` preset).

---

## 3. Presets (`FEATURE_PRESET`)

Env default: `FEATURE_PRESET=general`.  
Alias: `nex` → internal key `solar`.

Explicit `FEATURE_*` env **selalu override** default preset.

| Preset | Target customer | Core | Odoo packs | Industry |
|--------|-----------------|------|------------|----------|
| **`general`** | Trading / jasa ringan | ✓ | semua OFF | OFF |
| **`services`** | Jasa + proyek | ✓ | **projects** | OFF |
| **`manufacturing`** | Pabrik generik | ✓ | mfg+bom+wo+mr+mrp+subcon | OFF |
| **`enterprise`** | Pitch Odoo Enterprise–like | ✓ | **semua** odoo packs | **OFF** (tanpa vertical) |
| **`vahana`** | Panel electrical (Vahana) | ✓ | full manufacturing packs | **electrical_panel ON** |
| **`solar` / `nex`** | Solar EPC (NEX) | ✓ | **bom** + **projects** (shop floor OFF) | **solar_proposals ON** |
| **`full`** | Demo / test dev | ✓ | semua | **keduanya ON** |

### Pack flag matrix

| Flag | general | services | mfg | enterprise | vahana | solar/nex | full |
|------|:-------:|:--------:|:---:|:----------:|:------:|:---------:|:----:|
| manufacturing | · | · | ✓ | ✓ | ✓ | · | ✓ |
| bom | · | · | ✓ | ✓ | ✓ | ✓ | ✓ |
| work_orders | · | · | ✓ | ✓ | ✓ | · | ✓ |
| material_requisitions | · | · | ✓ | ✓ | ✓ | · | ✓ |
| mrp | · | · | ✓ | ✓ | ✓ | · | ✓ |
| subcontracting | · | · | ✓ | ✓ | ✓ | · | ✓ |
| projects | · | ✓ | · | ✓ | · | ✓ | ✓ |
| **electrical_panel** | · | · | · | · | **✓** | · | ✓ |
| **solar_proposals** | · | · | · | · | · | **✓** | ✓ |

Core modules (`products`, `quotations`, `delivery_orders`, `inventory`, `warehouses`, `budgeting`, …) **ON** di semua preset di atas kecuali di-override env.

### Override manual

```bash
FEATURE_PRESET=enterprise
FEATURE_ELECTRICAL_PANEL=true   # nyalakan Vahana di atas enterprise
FEATURE_SOLAR_PROPOSALS=true    # nyalakan NEX
```

### Decision tree

```
Mulai
  │
  ├─ Hanya jual-beli + stok + akunting? ──────────► general
  │
  ├─ Ada manajemen proyek (jasa/kontrak)? ────────► services
  │         │
  │         └─ + proposal PLTS / EPC solar? ──────► nex / solar
  │
  ├─ Ada pabrik / assembly generik? ──────────────► manufacturing
  │         │
  │         └─ + brand swap panel (Schneider/ABB/…)? ► vahana
  │
  ├─ Mau suite Odoo-like tanpa vertical? ─────────► enterprise
  │
  └─ Demo / internal full stack? ─────────────────► full
```

---

## 4. Industry add-on detail

### 4.1 Vahana — `electrical_panel`

| | |
|--|--|
| **Config** | `config/addons.php` → `electrical_panel` |
| **Flag** | `features.modules.electrical_panel` |
| **Preset tipikal** | `vahana` |
| **Packs yang ikut** | Full manufacturing (bom, wo, mrp, …) |
| **Namespace** | `App\Services\ElectricalPanel`, `App\Models\ElectricalPanel`, `App\Http\…\ElectricalPanel` |
| **Routes** | `routes/addons/electrical_panel.php` (middleware `feature:electrical_panel`; loaded inside bom pack group) |
| **Provider** | `App\Providers\Addons\ElectricalPanelServiceProvider` |
| **Core extension** | Meta tables + `App\Support\AddonExtensions` (core HTTP **zero-mention**) |

**Meta tables (bukan kolom FK di core manufacturing):**

- `electrical_panel_bom_item_meta` → `component_standard_id`
- `electrical_panel_bom_template_item_meta` → `component_standard_id`
- `electrical_panel_bom_template_meta` → `default_rule_set_id`
- `electrical_panel_bom_meta` → `spec_rule_set_id`

**Saat ON**

- Bind `BomTemplateServiceInterface` → panel (brand-aware) service
- Resource merge: `component_standard*`, `default_rule_set`, …
- Validation allow industry fields; eager-loads panel meta
- API: component standards, brand swap, cost opt, auto-mapping, spec rule sets, import mappings

**Saat OFF**

- Routes → **404**
- Resource **omit** industry fields
- Request industry fields → **prohibited**
- Core `BomTemplateService` product/manual only

**FE nav (flag ON)**

- `/addons/electrical-panel/component-library`
- `/addons/electrical-panel/rule-sets`
- `/addons/electrical-panel/cost-optimization`

---

### 4.2 NEX — `solar_proposals`

| | |
|--|--|
| **Config** | `config/addons.php` → `solar` |
| **Flag** | `features.modules.solar_proposals` |
| **Preset tipikal** | `solar` / `nex` |
| **Packs yang ikut** | `projects` + `bom` (shop floor WO/MRP OFF) |
| **Namespace** | `App\Services\Solar`, `App\Models\Solar`, `App\Http\…\Solar` |
| **Routes** | `routes/addons/solar.php` (public + auth; middleware `feature:solar_proposals`) |
| **Provider** | `App\Providers\Addons\SolarServiceProvider` |

**Saat ON**

- Solar proposal CRUD, calculate, send/accept/reject, convert → quotation
- Attach BOM variants / select BOM (butuh pack `bom`)
- Public proposal token + public calculator
- Solar data (irradiance) + PLN tariffs
- Seeders solar aktif

**Saat OFF**

- Routes → **404**
- Solar services **tidak resolve**
- Seeders solar **no-op**

**FE nav (flag ON)**

- `/solar-proposals` (+ public calculator flow)

---

### 4.3 Side-by-side

| | **Vahana ON** | **NEX ON** |
|--|---------------|------------|
| Flag | `electrical_panel` | `solar_proposals` |
| Domain | Brand/spec komponen panel | Proposal PLTS + irradiance/PLN |
| Extend core | Meta tables BOM + AddonExtensions | Proposal → quotation; attach BOM variants |
| FE | Component Library, Rule Sets, Cost Opt | Solar Proposals |
| OFF behavior | 404 + omit/prohibit fields | 404 + no service bind |

---

## 5. Endpoint / domain matrix per preset

Legenda: **✓** hidup · **·** 404 / nav hidden

### 5.1 Core ERP

| Area (`/api/v1/…`) | general | services | mfg | enterprise | vahana | nex/solar | full |
|--------------------|:-------:|:--------:|:---:|:----------:|:------:|:---------:|:----:|
| `features`, `auth/*`, users | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| accounts, journal-entries | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| contacts, company-profiles | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| products, product-categories | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| warehouses | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| inventory/* | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| quotations | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| invoices, payments, reminders | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| purchase-orders, GRN, returns | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| delivery-orders, sales-returns | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| down-payments | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| budgeting / recurring / bank recon | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| reports keuangan generik | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

### 5.2 Odoo packs

| Area | Flag | general | services | mfg | enterprise | vahana | nex | full |
|------|------|:-------:|:--------:|:---:|:----------:|:------:|:---:|:----:|
| `boms/*`, `bom-templates/*`, variant groups | `bom` | · | · | ✓ | ✓ | ✓ | ✓ | ✓ |
| `work-orders/*` | `work_orders` | · | · | ✓ | ✓ | ✓ | · | ✓ |
| material-requisitions | `material_requisitions` | · | · | ✓ | ✓ | ✓ | · | ✓ |
| mrp | `mrp` | · | · | ✓ | ✓ | ✓ | · | ✓ |
| subcontractor WO/invoices | `subcontracting` | · | · | ✓ | ✓ | ✓ | · | ✓ |
| `projects/*` | `projects` | · | ✓ | · | ✓ | · | ✓ | ✓ |

> **NEX note:** BOM ON (attach variant ke proposal), WO/MRP OFF — bukan pabrik full, butuh struktur material.

### 5.3 Vahana endpoints (`feature:electrical_panel`)

| Endpoint group | general | enterprise | mfg | vahana | nex | full |
|----------------|:-------:|:----------:|:---:|:------:|:---:|:----:|
| `component-standards/*` + mappings | · | · | · | ✓ | · | ✓ |
| `component-search`, `available-brands` | · | · | · | ✓ | · | ✓ |
| `boms/{id}/swap-brand*`, brand-comparison, variants | · | · | · | ✓ | · | ✓ |
| `boms/{id}/cost-optimization*` | · | · | · | ✓ | · | ✓ |
| `boms/{id}/items/{item}/swap` | · | · | · | ✓ | · | ✓ |
| `auto-mapping/*` | · | · | · | ✓ | · | ✓ |
| `component-mappings/*` import/export | · | · | · | ✓ | · | ✓ |
| `spec-rule-sets/*` | · | · | · | ✓ | · | ✓ |
| BOM/template resource extras via AddonExtensions | omit | omit | omit | merge | omit | merge |

### 5.4 NEX endpoints (`feature:solar_proposals`)

| Endpoint group | general | enterprise | mfg | vahana | nex | full |
|----------------|:-------:|:----------:|:---:|:------:|:---:|:----:|
| Public `public/solar-proposals/{token}` | · | · | · | · | ✓ | ✓ |
| Public `public/solar-calculator/*` | · | · | · | · | ✓ | ✓ |
| `solar-proposals` CRUD + lifecycle | · | · | · | · | ✓ | ✓ |
| attach-variants / select-bom / convert-to-quotation | · | · | · | · | ✓* | ✓* |
| pdf / excel / statistics | · | · | · | · | ✓ | ✓ |
| `solar-data/*` | · | · | · | · | ✓ | ✓ |
| `pln-tariffs/*` | · | · | · | · | ✓ | ✓ |

\* Butuh pack **`bom`** (preset `solar`/`nex` sudah `bom=true`).

---

## 6. Implementation status (snapshot)

| Item | Status |
|------|--------|
| Preset keys + env overrides | done (`config/features.php`) |
| Add-on config boundaries | done (`config/addons.php`) |
| Add-on routes + providers | done |
| Core manufacturing free of panel FKs (meta tables) | done (Fase A) |
| Core BOM HTTP zero-mention via `AddonExtensions` | done (commit residual polish) |
| Isolation tests (soft-skip, prohibit, package boundaries) | done |
| Smoke `FEATURE_PRESET=general` | done (core 200, industry/mfg 404) |
| FE soft-gate industry UI on core pages | partial (gates exist; full extract to addon slots open) |
| API OpenAPI / FE types after AddonExtensions | open / verify |
| Multi-preset smoke (enterprise, vahana, nex) | open |
| Final product docs (README pitch) | open — this file is working artifact only |

---

## 7. Four sentences (copy-paste)

1. **Core** selalu ada: master data, sales, purchase, inventory, accounting.  
2. **Pack** = apps generik (BOM, WO, MRP, Projects) — mirip Odoo apps, **bukan** vertical.  
3. **Vahana** = add-on industri panel: library komponen, rule set, brand swap, cost opt (butuh manufacturing/BOM).  
4. **NEX** = add-on industri solar: proposal + kalkulator + data PLN/irradiance (butuh projects + BOM light).

---

## 8. Next (when isolation arc continues)

1. Verify OpenAPI + FE types after AddonExtensions resource shape.  
2. Smoke `enterprise`, `vahana`, `nex` (and optional `full`).  
3. Optional: extract FE industry UI fully under `pages/addons/*` (zero-mention in core Vue pages).  
4. Promote this map (or a trimmed version) to permanent docs when product packaging is 100% frozen.
