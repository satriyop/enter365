# Enter365 Documentation

> **AI-First Documentation System**
>
> This documentation is optimized for AI agents (LLMs) while remaining useful for humans.
> Every document includes machine-parseable context and cross-references.

---

## Quick Start for AI Agents

**New to this codebase?** Follow this sequence:

1. **Read** `/docs/INDEX.md` - One-page index of all documentation
2. **Scan** `/docs/GLOSSARY.md` - Indonesian ↔ English business terms
3. **Understand** `/docs/01-architecture/system-overview.md` - High-level architecture
4. **Learn DDD patterns** `/docs/01-architecture/domain-layer.md` - StateMachines, Value Objects
5. **Explore** `/docs/02-domain/` - Business domain documentation

**Need specific information?**

| Task | Document |
|------|----------|
| Understand overall system | `/docs/01-architecture/system-overview.md` |
| Learn DDD patterns (StateMachine, ValueObjects) | `/docs/01-architecture/domain-layer.md` |
| Implement sales feature | `/docs/02-domain/sales-cycle.md` |
| Implement manufacturing feature | `/docs/02-domain/manufacturing.md` |
| Create a new StateMachine | `/docs/07-code-patterns/state-machine-pattern.md` |
| Create a Strategy | `/docs/07-code-patterns/strategy-pattern.md` |
| Create query filters | `/docs/07-code-patterns/filter-pattern.md` |
| Why was this decision made? | `/docs/08-adr/` |
| How to code this pattern? | `/docs/07-code-patterns/` |
| Indonesian term meaning? | `/docs/GLOSSARY.md` |

---

## Project Context

**Enter365** is a Laravel 12 ERP/Accounting system for Indonesian SMEs in:
- **Electrical panel manufacturing** - Custom panel builders with multi-brand BOM alternatives
- **Solar EPC contracting** - Solar installation projects with ROI proposals

### Key Statistics

| Metric | Value |
|--------|-------|
| API Routes | 513 |
| Eloquent Models | 71 |
| Services | 77 |
| Contracts/Interfaces | 40 |
| Domain Layer Files | 122 (StateMachines, Events, ValueObjects, Strategies) |
| Database Migrations | 101+ |
| Tests | 78 test files |
| ADRs | 50 |

### Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 12.44.0 |
| PHP Version | 8.4.14 |
| Database | PostgreSQL |
| Authentication | Sanctum 4.x (token-based) |
| Frontend | Livewire 3.x + Volt 1.x + Tailwind v4 |
| Testing | Pest v4 |
| API Docs | Scramble (OpenAPI) |

### Accounting Standard

**SAK EMKM** (Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah) - Indonesian accounting standard for micro, small, and medium enterprises.

---

## Architecture Overview

