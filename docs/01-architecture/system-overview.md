---
section: architecture
title: "System Overview"
order: 2
---

# System Overview

> **High-level architecture of Enter365**
>
> C4 Context-level view of the system, its users, and external dependencies.

---

## AI Agent Quick Reference

**Use this document when:**
- Understanding the big picture
- Explaining the system to stakeholders
- Identifying integration points
- Planning new features

---

## System Context

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              ENTER365 SYSTEM                                │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  ┌─────────────┐      ┌─────────────────────────────────────────────────┐  │
│  │   Vue.js    │◀────▶│              Laravel 12 API                     │  │
│  │   SPA       │      │                                                 │  │
│  │  Frontend   │      │  ┌─────────┐ ┌─────────┐ ┌─────────┐            │  │
│  └─────────────┘      │  │  Sales  │ │Purchase │ │ Manufac │            │  │
│                       │  │ Module  │ │ Module  │ │ Module  │            │  │
│                       │  └────┬────┘ └────┬────┘ └────┬────┘            │  │
│                       │       │           │           │                 │  │
│                       │  ┌────▼───────────▼───────────▼────┐            │  │
│                       │  │      Core Accounting Engine     │            │  │
│                       │  │   (Double-Entry, SAK EMKM)      │            │  │
│                       │  └────────────────┬────────────────┘            │  │
│                       │                   │                             │  │
│                       └───────────────────┼─────────────────────────────┘  │
│                                           │                                │
│                                           ▼                                │
│                                 ┌─────────────────┐                        │
│                                 │   PostgreSQL    │                        │
│                                 │   (79 tables)   │                        │
│                                 └─────────────────┘                        │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘

                                    ▲
                                    │
        ┌───────────────────────────┼───────────────────────────┐
        │                           │                           │
        ▼                           ▼                           ▼
┌───────────────┐         ┌───────────────────┐        ┌───────────────┐
│    SME        │         │   Accounting      │        │   Customers   │
│   Owners      │         │   Staff           │        │   (Solar      │
│               │         │                   │        │   Proposals)  │
└───────────────┘         └───────────────────┘        └───────────────┘
```

---

## User Personas

### 1. SME Owner / Manager
- Views dashboards, reports
- Approves quotations, invoices
- Monitors cash flow, profitability
- Makes business decisions

### 2. Accounting Staff
- Creates journal entries
- Manages invoices, bills
- Processes payments
- Generates financial reports

### 3. Sales Staff
- Creates quotations
- Follows up on opportunities
- Converts quotations to invoices
- Manages customer relationships

### 4. Purchasing Staff
- Creates purchase orders
- Receives goods (GRN)
- Manages vendor bills
- Tracks inventory

### 5. Manufacturing Staff
- Creates work orders
- Manages BOMs
- Runs MRP calculations
- Tracks production

### 6. External Customers
- Views solar proposals (public link)
- Accepts/rejects proposals
- Uses public solar calculator

---

## Container Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                            ENTER365                                     │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│   ┌─────────────────────────┐    ┌─────────────────────────────────┐   │
│   │     VUE.JS SPA          │    │      LARAVEL API                │   │
│   │                         │    │                                 │   │
│   │ - TypeScript            │───▶│ - RESTful JSON API              │   │
│   │ - Inertia.js           │    │ - Sanctum Auth                  │   │
│   │ - Tailwind CSS          │    │ - 418 Routes                    │   │
│   │                         │    │ - 53 Controllers                │   │
│   │ Served from Laravel     │    │ - 37 Services                   │   │
│   └─────────────────────────┘    │ - 70 Models                     │   │
│                                  │                                 │   │
│                                  └────────────────┬────────────────┘   │
│                                                   │                    │
│   ┌─────────────────────────┐                     │                    │
│   │     BACKGROUND JOBS     │                     │                    │
│   │                         │                     │                    │
│   │ - Recurring generation  │◀────────────────────┤                    │
│   │ - Overdue detection     │                     │                    │
│   │ - Report generation     │                     │                    │
│   │ - MRP calculations      │                     │                    │
│   └─────────────────────────┘                     │                    │
│                                                   │                    │
│                                                   ▼                    │
│                                  ┌─────────────────────────────────┐   │
│                                  │         POSTGRESQL              │   │
│                                  │                                 │   │
│                                  │ - 79 Tables                     │   │
│                                  │ - FK Constraints                │   │
│                                  │ - ACID Transactions             │   │
│                                  │ - JSONB for metadata            │   │
│                                  └─────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Component Overview

### API Layer (53 Controllers)

```
/app/Http/Controllers/Api/V1/
├── AuthController.php           # Login, logout, user
├── AccountController.php        # Chart of accounts
├── BillController.php           # Vendor bills
├── BomController.php            # Bills of material
├── ContactController.php        # Customers & vendors
├── DashboardController.php      # KPI dashboard
├── InvoiceController.php        # Sales invoices
├── JournalEntryController.php   # Journal entries
├── MrpController.php            # MRP calculations
├── PaymentController.php        # Payments
├── ProductController.php        # Products & services
├── PurchaseOrderController.php  # Purchase orders
├── QuotationController.php      # Sales quotations
├── ReportController.php         # Financial reports
├── SolarProposalController.php  # Solar proposals
├── WorkOrderController.php      # Manufacturing
└── ... (53 total)
```

### Service Layer (37 Services)

```
/app/Services/Accounting/
├── JournalService.php           # Core accounting
├── FinancialReportService.php   # Financial statements
├── QuotationService.php         # Sales flow
├── PurchaseOrderService.php     # Purchasing flow
├── InventoryService.php         # Stock management
├── BomService.php               # Manufacturing
├── MrpService.php               # Material planning
├── SolarProposalService.php     # Solar EPC
└── ... (37 total)
```

### Data Layer (70 Models)

```
/app/Models/Accounting/
├── Account.php                  # Chart of accounts
├── JournalEntry.php             # Journal headers
├── JournalEntryLine.php         # Journal lines
├── Invoice.php                  # Sales invoices
├── Bill.php                     # Vendor bills
├── Payment.php                  # Payments
├── Quotation.php                # Quotations
├── Product.php                  # Products
├── Bom.php                      # Bills of material
├── WorkOrder.php                # Work orders
└── ... (70 total)
```

---

## Data Flow Examples

### Sales Flow

```
Quotation → [Approval] → Invoice → [Posting] → Journal Entry
     │                        │                      │
     └─ Items                 └─ Items               └─ Lines
                                   │
                                   ▼
                              Payment → Journal Entry
