# Appendix B: Target Architecture Diagram

## High-Level Target Architecture

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              PRESENTATION LAYER                                 │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Controllers (API/V1/)                                                          │
│  ├── Extends BaseApiController (rate limiting, error handling)                  │
│  ├── Minimal logic - delegates to Application Services                          │
│  └── Returns API Resources with metadata                                        │
│                                                                                 │
│  API Resources                                                                  │
│  ├── Includes workflow metadata from state machines                             │
│  ├── HATEOAS-style links for valid actions                                      │
│  └── Consistent error response format                                           │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              APPLICATION LAYER                                  │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Application Services (Commands - Write Operations)                             │
│  ├── Extend AbstractApplicationService                                          │
│  ├── Return ServiceResult<T> for all operations                                 │
│  ├── Use Repositories for data access                                           │
│  ├── Dispatch Domain Events                                                     │
│  └── Implement domain-specific interfaces                                       │
│                                                                                 │
│  Query Services (Queries - Read Operations)                                     │
│  ├── Extend AbstractQueryService                                                │
│  ├── Optimized read queries (no hydration overhead)                             │
│  ├── Pagination, filtering, sorting                                             │
│  └── May use DB::table() for performance                                        │
│                                                                                 │
│  Base Classes:                                                                  │
│  ├── AbstractApplicationService (logging, transactions, events)                 │
│  ├── AbstractDocumentService (CRUD + state machine for documents)               │
│  └── AbstractQueryService (read operations)                                     │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                                DOMAIN LAYER                                     │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Domain/                                                                        │
│  ├── Sales/                                                                     │
│  │   ├── Invoices/                                                              │
│  │   │   ├── InvoiceStateMachine (with guards, actions, history)                │
│  │   │   └── Events/ (extends DomainEvent)                                      │
│  │   ├── Quotations/                                                            │
│  │   └── SalesOrders/                                                           │
│  │                                                                              │
│  ├── Purchasing/                                                                │
│  │   ├── PurchaseInvoices/                                                      │
│  │   ├── PurchaseOrders/                                                        │
│  │   └── Receivings/                                                            │
│  │                                                                              │
│  ├── Manufacturing/ (NEW - Complete structure)                                  │
│  │   ├── WorkOrders/                                                            │
│  │   │   ├── WorkOrderStateMachine                                              │
│  │   │   ├── Events/                                                            │
│  │   │   └── Handlers/                                                          │
│  │   │       ├── MaterialConsumptionHandler                                     │
│  │   │       └── FinishedGoodsHandler                                           │
│  │   └── BillOfMaterials/                                                       │
│  │       └── BomExploder                                                        │
│  │                                                                              │
│  ├── Inventory/ (NEW - Complete structure)                                      │
│  │   ├── Movements/                                                             │
│  │   │   ├── MovementRecorder                                                   │
│  │   │   ├── MovementValidator                                                  │
│  │   │   └── Events/                                                            │
│  │   └── StockOpname/                                                           │
│  │       └── StockOpnameStateMachine                                            │
│  │                                                                              │
│  ├── Accounting/                                                                │
│  │   └── Strategies/ (existing + new)                                           │
│  │       ├── COGSRecognitionStrategy                                            │
│  │       ├── TaxCalculationStrategy (NEW)                                       │
│  │       └── ...                                                                │
│  │                                                                              │
│  └── Shared/                                                                    │
│      ├── ValueObjects/                                                          │
│      │   ├── Money                                                              │
│      │   ├── Quantity                                                           │
│      │   └── DateRange                                                          │
│      ├── Events/                                                                │
│      │   └── DomainEvent (base class)                                           │
│      ├── NumberGeneration/                                                      │
│      │   ├── NumberGenerationStrategy                                           │
│      │   ├── SequentialNumberStrategy                                           │
│      │   └── ProjectBasedNumberStrategy                                         │
│      └── Approval/                                                              │
│          ├── ApprovalStrategy                                                   │
│          └── AmountBasedApprovalStrategy                                        │
│                                                                                 │
│  State Machines:                                                                │
│  └── AbstractStateMachine (enhanced with guards, actions, history, metadata)    │
│                                                                                 │
│  Domain Exceptions:                                                             │
│  ├── DomainException (base)                                                     │
│  ├── ValidationException                                                        │
│  ├── BusinessRuleException                                                      │
│  ├── EntityNotFoundException                                                    │
│  ├── DocumentLockedException                                                    │
│  └── StateTransitionException                                                   │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        ▼
┌─────────────────────────────────────────────────────────────────────────────────┐
│                             INFRASTRUCTURE LAYER                                │
├─────────────────────────────────────────────────────────────────────────────────┤
│  Repositories/ (NEW)                                                            │
│  ├── Contracts/                                                                 │
│  │   ├── RepositoryInterface                                                    │
│  │   ├── SpecificationInterface                                                 │
│  │   └── Domain-specific interfaces                                             │
│  │                                                                              │
│  ├── Eloquent/                                                                  │
│  │   ├── EloquentRepository (base)                                              │
│  │   ├── EloquentInvoiceRepository                                              │
│  │   ├── EloquentWorkOrderRepository                                            │
│  │   └── EloquentProductStockRepository                                         │
│  │                                                                              │
│  └── InMemory/ (for testing)                                                    │
│      ├── InMemoryRepository (base)                                              │
│      └── InMemoryInvoiceRepository                                              │
│                                                                                 │
│  Events/                                                                        │
│  ├── EventServiceProvider (auto-discovery)                                      │
│  ├── Subscribers/                                                               │
│  │   ├── InvoiceEventSubscriber                                                 │
│  │   ├── WorkOrderEventSubscriber                                               │
│  │   └── InventoryMovementSubscriber                                            │
│  └── Testing/                                                                   │
│      └── RecordingEventDispatcher                                               │
│                                                                                 │
│  Logging/                                                                       │
│  ├── ContextualLoggerInterface                                                  │
│  ├── LaravelContextualLogger                                                    │
│  └── NullContextualLogger (testing)                                             │
│                                                                                 │
│  Models/ (Eloquent - data access only)                                          │
│  └── (unchanged, but domain logic moved to Domain layer)                        │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Contracts/Interfaces Target Structure