### Layer Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                    HTTP / API Layer                          │
│  Controllers (thin) → Form Requests → API Resources          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Service Layer                             │
│  Business Logic, Transactions, Orchestration                 │
│  /app/Services/{Domain}/ - 77 services                       │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Domain Layer (DDD)                        │
│  StateMachines | ValueObjects | DomainEvents | Calculators   │
│  /app/Domain/{Domain}/ - 90+ files                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                 Contracts / Interfaces                       │
│  Service interfaces, Strategy interfaces                     │
│  /app/Contracts/ - 40+ interfaces                            │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Model Layer                               │
│  Eloquent Models (thin), Relationships, Casts                │
│  /app/Models/{Domain}/ - 71 models                           │
└─────────────────────────────────────────────────────────────┘
```

### Key Architectural Patterns

| Pattern | Implementation | Location | Docs |
|---------|---------------|----------|------|
| **State Machine** | Document workflow state transitions | `/app/Domain/*/` | `/docs/07-code-patterns/state-machine-pattern.md` |
| **Strategy** | Pluggable algorithms (COGS, Closing) | `/app/Contracts/*/Strategies/` | `/docs/07-code-patterns/strategy-pattern.md` |
| **Domain Events** | Decoupled side effects | `/app/Domain/*/Events/` | `/docs/07-code-patterns/event-listener-pattern.md` |
| **Value Objects** | Composite domain values | `/app/Domain/*/` (e.g., InvoiceTotals) | `/docs/01-architecture/domain-layer.md` |
| **Service Layer** | Business logic, not controllers | `/app/Services/{Domain}/` | `/docs/07-code-patterns/service-pattern.md` |
| **Query Filters** | Reusable filter classes | `/app/Filters/` | `/docs/07-code-patterns/filter-pattern.md` |
| **Form Requests** | Validation in dedicated classes | `/app/Http/Requests/` | `/docs/07-code-patterns/validation-pattern.md` |
| **API Resources** | Response transformation | `/app/Http/Resources/` | `/docs/01-architecture/api-design.md` |
| **Feature Flags** | Module toggling | `/config/features.php` | ADR-0007 |

---

## Documentation Structure

```
docs/
├── README.md                    # You are here - AI agent entry point
├── INDEX.md                     # One-page quick reference
├── GLOSSARY.md                  # Indonesian ↔ English terms
│
├── 00-getting-started/          # Quick start guide
├── 01-architecture/             # System design + Domain Layer
├── 02-domain/                   # Business domains (sales, manufacturing)
├── 03-workflows/                # Flowcharts and process flows
├── 04-api/                      # API conventions
├── 05-entities/                 # Entity documentation
├── 06-business-rules/           # Business logic rules
├── 07-code-patterns/            # Code conventions + DDD patterns
├── 08-adr/                      # Architecture Decision Records (48)
├── 09-development/              # Setup, testing, debugging
└── 10-references/               # External references
```

---

## Code Organization by Domain

| Domain | Models | Services | StateMachines | Events |
|--------|--------|----------|---------------|--------|
| **Accounting** | 9 | 26 | FiscalPeriodStateMachine | 5 |
| **Sales** | 12 | 14 | Invoice, Quotation, DeliveryOrder, SalesReturn | 20+ |
| **Purchasing** | 8 | 6 | Bill, PurchaseOrder, PurchaseReturn | 15+ |
| **Manufacturing** | 18 | 20 | - | - |
| **Inventory** | 7 | 4 | - | - |
| **Projects** | 3 | 2 | ProjectStateMachine | 6 |
| **Solar** | 3 | 2 | - | - |
| **Shared** | 5 | - | - | - |
| **Contacts** | 1 | - | - | - |

---

## Business Domains

### Core Accounting
- **Chart of Accounts** - SAK EMKM compliant hierarchical structure
- **Journal Entries** - Double-entry bookkeeping with auto-reversals
- **Fiscal Periods** - Period open/close/lock with StateMachine
- **Multi-Currency** - IDR default with exchange rate tracking

### Sales & Receivables
- **Quotations** - Standard and multi-option (Budget/Standard/Premium), StateMachine
- **Invoices** - Sales invoices with InvoiceStateMachine, InvoiceCalculator
- **Delivery Orders** - Shipment with DeliveryOrderStateMachine
- **Down Payments** - Prepayment tracking and application
- **Sales Returns** - Returns with SalesReturnStateMachine + approval pipeline

### Purchasing & Payables
- **Purchase Orders** - PurchaseOrderStateMachine
- **Goods Receipt Notes (GRN)** - Multi-step receiving workflow
- **Bills** - Vendor invoices with BillStateMachine
- **Purchase Returns** - PurchaseReturnStateMachine + approval pipeline

### Inventory Management
- **Products** - Master catalog with MRP fields
- **Warehouses** - Multi-location stock tracking
- **Stock Movements** - In/out/transfer/adjustment
- **Stock Opname** - Physical inventory counting

### Manufacturing
- **Bill of Materials (BOM)** - Product recipes with variants
- **BOM Variant Groups** - Multi-brand alternatives (ABB/Schneider/Siemens)
- **Work Orders** - Production execution
- **Material Requisitions** - Material requests for production
- **MRP** - Material Requirements Planning with suggestions

### Project Costing
- **Projects** - ProjectStateMachine (draft → active → complete)
- **Cost Tracking** - Labor, material, overhead allocation
- **Revenue Recognition** - Project revenue tracking

### Solar EPC (Killer Feature)
- **Solar Proposals** - Energy savings, ROI, ESG metrics
- **Indonesian Solar Data** - Location-specific irradiance
- **PLN Tariffs** - Indonesian electricity rates
- **Public Proposal Links** - Shareable proposal URLs

---

## File Locations Quick Reference

| Component | Path | Count |
|-----------|------|-------|
| **Models** | `/app/Models/{Domain}/` | 71 |
| **Services** | `/app/Services/{Domain}/` | 77 |
| **Contracts** | `/app/Contracts/{Domain}/` | 40+ |
| **Domain Layer** | `/app/Domain/{Domain}/` | 90+ |
| **Controllers** | `/app/Http/Controllers/Api/V1/` | 49 |
| **Form Requests** | `/app/Http/Requests/Api/V1/` | 92 |
| **API Resources** | `/app/Http/Resources/Api/V1/` | 75+ |
| **Filters** | `/app/Filters/` | 16 |
| **Migrations** | `/database/migrations/` | 101 |
| **Tests** | `/tests/Feature/`, `/tests/Unit/` | 950+ |
| **Config** | `/config/accounting.php`, `/config/features.php` | - |
| **Routes** | `/routes/api.php` | 418 routes |

---

## ADR System

Architecture Decision Records document the "why" behind decisions.

**48 ADRs documented** covering:
- Technology choices (Laravel, PostgreSQL, Sanctum)
- Architectural patterns (service layer, feature flags)
- Domain decisions (SAK EMKM, BOM variants, MRP)
- Indonesian context (PPN, NPWP, fiscal year)

**ADR Index:** `/docs/08-adr/README.md`

---

## Related Files

| File | Purpose |
|------|---------|
| `/CLAUDE.md` | AI agent instructions (project-specific) |
| `/api.json` | OpenAPI specification (Scramble-generated) |
| `/.claude/skills/enter365/` | Project-specific AI skill |
| `/.claude/skills/scaffold-*/` | Code scaffolding skills |

---

## Maintenance

**When to update documentation:**

| Event | Update |
|-------|--------|
| New architectural decision | Create ADR in `/docs/08-adr/` |
| New model added | Create entity doc in `/docs/05-entities/` |
| New StateMachine created | Document in `/docs/07-code-patterns/state-machine-pattern.md` |
| New Strategy created | Document in `/docs/07-code-patterns/strategy-pattern.md` |
| Business rule changed | Update `/docs/06-business-rules/` |
| New Indonesian term | Add to `/docs/GLOSSARY.md` |
| Workflow changed | Update `/docs/03-workflows/` |

---

## Getting Help

- **Feature implementation**: Start with domain docs (`/docs/02-domain/`)
- **DDD patterns**: Check domain layer (`/docs/01-architecture/domain-layer.md`)
- **Code patterns**: Review patterns (`/docs/07-code-patterns/`)
- **"Why" questions**: Search ADRs (`/docs/08-adr/`)
- **Indonesian terms**: Check glossary (`/docs/GLOSSARY.md`)
