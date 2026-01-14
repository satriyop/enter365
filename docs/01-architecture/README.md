---
section: architecture
title: "Architecture Overview"
order: 1
---

# Architecture Overview

> **Technical architecture of Enter365 - the Indonesian SME accounting system**
>
> This section documents the system's architectural decisions, patterns, and structure.

---

## AI Agent Quick Reference

**Use this section when:**
- Understanding overall system design
- Finding where code lives
- Learning architectural patterns
- Onboarding to the codebase

**Quick Links:**
- [System Overview](./system-overview.md) - C4 Context and high-level design
- [Service Layer](./service-layer.md) - 37 services and their responsibilities
- [Data Model](./data-model.md) - 70 Eloquent models
- [API Design](./api-design.md) - RESTful conventions

---

## Architecture Summary

### Technology Stack

| Layer | Technology | Version |
|-------|------------|---------|
| Framework | Laravel | 12 |
| Language | PHP | 8.4 |
| Database | PostgreSQL | 15+ |
| API Auth | Sanctum | 4 |
| Testing | Pest | 4 |
| Styling | Pint | 1 |

### Key Metrics

| Metric | Count |
|--------|-------|
| API Routes | 418 |
| Database Tables | 79 |
| Eloquent Models | 70 |
| Services | 37 |
| Controllers | 53 |
| Migrations | 101 |

---

## Architecture Principles

### 1. Service Layer Pattern

All business logic lives in service classes, not controllers.

```
Controller → Service → Model → Database
    ↓           ↓
 Request     Logic
Validation  Transaction
 Response   Calculation
```

**See:** [ADR-0003](../08-adr/0003-service-layer-pattern.md), [Service Layer](./service-layer.md)

### 2. Single Namespace

All accounting models and services under one namespace:
- `App\Models\Accounting\*`
- `App\Services\Accounting\*`

**See:** [ADR-0005](../08-adr/0005-single-accounting-namespace.md)

### 3. Configuration-Driven Rules

Business rules (tax rates, payment terms) are configurable, not hardcoded.

**See:** [ADR-0010](../08-adr/0010-configuration-driven-rules.md)

### 4. Feature Flags for Modules

Modules are enabled/disabled via feature flags, not code changes.

**See:** [ADR-0007](../08-adr/0007-feature-flag-system.md)

### 5. Integer Currency Storage

All monetary amounts stored as integers to avoid floating-point errors.

**See:** [ADR-0008](../08-adr/0008-integer-currency-storage.md)

---

## Directory Structure

```
enter365/
├── app/
│   ├── Contracts/                 # Interfaces
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── Api/
│   │   │       └── V1/            # 53 API controllers
│   │   ├── Middleware/
│   │   ├── Requests/
│   │   │   └── Api/V1/            # Form Request validation
│   │   └── Resources/
│   │       └── Api/V1/            # API Resources (JSON)
│   ├── Models/
│   │   ├── Accounting/            # 70 Eloquent models
│   │   └── User.php
│   ├── Services/
│   │   └── Accounting/            # 37 service classes
│   └── Support/                   # Helpers, Features facade
│
├── bootstrap/
│   └── app.php                    # Laravel 12 bootstrap
│
├── config/
│   ├── accounting.php             # Business rules config
│   ├── features.php               # Feature flags
│   └── ...
│
├── database/
│   ├── factories/
│   │   └── Accounting/            # Model factories
│   ├── migrations/                # 101 migrations
│   └── seeders/
│
├── docs/                          # Documentation (you are here)
│
├── routes/
│   ├── api.php                    # API routes (418)
│   └── web.php                    # Web routes
│
└── tests/
    ├── Feature/                   # Feature tests
    └── Unit/                      # Unit tests
```

---

## Request Flow

```
┌─────────┐     ┌──────────┐     ┌──────────────┐
│ Client  │────▶│  Routes  │────▶│  Middleware  │
└─────────┘     │ api.php  │     │ auth:sanctum │
                └──────────┘     │ feature:xxx  │
                                 └──────────────┘
                                        │
                ┌───────────────────────▼───────────────────────┐
                │                  Controller                   │
                │  - Validate request (Form Request)            │
                │  - Call service method                        │
                │  - Return JSON response (API Resource)        │
                └───────────────────────┬───────────────────────┘
                                        │
                ┌───────────────────────▼───────────────────────┐
                │                   Service                     │
                │  - Business logic                             │
                │  - Transaction wrapping                       │
                │  - Cross-model operations                     │
                └───────────────────────┬───────────────────────┘
                                        │
                ┌───────────────────────▼───────────────────────┐
                │                    Model                      │
                │  - Data access                                │
                │  - Relationships                              │
                │  - Scopes & accessors                         │
                └───────────────────────┬───────────────────────┘
                                        │
                ┌───────────────────────▼───────────────────────┐
                │                 PostgreSQL                    │
                │  - 79 tables                                  │
                │  - FK constraints                             │
                │  - ACID transactions                          │
                └───────────────────────────────────────────────┘
```

---

## Module Overview

### Core Modules (Always Enabled)

| Module | Purpose | Key Models |
|--------|---------|------------|
| Accounting | GL, journal entries | Account, JournalEntry |
| Contacts | Customers & vendors | Contact |
| Products | Items & services | Product, ProductCategory |

### Feature Modules (Toggleable)

| Module | Feature Flag | Purpose |
|--------|--------------|---------|
| Sales | `sales` | Quotations, invoices, payments |
| Purchasing | `purchasing` | POs, GRNs, bills |
| Inventory | `inventory` | Warehouses, stock tracking |
| Manufacturing | `manufacturing` | BOMs, work orders |
| MRP | `mrp` | Material requirements planning |
| Projects | `projects` | Project cost tracking |
| Solar | `solar` | Solar EPC proposals |
| Recurring | `recurring` | Recurring documents |

---

## Related Documentation

| Document | Description |
|----------|-------------|
| [System Overview](./system-overview.md) | High-level architecture |
| [Service Layer](./service-layer.md) | Service class details |
| [Data Model](./data-model.md) | Entity relationships |
| [API Design](./api-design.md) | API conventions |
| [ADR Index](../08-adr/README.md) | All 48 decisions |

---

## Key Files Quick Reference

| Purpose | Path |
|---------|------|
| Bootstrap | `/bootstrap/app.php` |
| API Routes | `/routes/api.php` |
| Business Config | `/config/accounting.php` |
| Feature Flags | `/config/features.php` |
| Models | `/app/Models/Accounting/` |
| Services | `/app/Services/Accounting/` |
| Controllers | `/app/Http/Controllers/Api/V1/` |