```

### Purchasing Flow

```
Purchase Order → [Approval] → GRN → Bill → [Posting] → Journal Entry
      │                         │     │                      │
      └─ Items                  │     └─ Items               └─ Lines
                                │
                                ▼
                          Stock Movement
```

### Manufacturing Flow

```
BOM ──────────┐
(Recipe)      │
              ▼
Work Order → Material Requisition → Inventory Movement
    │              │
    │              └─ Components consumed
    │
    └─ Finished Product → Stock In
```

---

## Key Design Decisions

| Decision | Approach | ADR |
|----------|----------|-----|
| Framework | Laravel 12 | [0001](../08-adr/0001-laravel-framework.md) |
| Database | PostgreSQL | [0002](../08-adr/0002-postgresql-database.md) |
| Business Logic | Service Layer | [0003](../08-adr/0003-service-layer-pattern.md) |
| Authentication | Sanctum Tokens | [0004](../08-adr/0004-sanctum-authentication.md) |
| Namespace | Single Accounting | [0005](../08-adr/0005-single-accounting-namespace.md) |
| Compliance | SAK EMKM | [0006](../08-adr/0006-sak-emkm-compliance.md) |
| Modules | Feature Flags | [0007](../08-adr/0007-feature-flag-system.md) |
| Currency | Integer Storage | [0008](../08-adr/0008-integer-currency-storage.md) |
| BOM Variants | Variant Groups | [0009](../08-adr/0009-bom-variant-groups.md) |
| Config | Config-Driven | [0010](../08-adr/0010-configuration-driven-rules.md) |

---

## External Integrations

Currently, Enter365 is self-contained with no external integrations. Planned:

| Integration | Purpose | Status |
|-------------|---------|--------|
| Email | Notifications, reminders | Planned |
| PLN API | Real-time tariffs | Planned |
| Bank API | Statement import | Planned |
| Tax (DJP) | E-Faktur export | Planned |

---

## Deployment Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        PRODUCTION                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│   ┌─────────────┐    ┌─────────────┐    ┌─────────────┐        │
│   │   Nginx     │───▶│   PHP-FPM   │───▶│ PostgreSQL  │        │
│   │   (Proxy)   │    │ (Laravel)   │    │ (Database)  │        │
│   └─────────────┘    └─────────────┘    └─────────────┘        │
│                            │                                    │
│                            ▼                                    │
│                      ┌─────────────┐                           │
│                      │   Redis     │ (Planned: Cache, Queue)   │
│                      └─────────────┘                           │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Related Documentation

- [Service Layer](./service-layer.md) - Service details
- [Data Model](./data-model.md) - Entity relationships
- [API Design](./api-design.md) - API conventions
- [ADR Index](../08-adr/README.md) - All decisions
