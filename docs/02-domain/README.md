---
section: domain
title: "Business Domain Overview"
order: 1
---

# Business Domain Overview

> **Understanding Enter365's business domains and workflows**
>
> Enter365 serves Indonesian SMEs in electrical panel manufacturing and solar EPC industries.

---

## AI Agent Quick Reference

**Use this section when:**
- Understanding business processes
- Implementing new features
- Debugging workflow issues
- Learning Indonesian business context

**Quick Links:**
- [Sales Cycle](./sales-cycle.md) - Quotation → Invoice → Payment
- [Purchasing Cycle](./purchasing-cycle.md) - PO → GRN → Bill → Payment
- [Manufacturing](./manufacturing.md) - BOM → Work Order → MRP
- [Solar EPC](./solar-epc.md) - Solar proposal system (killer feature)
- [Indonesian Context](./indonesian-context.md) - SAK EMKM, PPN, NPWP

---

## Target Market

### Primary: Electrical Panel Manufacturers

Companies that build electrical distribution panels (MDP, SDP, control panels) for:
- Commercial buildings
- Industrial facilities
- Infrastructure projects

**Pain Points Solved:**
- Multi-brand quotations (ABB/Schneider/Siemens)
- BOM management with variants
- Material Requirements Planning (MRP)
- Project cost tracking

### Secondary: Solar EPC Contractors

Companies that design and install solar PV systems:
- Rooftop solar for commercial/industrial
- Ground-mounted systems
- Hybrid systems with battery

**Pain Points Solved:**
- Automated ROI calculations
- Professional proposal generation
- PLN tariff integration
- Customer self-service portal

---

## Core Business Flows

### 1. Sales Flow (Quotation to Cash)

```
Customer Inquiry
      │
      ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Quotation  │────▶│   Invoice   │────▶│   Payment   │
│   (Draft)   │     │  (Posted)   │     │  (Received) │
└─────────────┘     └─────────────┘     └─────────────┘
      │                    │                    │
      │                    ▼                    ▼
      │             Delivery Order      Journal Entry
      │                    │            (AR → Cash)
      │                    ▼
      │             Stock Movement
      │             (Warehouse → Customer)
      │
      ▼
 Multi-Option
 (Budget/Standard/Premium)
```

**See:** [Sales Cycle](./sales-cycle.md)

### 2. Purchasing Flow (Procure to Pay)

```
Material Need
      │
      ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│  Purchase   │────▶│     GRN     │────▶│    Bill     │
│   Order     │     │  (Received) │     │  (Posted)   │
└─────────────┘     └─────────────┘     └─────────────┘
      │                    │                    │
      │                    ▼                    ▼
      │             Stock Movement        Payment
      │             (Vendor → Warehouse)       │
      │                                        ▼
      │                               Journal Entry
      │                               (Expense → AP)
      │
      ▼
 MRP Suggestions
 (Auto-generate POs)
```

**See:** [Purchasing Cycle](./purchasing-cycle.md)

### 3. Manufacturing Flow

```
Customer Order
      │
      ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│     BOM     │────▶│ Work Order  │────▶│  Finished   │
│  (Recipe)   │     │ (Production)│     │   Goods     │
└─────────────┘     └─────────────┘     └─────────────┘
      │                    │                    │
      │                    ▼                    ▼
      ▼             Material Requisition   Stock In
 BOM Variants            │              (Finished Product)
 (Budget/Standard/       ▼
  Premium)          Stock Out
                   (Components)
```

**See:** [Manufacturing](./manufacturing.md)

### 4. Solar Proposal Flow

```
Site Assessment
      │
      ▼
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Solar     │────▶│  Customer   │────▶│  Quotation  │
│  Proposal   │     │  Accepts    │     │  Generated  │
└─────────────┘     └─────────────┘     └─────────────┘
      │                    │
      │                    ▼
      ▼             Project Created
 Auto-Calculate:          │
 - System Size            ▼
 - ROI/Payback      Work Orders
 - Monthly Savings  (Installation)
```

**See:** [Solar EPC](./solar-epc.md)

---

## Domain Model Summary

### Core Entities

| Entity | Indonesian | Purpose |
|--------|------------|---------|
| Contact | Kontak | Customers & vendors |
| Product | Produk | Items, services, components |
| Account | Akun | Chart of accounts |
| JournalEntry | Jurnal | Accounting transactions |

### Sales Entities

| Entity | Indonesian | Purpose |
|--------|------------|---------|
| Quotation | Penawaran | Price quotes |
| Invoice | Faktur | Sales invoices |
| Payment | Pembayaran | Payment receipts |
| DeliveryOrder | Surat Jalan | Shipping documents |
| DownPayment | Uang Muka | Advance payments |

### Purchasing Entities

| Entity | Indonesian | Purpose |
|--------|------------|---------|
| PurchaseOrder | PO | Purchase orders |
| GoodsReceiptNote | GRN | Receiving documents |
| Bill | Tagihan | Vendor bills |

### Manufacturing Entities

| Entity | Indonesian | Purpose |
|--------|------------|---------|
| Bom | BOM | Bill of materials |
| BomVariantGroup | Grup Varian | Multi-brand options |
| WorkOrder | Perintah Kerja | Production orders |
| MaterialRequisition | Permintaan Material | Material requests |

### Inventory Entities

| Entity | Indonesian | Purpose |
|--------|------------|---------|
| Warehouse | Gudang | Storage locations |
| ProductStock | Stok | Stock per warehouse |
| InventoryMovement | Mutasi | Stock transactions |

---

## Indonesian Business Context

### Regulatory Requirements

| Requirement | Description | Implementation |
|-------------|-------------|----------------|
| SAK EMKM | Accounting standard | Double-entry, accrual basis |
| PPN | VAT 11% | Automatic tax calculation |
| NPWP | Tax ID | Contact field validation |
| Fiscal Year | Jan-Dec | Period management |

### Common Business Practices

| Practice | Description | Feature |
|----------|-------------|---------|
| Uang Muka | Down payment (30-50%) | DownPayment tracking |
| Net 30 | 30-day payment terms | Configurable terms |
| Retensi | 5% retention for subcontractors | Retention tracking |
| Multi-brand | Compare ABB/Schneider/Siemens | BOM variants |

**See:** [Indonesian Context](./indonesian-context.md)

---

## Feature Flags

Modules can be enabled/disabled per deployment:

| Module | Flag | Default |
|--------|------|---------|
| Sales | `FEATURE_SALES` | true |
| Purchasing | `FEATURE_PURCHASING` | true |
| Inventory | `FEATURE_INVENTORY` | true |
| Manufacturing | `FEATURE_MANUFACTURING` | true |
| MRP | `FEATURE_MRP` | true |
| Projects | `FEATURE_PROJECTS` | true |
| Solar | `FEATURE_SOLAR` | true |

---

## Related Documentation

| Document | Description |
|----------|-------------|
| [Architecture](../01-architecture/README.md) | Technical architecture |
| [GLOSSARY](../GLOSSARY.md) | Indonesian terms |
| [ADR-0006](../08-adr/0006-sak-emkm-compliance.md) | SAK EMKM compliance |
| [ADR-0009](../08-adr/0009-bom-variant-groups.md) | BOM variants |
