# Enter365 - Simple Accounting System API
## Complete System Workflow Diagrams

**Target Customers:** Indonesian SMEs - Electrical Panel Makers & Solar EPC Contractors
**Standard:** SAK EMKM (Indonesian SME Accounting Standard)
**Framework:** Laravel 12 + PostgreSQL

---

## Implementation Phases Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        IMPLEMENTATION ROADMAP                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  Phase 1-4: Core Accounting Foundation                         [COMPLETE]  │
│  ├── Chart of Accounts (SAK EMKM)                                          │
│  ├── Journal Entries & Double-Entry Bookkeeping                            │
│  ├── Fiscal Periods                                                         │
│  ├── Contacts (Customers/Vendors/Subcontractors)                           │
│  ├── Invoices & Bills                                                       │
│  └── Payments & Bank Reconciliation                                         │
│                                                                             │
│  Phase 5A: Quotations                                          [COMPLETE]  │
│  Phase 5B: Purchase Orders                                     [COMPLETE]  │
│  Phase 5C: Down Payments                                       [COMPLETE]  │
│  Phase 5D: Delivery Orders                                     [COMPLETE]  │
│  Phase 5E: Sales & Purchase Returns                            [COMPLETE]  │
│  Phase 5F: Projects & Bill of Materials (BOM)                  [COMPLETE]  │
│                                                                             │
│  Phase 6A: Work Orders & Material Requisitions                 [COMPLETE]  │
│  Phase 6B: MRP & Subcontractor Management                      [COMPLETE]  │
│                                                                             │
│  Phase 7: Financial Reports                                    [COMPLETE]  │
│  ├── Cash Flow Statement & Daily Movement                                  │
│  ├── Comparative Balance Sheet & Income Statement                          │
│  ├── Project Profitability Reports                                         │
│  ├── Work Order Cost Analysis                                              │
│  └── Subcontractor Summary & Retention                                     │
│                                                                             │
│  Phase 8A: Stock Opname (Physical Inventory)                   [COMPLETE]  │
│  Phase 8B: Goods Receipt Note (GRN)                            [COMPLETE]  │
│                                                                             │
│  Phase 9: Authentication & User Management                     [COMPLETE]  │
│                                                                             │
│  Phase 10: Multi-Alternative BOM Comparison (Killer Feature)  [COMPLETE]  │
│  ├── BOM Variant Groups (group BOMs for same product)                      │
│  ├── Side-by-side cost comparison (min/max/difference)                     │
│  ├── Item-level detailed comparison                                         │
│  ├── Clone BOM as new variant                                               │
│  └── Multi-Option Quotations (customer selects Budget/Standard/Premium)   │
│                                                                             │
│  FUTURE PHASES:                                                             │
│  ├── PDF/Document Generation                                               │
│  ├── Email Notifications                                                    │
│  └── Solar Proposal Generator with ESG Metrics (Killer Feature)           │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## 1. Sales Cycle Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                           SALES CYCLE WORKFLOW                               │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   CUSTOMER   │
    │   REQUEST    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────┐
    │  QUOTATION   │─────▶│   REJECTED   │
    │   (draft)    │      └──────────────┘
    └──────┬───────┘
           │ approved
           ▼
    ┌──────────────┐
    │  QUOTATION   │
    │   (sent)     │
    └──────┬───────┘
           │ customer accepts
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   INVOICE    │◀─────│ Convert Quotation to Invoice      │
    │   (draft)    │      │ - Copy items & pricing            │
    └──────┬───────┘      │ - Link to quotation               │
           │              └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐                    ┌──────────────────────────────┐
    │   INVOICE    │                    │     DOWN PAYMENT             │
    │   (sent)     │◀───────────────────│ - Record DP received         │
    └──────┬───────┘                    │ - Link to invoice            │
           │                            │ - Reduce invoice balance     │
           │                            └──────────────────────────────┘
           ▼
    ┌──────────────┐
    │  DELIVERY    │
    │    ORDER     │
    │   (draft)    │
    └──────┬───────┘
           │ confirm
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  DELIVERY    │─────▶│  INVENTORY MOVEMENT               │
    │   ORDER      │      │  - type: out                      │
    │ (delivered)  │      │  - Reduce product stock           │
    └──────┬───────┘      │  - Create stock movement record   │
           │              └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   PAYMENT    │─────▶│  JOURNAL ENTRY (Auto-generated)  │
    │  (received)  │      │  Dr: Bank/Cash                    │
    │              │      │  Cr: Accounts Receivable          │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │   INVOICE    │
    │    (paid)    │
    └──────────────┘

    ┌──────────────────────────────────────────────────────────────────┐
    │                    SALES RETURN FLOW                              │
    ├──────────────────────────────────────────────────────────────────┤
    │                                                                   │
    │   Invoice (paid/partial) ──▶ Sales Return (draft)                │
    │                                    │                              │
    │                                    ▼                              │
    │                             Sales Return (approved)               │
    │                                    │                              │
    │                    ┌───────────────┼───────────────┐             │
    │                    ▼               ▼               ▼             │
    │              Credit Note    Inventory In     Refund Payment      │
    │                                                                   │
    └──────────────────────────────────────────────────────────────────┘