```
Contracts/
├── Sales/
│   ├── InvoiceServiceInterface.php
│   ├── InvoiceQueryServiceInterface.php (NEW)
│   └── ... (query + command separation)
│
├── Repositories/ (NEW)
│   ├── RepositoryInterface.php
│   ├── SpecificationInterface.php
│   └── Sales/
│       └── InvoiceRepositoryInterface.php
│
├── Logging/ (NEW)
│   └── ContextualLoggerInterface.php
│
├── Shared/
│   ├── NumberGenerationStrategy.php (NEW)
│   └── ApprovalStrategy.php (NEW)
│
└── Accounting/
    └── TaxCalculationStrategy.php (NEW)
```

---

## Event Architecture (Target)

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           EventServiceProvider                                   │
│                          (Auto-discovery enabled)                                │
└─────────────────────────────────────────────────────────────────────────────────┘
                                        │
            ┌───────────────────────────┼───────────────────────────┐
            ▼                           ▼                           ▼
┌─────────────────────┐    ┌─────────────────────┐    ┌─────────────────────┐
│InvoiceEventSubscriber│   │WorkOrderEventSubscriber│  │InventorySubscriber │
├─────────────────────┤   ├─────────────────────┤    ├─────────────────────┤
│ - InvoiceCreated    │   │ - WorkOrderCreated  │    │ - MovementRecorded  │
│ - InvoiceSent       │   │ - WorkOrderStarted  │    │ - StockAdjusted     │
│ - InvoicePaid       │   │ - WorkOrderCompleted│    │ - TransferCompleted │
│ - InvoiceCancelled  │   │ - WorkOrderCancelled│    │                     │
└─────────────────────┘   └─────────────────────┘    └─────────────────────┘
            │                           │                           │
            └───────────────────────────┼───────────────────────────┘
                                        ▼
                            ┌─────────────────────┐
                            │ Cross-Cutting       │
                            │ - NotificationListener│
                            │ - AuditLogListener  │
                            │ - MetricsListener   │
                            └─────────────────────┘
