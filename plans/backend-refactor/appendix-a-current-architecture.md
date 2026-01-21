# Appendix A: Current Architecture Diagram

## High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Controllers (API/V1/)          │  Resources           │  Requests             │
│  ├── Sales/                     │  ├── InvoiceResource │  ├── InvoiceRequest   │
│  ├── Purchasing/                │  ├── QuotationResource│ ├── QuotationRequest  │
│  ├── Manufacturing/             │  └── WorkOrderResource│ └── WorkOrderRequest  │
│  └── Inventory/                 │                       │                       │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              APPLICATION LAYER                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Services/                                                                      │
│  ├── Sales/                                                                     │
│  │   ├── InvoiceService (extends AbstractDocumentService)                       │
│  │   ├── QuotationService (extends AbstractDocumentService)                     │
│  │   ├── SalesOrderService (extends AbstractDocumentService)                    │
│  │   └── DeliveryService                                                        │
│  ├── Purchasing/                                                                │
│  │   ├── PurchaseInvoiceService (extends AbstractDocumentService)               │
│  │   ├── PurchaseOrderService (extends AbstractDocumentService)                 │
│  │   └── ReceivingService                                                       │
│  ├── Manufacturing/                                                             │
│  │   ├── WorkOrderService                                                       │
│  │   └── ProductionService                                                      │
│  ├── Inventory/                                                                 │
│  │   ├── InventoryService                                                       │
│  │   ├── StockOpnameService                                                     │
│  │   └── ProductStockService                                                    │
│  └── Accounting/                                                                │
│      ├── JournalEntryService                                                    │
│      ├── AccountService                                                         │
│      └── ClosingService                                                         │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Base Classes:                                                                  │
│  └── AbstractDocumentService (CRUD + state machine integration)                 │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                DOMAIN LAYER                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Domain/                                                                        │
│  ├── Sales/                                                                     │
│  │   ├── Invoices/                                                              │
│  │   │   ├── InvoiceStateMachine                                                │
│  │   │   └── Events/ (InvoiceCreated, InvoiceSent, InvoicePaid)                 │
│  │   ├── Quotations/                                                            │
│  │   │   └── QuotationStateMachine                                              │
│  │   └── SalesOrders/                                                           │
│  │       └── SalesOrderStateMachine                                             │
│  ├── Purchasing/                                                                │
│  │   ├── PurchaseInvoices/                                                      │
│  │   │   └── PurchaseInvoiceStateMachine                                        │
│  │   └── PurchaseOrders/                                                        │
│  │       └── PurchaseOrderStateMachine                                          │
│  └── Accounting/                                                                │
│      └── Strategies/                                                            │
│          ├── COGSRecognitionStrategy (Interface)                                │
│          ├── InventoryAccountingStrategy (Interface)                            │
│          ├── ManufacturingCostStrategy (Interface)                              │
│          └── Implementations...                                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│  State Machines:                                                                │
│  └── AbstractStateMachine (base with transitions, guards, events)               │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                             INFRASTRUCTURE LAYER                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Models/ (Eloquent - currently BOTH data & domain logic)                        │
│  ├── Sales/                                                                     │
│  │   ├── Invoice.php                                                            │
│  │   ├── InvoiceItem.php                                                        │
│  │   ├── Quotation.php                                                          │
│  │   └── SalesOrder.php                                                         │
│  ├── Purchasing/                                                                │
│  │   ├── PurchaseInvoice.php                                                    │
│  │   ├── PurchaseOrder.php                                                      │
│  │   └── Receiving.php                                                          │
│  ├── Manufacturing/                                                             │
│  │   ├── WorkOrder.php                                                          │
│  │   └── BillOfMaterial.php                                                     │
│  ├── Inventory/                                                                 │
│  │   ├── Product.php                                                            │
│  │   ├── ProductStock.php                                                       │
│  │   └── InventoryMovement.php                                                  │
│  └── Accounting/                                                                │
│      ├── Account.php                                                            │
│      ├── JournalEntry.php                                                       │
│      └── JournalEntryLine.php                                                   │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Events/                                                                        │
│  ├── LaravelEventDispatcher                                                     │
│  └── NullEventDispatcher (for testing)                                          │
└─────────────────────────────────────────────────────────────────────────────────┘

```

---

## Contracts/Interfaces Structure

```
Contracts/
├── Sales/
│   ├── InvoiceServiceInterface.php
│   ├── QuotationServiceInterface.php
│   └── SalesOrderServiceInterface.php
├── Purchasing/
│   ├── PurchaseInvoiceServiceInterface.php
│   └── PurchaseOrderServiceInterface.php
├── Manufacturing/
│   └── WorkOrderServiceInterface.php
├── Inventory/
│   ├── InventoryServiceInterface.php
│   └── StockOpnameServiceInterface.php
├── Accounting/
│   ├── JournalEntryServiceInterface.php
│   └── ClosingServiceInterface.php
├── Events/
│   └── EventDispatcherInterface.php
└── Shared/
    ├── DocumentLifecycleInterface.php
    └── StateMachineInterface.php