```

---

## 2. Purchasing Cycle Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PURCHASING CYCLE WORKFLOW                            │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   MATERIAL   │
    │   REQUIRED   │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐
    │  PURCHASE    │
    │   ORDER      │
    │   (draft)    │
    └──────┬───────┘
           │ submit
           ▼
    ┌──────────────┐      ┌──────────────┐
    │  PURCHASE    │─────▶│   REJECTED   │
    │   ORDER      │      │ (with reason)│
    │ (submitted)  │      └──────────────┘
    └──────┬───────┘
           │ approve
           ▼
    ┌──────────────┐
    │  PURCHASE    │
    │   ORDER      │
    │  (approved)  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │    GOODS     │◀─────│ Create from PO                    │
    │   RECEIPT    │      │ - Auto-populate items             │
    │    NOTE      │      │ - Track remaining qty to receive  │
    │   (draft)    │      └──────────────────────────────────┘
    └──────┬───────┘
           │ start receiving
           ▼
    ┌──────────────┐
    │    GOODS     │
    │   RECEIPT    │
    │    NOTE      │
    │ (receiving)  │
    └──────┬───────┘
           │ record received qty
           │ (may include rejected items)
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │    GOODS     │─────▶│  INVENTORY MOVEMENT               │
    │   RECEIPT    │      │  - type: in                       │
    │    NOTE      │      │  - Increase product stock         │
    │ (completed)  │      │  - Update PO received qty         │
    └──────┬───────┘      │  - Update PO status               │
           │              │    (partial/received)              │
           ▼              └──────────────────────────────────┘
    ┌──────────────┐
    │  PURCHASE    │
    │   ORDER      │
    │  (partial)   │ ←── Multiple GRNs allowed
    └──────┬───────┘
           │ all items received
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  PURCHASE    │─────▶│  Convert to Bill                  │
    │   ORDER      │      │  - Create bill from PO            │
    │  (received)  │      │  - Copy items & pricing           │
    └──────────────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │    BILL      │─────▶│  JOURNAL ENTRY (Auto-generated)  │
    │  (received)  │      │  Dr: Expense/Inventory            │
    │              │      │  Cr: Accounts Payable             │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   PAYMENT    │─────▶│  JOURNAL ENTRY (Auto-generated)  │
    │    (sent)    │      │  Dr: Accounts Payable             │
    │              │      │  Cr: Bank/Cash                    │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │    BILL      │
    │    (paid)    │
    └──────────────┘

    ┌──────────────────────────────────────────────────────────────────┐
    │                   PURCHASE RETURN FLOW                            │
    ├──────────────────────────────────────────────────────────────────┤
    │                                                                   │
    │   Bill (received) ──▶ Purchase Return (draft)                    │
    │                              │                                    │
    │                              ▼                                    │
    │                       Purchase Return (approved)                  │
    │                              │                                    │
    │               ┌──────────────┼──────────────┐                    │
    │               ▼              ▼              ▼                    │
    │         Debit Note    Inventory Out    Refund Expected           │
    │                                                                   │
    └──────────────────────────────────────────────────────────────────┘
```

---

## 3. Manufacturing / Work Order Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     MANUFACTURING WORKFLOW                                   │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   PROJECT    │
    │   CREATED    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │     BOM      │◀─────│ Bill of Materials                 │
    │   DEFINED    │      │ - Product (finished good)         │
    │              │      │ - BOM Items (components)          │
    │              │      │ - Quantities per unit             │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  WORK ORDER  │◀─────│ Created from BOM                  │
    │   (draft)    │      │ - Quantity to produce             │
    │              │      │ - Target completion date          │
    │              │      │ - Auto-calculate materials needed │
    └──────┬───────┘      └──────────────────────────────────┘
           │ release
           ▼
    ┌──────────────┐
    │  WORK ORDER  │
    │  (released)  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   MATERIAL   │◀─────│ Material Requisition              │
    │ REQUISITION  │      │ - Request materials from warehouse│
    │   (draft)    │      │ - Auto-reserve stock              │
    └──────┬───────┘      └──────────────────────────────────┘
           │ approve
           ▼
    ┌──────────────┐
    │   MATERIAL   │
    │ REQUISITION  │
    │  (approved)  │
    └──────┬───────┘
           │ issue materials
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   MATERIAL   │─────▶│  INVENTORY MOVEMENT               │
    │ REQUISITION  │      │  - type: out                      │
    │   (issued)   │      │  - Move to WIP warehouse          │
    └──────┬───────┘      │  - Reduce reserved qty            │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │   MATERIAL   │      ┌──────────────────────────────────┐
    │ CONSUMPTION  │─────▶│  Record actual usage              │
    │              │      │  - May differ from requisition    │
    └──────┬───────┘      │  - Track waste/scrap              │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │  WORK ORDER  │
    │(in_progress) │
    └──────┬───────┘
           │ complete production
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  WORK ORDER  │─────▶│  INVENTORY MOVEMENT               │
    │ (completed)  │      │  - type: in                       │
    │              │      │  - Finished goods to warehouse    │
    └──────┬───────┘      │  - Update product stock           │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │   PROJECT    │
    │    COSTS     │
    │   RECORDED   │
    └──────────────┘