```

---

## Data Flow: Invoice Creation (Target)

```
┌──────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌──────────────────┐
│Controller│───▶│ InvoiceService  │───▶│ InvoiceRepository│───▶│ Invoice Model   │
│          │    │                 │    │                  │    │ (Data only)     │
└──────────┘    └─────────────────┘    └─────────────────┘    └──────────────────┘
     │                  │                                              │
     │                  │                                              ▼
     │                  │                                      ┌──────────────────┐
     │                  │                                      │ Database         │
     │                  │                                      └──────────────────┘
     │                  ▼
     │          ┌───────────────┐
     │          │ State Machine │
     │          │ (with guards) │
     │          └───────────────┘
     │                  │
     │                  ▼
     │          ┌───────────────┐
     │          │ Contextual    │
     │          │ Logger        │──────▶ Logs (structured)
     │          └───────────────┘
     │                  │
     │                  ▼
     │          ┌───────────────┐
     │          │ Event         │
     │          │ Dispatcher    │
     │          └───────────────┘
     │                  │
     │                  ▼
     │          ┌───────────────────────────┐
     │          │ InvoiceEventSubscriber    │
     │          │ - CreateJournalEntry      │
     │          │ - UpdateCustomerBalance   │
     │          │ - SendNotification (async)│
     │          │ - RecordMetrics           │
     │          └───────────────────────────┘
     │
     ▼
┌──────────────┐
│ ServiceResult│
│ (Success/Fail)│
└──────────────┘
     │
     ▼
┌──────────────┐
│ API Resource │
│ + workflow   │
│   metadata   │
└──────────────┘
```

---

## Repository Pattern Flow

```
┌─────────────────┐        ┌─────────────────────────┐
│ Application     │        │ RepositoryInterface     │
│ Service         │───────▶│                         │
└─────────────────┘        │ + find(id)              │
                           │ + findBy(spec)          │
                           │ + save(entity)          │
                           │ + delete(entity)        │
                           └─────────────────────────┘
                                       │
                    ┌──────────────────┼──────────────────┐
                    ▼                                     ▼
          ┌─────────────────────┐            ┌─────────────────────┐
          │ EloquentRepository  │            │ InMemoryRepository  │
          │ (Production)        │            │ (Testing)           │
          └─────────────────────┘            └─────────────────────┘
                    │                                     │
                    ▼                                     ▼
          ┌─────────────────────┐            ┌─────────────────────┐
          │ PostgreSQL          │            │ Array               │
          │                     │            │ (in memory)         │
          └─────────────────────┘            └─────────────────────┘