```

---

## Event Registration (Current State in AppServiceProvider)

```php
// Current: 200+ lines of Event::listen() in AppServiceProvider

Event::listen(InvoiceCreated::class, [JournalEntryService::class, 'handleInvoiceCreated']);
Event::listen(InvoiceSent::class, [/* multiple listeners */]);
Event::listen(InvoicePaid::class, [/* multiple listeners */]);
Event::listen(WorkOrderCompleted::class, [/* multiple listeners */]);
// ... 50+ more event registrations
```

---

## Data Flow: Invoice Creation

```
┌──────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌──────────────────┐
│Controller│───▶│ InvoiceService  │───▶│ Invoice Model   │───▶│ Database         │
│          │    │                 │    │ (Eloquent)      │    │ (PostgreSQL)     │
└──────────┘    └─────────────────┘    └─────────────────┘    └──────────────────┘
     │                  │                      │
     │                  │                      │
     │                  ▼                      │
     │          ┌───────────────┐              │
     │          │ State Machine │              │
     │          │ (transition)  │              │
     │          └───────────────┘              │
     │                  │                      │
     │                  ▼                      │
     │          ┌───────────────┐              │
     │          │ Event         │              │
     │          │ Dispatcher    │──────────────┘
     │          └───────────────┘
     │                  │
     │                  ▼
     │          ┌───────────────┐
     │          │ Listeners     │
     │          │ (Journal, etc)│
     │          └───────────────┘
     │
     ▼
┌──────────────┐
│ API Resource │
│ (Response)   │
└──────────────┘
```

---

## Current Pain Points Visualized

### 1. Service Layer Inconsistency

```
Services/
├── Sales/
│   ├── InvoiceService ───────────────▶ extends AbstractDocumentService ✓
│   └── DeliveryService ──────────────▶ standalone (no base) ✗
├── Manufacturing/
│   └── WorkOrderService ─────────────▶ standalone (no base) ✗
└── Inventory/
    └── ProductStockService ──────────▶ standalone (no base) ✗
```

### 2. Domain Layer Gaps

```
Domain/
├── Sales/ ────────────────────▶ Complete (state machines, events) ✓
├── Purchasing/ ───────────────▶ Partial (state machines only) ~
├── Accounting/ ───────────────▶ Partial (strategies only) ~
├── Manufacturing/ ────────────▶ Missing (no domain structure) ✗
└── Inventory/ ────────────────▶ Missing (no domain structure) ✗
```

### 3. No Repository Layer

```
┌─────────────────┐          ┌──────────────────┐
│ Service Layer   │─────────▶│ Eloquent Model   │  Direct coupling!
└─────────────────┘          └──────────────────┘
                                      │
        ┌─────────────────────────────┘
        │
        ▼
┌──────────────────┐
│ Hard to:         │
│ - Mock in tests  │
│ - Swap impl      │
│ - Add caching    │
└──────────────────┘
```

### 4. Event Registration Sprawl

```
AppServiceProvider (boot method)
├── Line 50-100: Sales events
├── Line 100-150: Purchasing events
├── Line 150-200: Manufacturing events
├── Line 200-250: Inventory events
└── Line 250-300: Accounting events

Problems:
- Single massive file
- No grouping by domain
- Hard to find specific listeners
- No auto-discovery
```

---

## Module Dependencies

```
                    ┌──────────────┐
                    │  Accounting  │
                    └──────────────┘
                          ▲
                          │
        ┌─────────────────┼─────────────────┐
        │                 │                 │
        ▼                 ▼                 ▼
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│    Sales     │  │  Purchasing  │  │Manufacturing │
└──────────────┘  └──────────────┘  └──────────────┘
        │                 │                 │
        └─────────────────┼─────────────────┘
                          │
                          ▼
                    ┌──────────────┐
                    │  Inventory   │
                    └──────────────┘
                          │
                          ▼
                    ┌──────────────┐
                    │   Contacts   │
                    └──────────────┘
```

All modules depend on:
- Inventory (for products)
- Contacts (for customers/vendors)
- Accounting (for journal entries)

---

## Database Schema Overview

| Domain | Tables | Relationships |
|--------|--------|---------------|
| Sales | invoices, invoice_items, quotations, sales_orders | →contacts, →products |
| Purchasing | purchase_invoices, purchase_orders, receivings | →contacts, →products |
| Manufacturing | work_orders, bill_of_materials | →products |
| Inventory | products, product_stocks, inventory_movements | →warehouses |
| Accounting | accounts, journal_entries, journal_entry_lines | →all documents |
| Contacts | contacts, contact_addresses | base table |

---

## Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| Runtime | PHP | 8.4.14 |
| Framework | Laravel | 12.x |
| Database | PostgreSQL | 16.x |
| Frontend | Livewire + Volt | 3.x |
| CSS | Tailwind | 4.x |
| Testing | Pest | 4.x |
| API Docs | Scramble | latest |