```

---

## 4. MRP (Material Requirements Planning) Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                          MRP WORKFLOW                                        │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   MRP RUN    │
    │   STARTED    │
    │   (draft)    │
    └──────┬───────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    DEMAND GATHERING                              │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
    │  │ Work Orders │  │  Forecasts  │  │  Min Stock  │              │
    │  │  (planned)  │  │             │  │   Levels    │              │
    │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
    │         └────────────────┼────────────────┘                      │
    │                          ▼                                       │
    │                  ┌──────────────┐                                │
    │                  │  MRP DEMAND  │                                │
    │                  │   RECORDS    │                                │
    │                  └──────────────┘                                │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    SUPPLY CHECKING                               │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
    │  │   Current   │  │  Open POs   │  │  Lead Time  │              │
    │  │    Stock    │  │  (on order) │  │   Config    │              │
    │  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘              │
    │         └────────────────┼────────────────┘                      │
    │                          ▼                                       │
    │                  ┌──────────────┐                                │
    │                  │   SHORTAGE   │                                │
    │                  │  DETECTION   │                                │
    │                  └──────────────┘                                │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │     MRP      │      │ Suggestion Types:                 │
    │ SUGGESTIONS  │      │ - purchase: Create PO             │
    │              │      │ - manufacture: Create Work Order  │
    │              │      │ - transfer: Move between WH       │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │   MRP RUN    │
    │ (completed)  │
    └──────┬───────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                 SUGGESTION ACTIONS                               │
    │                                                                  │
    │  ┌─────────────────┐  ┌─────────────────┐                       │
    │  │ Accept & Create │  │  Reject/Ignore  │                       │
    │  │   Documents     │  │                 │                       │
    │  └────────┬────────┘  └─────────────────┘                       │
    │           │                                                      │
    │           ▼                                                      │
    │  ┌────────────────────────────────────────────┐                 │
    │  │ Auto-create POs / Work Orders / Transfers │                 │
    │  └────────────────────────────────────────────┘                 │
    └─────────────────────────────────────────────────────────────────┘
```

---

## 5. Subcontractor Management Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    SUBCONTRACTOR WORKFLOW                                    │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐      ┌──────────────────────────────────┐
    │   PROJECT    │      │ Contact Type: Subcontractor       │
    │   CREATED    │      │ - retention_rate (e.g., 5%)       │
    │              │      │ - certification                   │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │ SUBCONTRACTOR│
    │  WORK ORDER  │
    │   (draft)    │
    └──────┬───────┘
           │ assign
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │ SUBCONTRACTOR│      │ Work Order Details:               │
    │  WORK ORDER  │      │ - Scope of work                   │
    │  (assigned)  │      │ - Agreed amount                   │
    │              │      │ - Start/end dates                 │
    └──────┬───────┘      │ - Retention percentage            │
           │              └──────────────────────────────────┘
           │ start
           ▼
    ┌──────────────┐
    │ SUBCONTRACTOR│
    │  WORK ORDER  │
    │(in_progress) │
    └──────┬───────┘
           │ complete
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │ SUBCONTRACTOR│─────▶│ Calculate:                        │
    │  WORK ORDER  │      │ - Actual cost                     │
    │ (completed)  │      │ - Retention amount                │
    └──────┬───────┘      │ - Net payable                     │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │ SUBCONTRACTOR│
    │   INVOICE    │
    │   (draft)    │
    └──────┬───────┘
           │ verify
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │ SUBCONTRACTOR│      │ Invoice Amount Breakdown:         │
    │   INVOICE    │      │ - Gross: 100,000,000              │
    │  (verified)  │      │ - Retention (5%): -5,000,000      │
    │              │      │ - Net Payable: 95,000,000         │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   PAYMENT    │─────▶│ Pay Net Amount                    │
    │  (partial)   │      │ Retention held until warranty     │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           │ after warranty period
           ▼
    ┌──────────────┐
    │  RETENTION   │
    │   RELEASE    │
    │   PAYMENT    │
    └──────────────┘