```

---

## State Machine Enhancement

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                        Enhanced AbstractStateMachine                             │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────┐    ┌────────────┐    ┌─────────────┐    ┌──────────────┐      │
│  │   Guards    │───▶│ Transition │───▶│   Actions   │───▶│Status History│      │
│  │ (can trans?)│    │ (execute)  │    │ (side efx) │    │  (audit)     │      │
│  └─────────────┘    └────────────┘    └─────────────┘    └──────────────┘      │
│                                                                                 │
│  Features:                                                                      │
│  - Guard conditions (hasItems, canApprove, etc.)                                │
│  - Pre/post transition actions                                                  │
│  - Status history recording                                                     │
│  - Workflow metadata for UI                                                     │
│  - Event dispatching on transition                                              │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Observability Stack

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                              Observability                                       │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐             │
│  │ ContextualLogger│    │ Health Endpoints│    │ Request Tracing │             │
│  ├─────────────────┤    ├─────────────────┤    ├─────────────────┤             │
│  │ - Request ID    │    │ GET /health     │    │ X-Request-ID    │             │
│  │ - User context  │    │ GET /ready      │    │ propagated      │             │
│  │ - Operation     │    │ GET /live       │    │ through calls   │             │
│  │ - Performance   │    │                 │    │                 │             │
│  └─────────────────┘    └─────────────────┘    └─────────────────┘             │
│           │                      │                      │                       │
│           └──────────────────────┼──────────────────────┘                       │
│                                  ▼                                              │
│                    ┌─────────────────────────┐                                  │
│                    │     LogsPerformance     │                                  │
│                    │         Trait           │                                  │
│                    ├─────────────────────────┤                                  │
│                    │ - Auto timing           │                                  │
│                    │ - Memory tracking       │                                  │
│                    │ - Slow query detection  │                                  │
│                    └─────────────────────────┘                                  │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Strategy Pattern Usage

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                             Strategy Patterns                                    │
├─────────────────────────────────────────────────────────────────────────────────┤
│                                                                                 │
│  NumberGenerationStrategy                  TaxCalculationStrategy               │
│  ├── SequentialNumberStrategy              ├── TaxExclusiveStrategy             │
│  └── ProjectBasedNumberStrategy            └── TaxInclusiveStrategy             │
│                                                                                 │
│  PricingStrategy                           ApprovalStrategy                     │
│  ├── StandardPricingStrategy               └── AmountBasedApprovalStrategy      │
│  ├── CustomerPricingStrategy                                                    │
│  └── VolumePricingStrategy                                                      │
│                                                                                 │
│  COGSRecognitionStrategy (existing)        InventoryAccountingStrategy (exist)  │
│  ├── COGSOnInvoiceStrategy                 ├── PerpetualStrategy                │
│  ├── COGSOnDeliveryStrategy                ├── PeriodicStrategy                 │
│  └── ManualCOGSStrategy                    └── HybridStrategy                   │
│                                                                                 │
│  ManufacturingCostStrategy (existing)      YearEndClosingStrategy (existing)    │
│  ├── WIPStrategy                           ├── DirectClosingStrategy            │
│  ├── JobCostingStrategy                    └── IncomeSummaryStrategy            │
│  └── ProjectBasedStrategy                                                       │
│                                                                                 │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## Testing Architecture

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── ValueObjects/
│   │   │   ├── MoneyTest.php
│   │   │   └── QuantityTest.php
│   │   └── StateMachine/
│   │       └── InvoiceStateMachineTest.php
│   └── Repositories/
│       └── EloquentInvoiceRepositoryTest.php
│
├── Feature/
│   └── Services/
│       ├── Sales/
│       │   └── InvoiceServiceTest.php
│       └── Manufacturing/
│           └── WorkOrderServiceTest.php
│
└── Traits/
    ├── InteractsWithDomainEvents.php
    ├── InteractsWithRepositories.php
    └── TestsServices.php
```

---

## Service Provider Organization

```
Providers/
├── AppServiceProvider.php
│   └── Core bindings, no event listeners
│
├── RepositoryServiceProvider.php (NEW)
│   └── All repository bindings
│
├── EventServiceProvider.php (REFACTORED)
│   └── Event subscribers, auto-discovery config
│
├── StrategyServiceProvider.php (NEW)
│   └── All strategy pattern bindings
│
├── AccountingServiceProvider.php (existing)
│   └── Accounting-specific services
│
└── ObservabilityServiceProvider.php (NEW)
    └── Logger, health checks, tracing
```

---

## Key Improvements Summary

| Area | Before | After |
|------|--------|-------|
| **Service Layer** | Inconsistent base classes | All extend AbstractApplicationService |
| **Data Access** | Direct Eloquent | Repository pattern |
| **Events** | 200+ lines in AppServiceProvider | Event subscribers with auto-discovery |
| **Observability** | Basic logging | Contextual logging, request IDs, health checks |
| **State Machines** | Basic transitions | Guards, actions, history, metadata |
| **Domain Layer** | Incomplete for Manufacturing/Inventory | Complete domain structure |
| **Testing** | Feature tests only | Unit + feature + in-memory repositories |
| **Error Handling** | Inconsistent | Domain exceptions hierarchy |
| **Strategies** | Some areas | Extended to numbering, tax, pricing, approval |
| **API** | Basic controllers | Base controller, rate limiting, consistent errors |