```

---

## 6. Stock Opname (Physical Inventory) Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    STOCK OPNAME WORKFLOW                                     │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │ STOCK OPNAME │
    │   CREATED    │
    │   (draft)    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   GENERATE   │─────▶│ Auto-populate items:              │
    │    ITEMS     │      │ - All products in warehouse       │
    │              │      │ - Current system quantity         │
    └──────┬───────┘      │ - Or specific products            │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │ STOCK OPNAME │
    │   (draft)    │
    │  with items  │
    └──────┬───────┘
           │ start counting
           ▼
    ┌──────────────┐
    │ STOCK OPNAME │
    │  (counting)  │
    └──────┬───────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    PHYSICAL COUNTING                             │
    │                                                                  │
    │  For each item:                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │ Product: MCB 16A Schneider                                  ││
    │  │ System Qty: 100 pcs                                         ││
    │  │ Counted Qty: 95 pcs  ◀── Enter actual count                ││
    │  │ Variance: -5 pcs (shortage)                                 ││
    │  │ Notes: "5 units damaged, written off"                       ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │ all items counted
           ▼
    ┌──────────────┐
    │ STOCK OPNAME │
    │  (pending    │
    │   review)    │
    └──────┬───────┘
           │
       ┌───┴───┐
       ▼       ▼
┌──────────┐ ┌──────────┐
│  REJECT  │ │  APPROVE │
│(back to  │ │          │
│ counting)│ │          │
└──────────┘ └────┬─────┘
                  │
                  ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │ STOCK OPNAME │─────▶│  INVENTORY ADJUSTMENTS            │
    │ (completed)  │      │                                    │
    │              │      │  For each variance:               │
    │              │      │  - Create adjustment movement     │
    │              │      │  - Update product stock           │
    └──────────────┘      │  - Reference: Stock Opname ID     │
                          └──────────────────────────────────┘

    ┌─────────────────────────────────────────────────────────────────┐
    │                    VARIANCE REPORT                               │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │ Stock Opname: SO-202412-0001                                ││
    │  │ Warehouse: Main Warehouse                                   ││
    │  │ Date: 2024-12-27                                            ││
    │  ├─────────────────────────────────────────────────────────────┤│
    │  │ Product         │ System │ Actual │ Variance │ Value (IDR) ││
    │  │─────────────────┼────────┼────────┼──────────┼─────────────││
    │  │ MCB 16A         │   100  │    95  │    -5    │  -500,000   ││
    │  │ Cable 4mm²      │   500  │   510  │   +10    │  +100,000   ││
    │  │ Contactor 25A   │    50  │    48  │    -2    │  -400,000   ││
    │  ├─────────────────────────────────────────────────────────────┤│
    │  │ Total Variance Value                        │  -800,000   ││
    │  └─────────────────────────────────────────────────────────────┘│
    └─────────────────────────────────────────────────────────────────┘
```

---

## 7. Project Costing & Profitability Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    PROJECT COSTING WORKFLOW                                  │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   PROJECT    │
    │   CREATED    │
    │   (draft)    │
    └──────┬───────┘
           │
           ▼
    ┌────────────────────────────────────────────────────────────────┐
    │                    COST TRACKING                                │
    │                                                                 │
    │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐          │
    │  │  Material   │   │   Labor     │   │Subcontractor│          │
    │  │   Costs     │   │   Costs     │   │   Costs     │          │
    │  │             │   │             │   │             │          │
    │  │ From:       │   │ From:       │   │ From:       │          │
    │  │ - Work Order│   │ - Timesheets│   │ - Sub WO    │          │
    │  │ - Mat. Req  │   │ - Manual    │   │ - Sub Inv   │          │
    │  └──────┬──────┘   └──────┬──────┘   └──────┬──────┘          │
    │         │                 │                 │                  │
    │         └─────────────────┼─────────────────┘                  │
    │                           ▼                                    │
    │                   ┌──────────────┐                             │
    │                   │PROJECT COSTS │                             │
    │                   │   TABLE      │                             │
    │                   └──────────────┘                             │
    │                                                                 │
    │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐          │
    │  │  Equipment  │   │  Overhead   │   │   Other     │          │
    │  │   Costs     │   │   Costs     │   │   Costs     │          │
    │  └──────┬──────┘   └──────┬──────┘   └──────┬──────┘          │
    │         └─────────────────┼─────────────────┘                  │
    │                           ▼                                    │
    │                   ┌──────────────┐                             │
    │                   │PROJECT COSTS │                             │
    │                   │   TABLE      │                             │
    │                   └──────────────┘                             │
    └────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌────────────────────────────────────────────────────────────────┐
    │                    REVENUE TRACKING                             │
    │                                                                 │
    │  ┌─────────────┐   ┌─────────────┐   ┌─────────────┐          │
    │  │  Progress   │   │   Invoice   │   │   Other     │          │
    │  │  Billing    │   │  Payments   │   │  Revenue    │          │
    │  └──────┬──────┘   └──────┬──────┘   └──────┬──────┘          │
    │         └─────────────────┼─────────────────┘                  │
    │                           ▼                                    │
    │                   ┌──────────────┐                             │
    │                   │  PROJECT     │                             │
    │                   │  REVENUES    │                             │
    │                   └──────────────┘                             │
    └────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                 PROFITABILITY REPORT                             │
    │                                                                  │
    │  Project: Solar Panel Installation - PT ABC                     │
    │  Status: In Progress (60% complete)                             │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │                                                              ││
    │  │  Contract Amount:             500,000,000                   ││
    │  │                                                              ││
    │  │  REVENUE                                                     ││
    │  │  ├── Progress Billing:        300,000,000                   ││
    │  │  └── Total Revenue:           300,000,000                   ││
    │  │                                                              ││
    │  │  COSTS                                                       ││
    │  │  ├── Material:                120,000,000                   ││
    │  │  ├── Labor:                    80,000,000                   ││
    │  │  ├── Subcontractor:            50,000,000                   ││
    │  │  ├── Equipment:                20,000,000                   ││
    │  │  ├── Overhead:                 10,000,000                   ││
    │  │  └── Total Costs:             280,000,000                   ││
    │  │                                                              ││
    │  │  GROSS PROFIT:                 20,000,000                   ││
    │  │  PROFIT MARGIN:                      6.67%                  ││
    │  │                                                              ││
    │  │  Budget Status:            ✓ Under Budget                   ││
    │  │  Budget Variance:             +20,000,000                   ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    └─────────────────────────────────────────────────────────────────┘
```

---

## 8. Financial Reporting Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    FINANCIAL REPORTING STRUCTURE                             │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌─────────────────────────────────────────────────────────────────┐
    │                    DATA SOURCES                                  │
    │                                                                  │
    │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐           │
    │  │ Journal  │ │ Invoices │ │  Bills   │ │ Payments │           │
    │  │ Entries  │ │          │ │          │ │          │           │
    │  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘           │
    │       └────────────┴────────────┴────────────┘                  │
    │                           │                                      │
    └───────────────────────────┼──────────────────────────────────────┘
                                │
                                ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    CORE FINANCIAL REPORTS                        │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │                                                              ││
    │  │  ┌────────────────┐    ┌────────────────┐                   ││
    │  │  │ Trial Balance  │    │ Balance Sheet  │                   ││
    │  │  │                │    │ (Comparative)  │                   ││
    │  │  │ Per Account    │    │                │                   ││
    │  │  │ Debit/Credit   │    │ Assets         │                   ││
    │  │  │ Balance        │    │ Liabilities    │                   ││
    │  │  │                │    │ Equity         │                   ││
    │  │  └────────────────┘    └────────────────┘                   ││
    │  │                                                              ││
    │  │  ┌────────────────┐    ┌────────────────┐                   ││
    │  │  │Income Statement│    │ Cash Flow      │                   ││
    │  │  │ (Comparative)  │    │ Statement      │                   ││
    │  │  │                │    │                │                   ││
    │  │  │ Revenue        │    │ Operating      │                   ││
    │  │  │ COGS           │    │ Investing      │                   ││
    │  │  │ Expenses       │    │ Financing      │                   ││
    │  │  │ Net Income     │    │                │                   ││
    │  │  └────────────────┘    └────────────────┘                   ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    AR/AP REPORTS                                 │
    │                                                                  │
    │  ┌──────────────────┐    ┌──────────────────┐                   │
    │  │  AR Aging Report │    │  AP Aging Report │                   │
    │  │                  │    │                  │                   │
    │  │  Buckets:        │    │  Buckets:        │                   │
    │  │  - Current       │    │  - Current       │                   │
    │  │  - 1-30 days     │    │  - 1-30 days     │                   │
    │  │  - 31-60 days    │    │  - 31-60 days    │                   │
    │  │  - 61-90 days    │    │  - 61-90 days    │                   │
    │  │  - Over 90 days  │    │  - Over 90 days  │                   │
    │  └──────────────────┘    └──────────────────┘                   │
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
                                │
                                ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    BUSINESS-SPECIFIC REPORTS                     │
    │                                                                  │
    │  ┌────────────────────────────────────────────────────────────┐ │
    │  │                                                             │ │
    │  │  PROJECT REPORTS                                            │ │
    │  │  ├── Project Profitability Summary                         │ │
    │  │  ├── Project Profitability Detail                          │ │
    │  │  └── Project Cost Analysis (by type)                       │ │
    │  │                                                             │ │
    │  │  WORK ORDER REPORTS                                         │ │
    │  │  ├── Work Order Cost Summary                               │ │
    │  │  ├── Work Order Cost Detail                                │ │
    │  │  └── Cost Variance Report (estimated vs actual)            │ │
    │  │                                                             │ │
    │  │  SUBCONTRACTOR REPORTS                                      │ │
    │  │  ├── Subcontractor Summary (all subs)                      │ │
    │  │  ├── Subcontractor Detail (per sub)                        │ │
    │  │  └── Retention Summary (held vs releasable)                │ │
    │  │                                                             │ │
    │  │  TAX REPORTS                                                │ │
    │  │  ├── PPN Keluaran (Output VAT)                             │ │
    │  │  ├── PPN Masukan (Input VAT)                               │ │
    │  │  └── PPN Neto (Net VAT)                                    │ │
    │  │                                                             │ │
    │  └────────────────────────────────────────────────────────────┘ │
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
```

---

## 9. User Authentication & Authorization

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    AUTHENTICATION WORKFLOW                                   │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │     USER     │
    │   REGISTER   │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │    LOGIN     │─────▶│  Sanctum Token Generated          │
    │              │      │  - Personal access token          │
    │              │      │  - Abilities assigned             │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   API CALL   │─────▶│  Bearer Token in Header           │
    │              │      │  Authorization: Bearer {token}    │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    ROLE-BASED ACCESS                             │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │                                                              ││
    │  │  ROLES                          PERMISSIONS                  ││
    │  │  ┌──────────┐                   ┌─────────────────────────┐ ││
    │  │  │  Admin   │ ─────────────────▶│ * (all permissions)     │ ││
    │  │  └──────────┘                   └─────────────────────────┘ ││
    │  │                                                              ││
    │  │  ┌──────────┐                   ┌─────────────────────────┐ ││
    │  │  │ Manager  │ ─────────────────▶│ invoices.*, bills.*     │ ││
    │  │  └──────────┘                   │ projects.*, reports.*   │ ││
    │  │                                 └─────────────────────────┘ ││
    │  │                                                              ││
    │  │  ┌──────────┐                   ┌─────────────────────────┐ ││
    │  │  │  Sales   │ ─────────────────▶│ quotations.*, invoices.*│ ││
    │  │  └──────────┘                   │ contacts.view           │ ││
    │  │                                 └─────────────────────────┘ ││
    │  │                                                              ││
    │  │  ┌──────────┐                   ┌─────────────────────────┐ ││
    │  │  │Warehouse │ ─────────────────▶│ inventory.*, stock.*    │ ││
    │  │  └──────────┘                   │ delivery-orders.*       │ ││
    │  │                                 └─────────────────────────┘ ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │    LOGOUT    │
    │ (revoke token)│
    └──────────────┘
```

---

## 10. Multi-Alternative BOM Comparison Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    BOM VARIANT COMPARISON WORKFLOW                           │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   PRODUCT    │
    │   DEFINED    │
    │  (e.g. PLTS  │
    │   50 kWp)    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   VARIANT    │◀─────│ Create Variant Group              │
    │    GROUP     │      │ - Name: "Material Options"        │
    │   CREATED    │      │ - Product: PLTS 50 kWp            │
    │   (draft)    │      │ - Comparison notes                 │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    CREATE BOM VARIANTS                           │
    │                                                                  │
    │  Option 1: Create new BOMs directly in group                    │
    │  Option 2: Add existing BOMs to group                           │
    │  Option 3: Clone existing BOM as new variant                    │
    │                                                                  │
    │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐              │
    │  │   BUDGET    │  │  STANDARD   │  │   PREMIUM   │              │
    │  │   VARIANT   │  │   VARIANT   │  │   VARIANT   │              │
    │  │             │  │             │  │             │              │
    │  │ Growatt +   │  │ Huawei +    │  │ SMA +       │              │
    │  │ NUSA mount  │  │ NUSA mount  │  │ LONGi mount │              │
    │  │             │  │             │  │             │              │
    │  │ Rp 575M     │  │ Rp 720M     │  │ Rp 920M     │              │
    │  └─────────────┘  └─────────────┘  └─────────────┘              │
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │   VARIANT    │
    │    GROUP     │
    │   (active)   │
    └──────┬───────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                    COMPARISON VIEWS                              │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │              SIDE-BY-SIDE COMPARISON                         ││
    │  ├─────────────────────────────────────────────────────────────┤│
    │  │                                                              ││
    │  │  Summary:                                                    ││
    │  │  ├── Total Variants: 3                                      ││
    │  │  ├── Cheapest: Budget (Rp 575,000,000)                      ││
    │  │  ├── Most Expensive: Premium (Rp 920,000,000)               ││
    │  │  └── Price Difference: Rp 345,000,000 (60%)                 ││
    │  │                                                              ││
    │  │  ┌──────────────┬──────────────┬──────────────┐             ││
    │  │  │    Budget    │   Standard   │   Premium    │             ││
    │  │  ├──────────────┼──────────────┼──────────────┤             ││
    │  │  │ Material:    │ Material:    │ Material:    │             ││
    │  │  │  500,000,000 │  620,000,000 │  800,000,000 │             ││
    │  │  │              │              │              │             ││
    │  │  │ Labor:       │ Labor:       │ Labor:       │             ││
    │  │  │   50,000,000 │   65,000,000 │   80,000,000 │             ││
    │  │  │              │              │              │             ││
    │  │  │ Overhead:    │ Overhead:    │ Overhead:    │             ││
    │  │  │   25,000,000 │   35,000,000 │   40,000,000 │             ││
    │  │  ├──────────────┼──────────────┼──────────────┤             ││
    │  │  │ TOTAL:       │ TOTAL:       │ TOTAL:       │             ││
    │  │  │  575,000,000 │  720,000,000 │  920,000,000 │             ││
    │  │  └──────────────┴──────────────┴──────────────┘             ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │              ITEM-LEVEL COMPARISON                           ││
    │  ├─────────────────────────────────────────────────────────────┤│
    │  │                                                              ││
    │  │  Inverter Comparison:                                        ││
    │  │  ├── Budget: Growatt 50kW (Rp 50,000,000)                   ││
    │  │  ├── Standard: Huawei SUN2000 (Rp 85,000,000)               ││
    │  │  └── Premium: SMA Sunny Tripower (Rp 120,000,000)           ││
    │  │                                                              ││
    │  │  Panel Comparison:                                           ││
    │  │  ├── Budget: Canadian Solar 545W (Rp 1,500,000/pc)          ││
    │  │  ├── Standard: JA Solar 550W (Rp 1,800,000/pc)              ││
    │  │  └── Premium: LONGi Hi-MO 5 (Rp 2,200,000/pc)               ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │   SELECT     │
    │   VARIANT    │
    │ FOR PROJECT  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  WORK ORDER  │◀─────│ Create Work Order from           │
    │   CREATED    │      │ selected BOM variant             │
    └──────────────┘      └──────────────────────────────────┘
```

---

## 11. Multi-Option Quotation Workflow

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                    MULTI-OPTION QUOTATION WORKFLOW                           │
└─────────────────────────────────────────────────────────────────────────────┘

    ┌──────────────┐
    │   CUSTOMER   │
    │   REQUEST    │
    │ (wants price │
    │  options)    │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  QUOTATION   │◀─────│ Create Multi-Option Quotation    │
    │   (draft)    │      │ - quotation_type: multi_option   │
    │              │      │ - Link to BOM Variant Group      │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                 CONFIGURE VARIANT OPTIONS                        │
    │                                                                  │
    │  For each BOM variant, set customer-facing details:             │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │                                                              ││
    │  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐         ││
    │  │  │   BUDGET    │  │  STANDARD   │  │   PREMIUM   │         ││
    │  │  │             │  │ ★RECOMMENDED│  │             │         ││
    │  │  │ display_name│  │ display_name│  │ display_name│         ││
    │  │  │ tagline     │  │ tagline     │  │ tagline     │         ││
    │  │  │ selling_prc │  │ selling_prc │  │ selling_prc │         ││
    │  │  │ features[]  │  │ features[]  │  │ features[]  │         ││
    │  │  │ specs{}     │  │ specs{}     │  │ specs{}     │         ││
    │  │  │ warranty    │  │ warranty    │  │ warranty    │         ││
    │  │  │             │  │             │  │             │         ││
    │  │  │ Rp 350M     │  │ Rp 500M     │  │ Rp 750M     │         ││
    │  │  │ (15% mrgn)  │  │ (20% mrgn)  │  │ (25% mrgn)  │         ││
    │  │  └─────────────┘  └─────────────┘  └─────────────┘         ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    │  Note: Selling price can differ from BOM cost (custom margins)  │
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │
           ▼
    ┌──────────────┐
    │  QUOTATION   │
    │ (submitted)  │
    └──────┬───────┘
           │ customer reviews options
           ▼
    ┌─────────────────────────────────────────────────────────────────┐
    │                 CUSTOMER-FACING COMPARISON                       │
    │                                                                  │
    │  GET /quotations/{id}/variant-comparison                        │
    │                                                                  │
    │  ┌─────────────────────────────────────────────────────────────┐│
    │  │                                                              ││
    │  │  PLTS Rooftop 50 kWp - Pilihan Konfigurasi                  ││
    │  │  ═══════════════════════════════════════════                ││
    │  │                                                              ││
    │  │  ┌──────────────┬──────────────┬──────────────┐             ││
    │  │  │ Paket Hemat  │Paket Standar │Paket Premium │             ││
    │  │  │              │   ★ Best     │              │             ││
    │  │  ├──────────────┼──────────────┼──────────────┤             ││
    │  │  │ Rp 350,000,000│Rp 500,000,000│Rp 750,000,000│            ││
    │  │  ├──────────────┼──────────────┼──────────────┤             ││
    │  │  │ ✓ Garansi 5th│ ✓ Garansi 10th│ ✓ Garansi 15th│          ││
    │  │  │ ✓ Instalasi  │ ✓ Instalasi  │ ✓ Instalasi  │             ││
    │  │  │              │ ✓ Monitoring │ ✓ Monitoring │             ││
    │  │  │              │ ✓ Training   │ ✓ Training   │             ││
    │  │  │              │              │ ✓ 24/7 Support│            ││
    │  │  │              │              │ ✓ Maintenance │            ││
    │  │  ├──────────────┼──────────────┼──────────────┤             ││
    │  │  │  [Select]    │  [Select]    │  [Select]    │             ││
    │  │  └──────────────┴──────────────┴──────────────┘             ││
    │  │                                                              ││
    │  │  Price Range: Rp 350M - Rp 750M                             ││
    │  │                                                              ││
    │  └─────────────────────────────────────────────────────────────┘│
    │                                                                  │
    └─────────────────────────────────────────────────────────────────┘
           │
           │ customer selects option
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   SELECT     │◀─────│ POST /quotations/{id}/select-variant │
    │   VARIANT    │      │ - variant_option_id              │
    │              │      │ - Updates selected_variant_id    │
    └──────┬───────┘      │ - Updates quotation total        │
           │              └──────────────────────────────────┘
           ▼
    ┌──────────────┐
    │  QUOTATION   │
    │  (approved)  │
    └──────┬───────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │   INVOICE    │◀─────│ Convert with selected variant    │
    │              │      │ price as total                   │
    └──────┬───────┘      └──────────────────────────────────┘
           │
           ▼
    ┌──────────────┐      ┌──────────────────────────────────┐
    │  WORK ORDER  │◀─────│ Create from selected BOM variant │
    │   CREATED    │      │ (Budget, Standard, or Premium)   │
    └──────────────┘      └──────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────────┐
│                    ALTERNATIVE: CREATE QUOTATION FROM BOM                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  When sales wants to pick ONE variant (not give customer options):          │
│                                                                              │
│  POST /api/v1/quotations/from-bom                                           │
│  {                                                                           │
│      "bom_id": 5,              // Pick specific BOM (e.g., Standard)        │
│      "contact_id": 123,                                                      │
│      "margin_percent": 25,     // Apply margin on BOM cost                  │
│      "expand_items": false     // false = single line, true = detailed      │
│  }                                                                           │
│                                                                              │
│  Result: Single quotation with selected BOM pricing                         │
│          - source_bom_id tracks which BOM was used                          │
│          - quotation_type remains "single"                                  │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## API Endpoint Summary

### Phase 1-4: Core Accounting
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/accounts` | Chart of Accounts |
| GET/POST | `/api/v1/journal-entries` | Journal Entries |
| GET/POST | `/api/v1/fiscal-periods` | Fiscal Periods |
| GET/POST | `/api/v1/contacts` | Customers/Vendors |
| GET/POST | `/api/v1/invoices` | Sales Invoices |
| GET/POST | `/api/v1/bills` | Purchase Bills |
| GET/POST | `/api/v1/payments` | Payments |

### Phase 5: Sales & Purchasing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/quotations` | Quotations |
| GET/POST | `/api/v1/purchase-orders` | Purchase Orders |
| GET/POST | `/api/v1/down-payments` | Down Payments |
| GET/POST | `/api/v1/delivery-orders` | Delivery Orders |
| GET/POST | `/api/v1/sales-returns` | Sales Returns |
| GET/POST | `/api/v1/purchase-returns` | Purchase Returns |

### Phase 6: Manufacturing
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/boms` | Bill of Materials |
| GET/POST | `/api/v1/work-orders` | Work Orders |
| GET/POST | `/api/v1/material-requisitions` | Material Requisitions |
| POST | `/api/v1/mrp/run` | Run MRP |
| GET/POST | `/api/v1/subcontractor-work-orders` | Subcontractor WOs |

### Phase 7: Reports
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/reports/balance-sheet` | Balance Sheet |
| GET | `/api/v1/reports/income-statement` | Income Statement |
| GET | `/api/v1/reports/cash-flow` | Cash Flow Statement |
| GET | `/api/v1/reports/project-profitability` | Project P&L |
| GET | `/api/v1/reports/work-order-costs` | WO Cost Analysis |
| GET | `/api/v1/reports/subcontractor-summary` | Subcontractor Report |

### Phase 8: Inventory Operations
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/stock-opnames` | Stock Opname |
| POST | `/api/v1/stock-opnames/{id}/approve` | Approve & Adjust |
| GET/POST | `/api/v1/goods-receipt-notes` | Goods Receipt Notes |
| POST | `/api/v1/goods-receipt-notes/{id}/complete` | Complete GRN |

### Phase 9: Authentication
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/auth/register` | Register User |
| POST | `/api/v1/auth/login` | Login |
| POST | `/api/v1/auth/logout` | Logout |
| GET | `/api/v1/users/me` | Current User |
| GET/POST | `/api/v1/roles` | Role Management |
| GET/POST | `/api/v1/permissions` | Permission Management |

### Phase 10: Multi-Alternative BOM Comparison
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET/POST | `/api/v1/bom-variant-groups` | BOM Variant Groups |
| GET/PUT/DELETE | `/api/v1/bom-variant-groups/{id}` | Variant Group CRUD |
| GET | `/api/v1/bom-variant-groups/{id}/compare` | Side-by-side Comparison |
| GET | `/api/v1/bom-variant-groups/{id}/compare-detailed` | Item-level Comparison |
| POST | `/api/v1/bom-variant-groups/{id}/boms` | Add BOM to Group |
| DELETE | `/api/v1/bom-variant-groups/{id}/boms/{bom}` | Remove BOM from Group |
| POST | `/api/v1/bom-variant-groups/{id}/boms/{bom}/set-primary` | Set Primary Variant |
| POST | `/api/v1/bom-variant-groups/{id}/reorder` | Reorder Variants |
| POST | `/api/v1/bom-variant-groups/{id}/create-variant` | Clone BOM as Variant |

### Phase 10B: Multi-Option Quotations
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/v1/quotations/from-bom` | Create quotation from BOM (single variant) |
| GET | `/api/v1/quotations/{id}/variant-options` | Get variant options |
| PUT | `/api/v1/quotations/{id}/variant-options` | Sync variant options |
| POST | `/api/v1/quotations/{id}/select-variant` | Customer selects variant |
| GET | `/api/v1/quotations/{id}/variant-comparison` | Customer-facing comparison |

---

## Test Coverage Summary

| Phase | Description | Tests |
|-------|-------------|-------|
| 1-4 | Core Accounting | 342 |
| 5A | Quotations (incl. Variants, FromBom) | 101 |
| 5B | Purchase Orders | 39 |
| 5C | Down Payments | 37 |
| 5D | Delivery Orders | 30 |
| 5E | Returns | 54 |
| 5F | Projects & BOM | 75 |
| 6A | Work Orders | 64 |
| 6B | MRP & Subcontractor | 65 |
| 7 | Financial Reports | 56 |
| 8A | Stock Opname | 23 |
| 8B | Goods Receipt Note | 26 |
| 9 | Authentication | ~20 |
| 10 | BOM Variant Comparison | 22 |
| **Total** | | **~950+** |

---

*Last Updated: December 27, 2024*
