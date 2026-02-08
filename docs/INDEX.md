# Documentation Index

> **One-page quick reference for AI agents - all docs on one page**

---

## Entry Points

| Document | Purpose |
|----------|---------|
| `/docs/README.md` | AI agent entry point, architecture overview |
| `/docs/GLOSSARY.md` | Indonesian ↔ English business terms |
| `/CLAUDE.md` | Project-specific AI instructions |

---

## 00 - Getting Started

| Document | Purpose |
|----------|---------|
| `/docs/00-getting-started/quick-start.md` | Essential info to start immediately |

---

## 01 - Architecture

| Document | Purpose |
|----------|---------|
| `/docs/01-architecture/system-overview.md` | C4 Context diagram |
| `/docs/01-architecture/service-layer.md` | 77 services explained |
| `/docs/01-architecture/data-model.md` | 71 models overview |
| `/docs/01-architecture/api-design.md` | RESTful conventions |
| `/docs/01-architecture/domain-layer.md` | **DDD patterns: StateMachines, ValueObjects, Events** |

---

## 02 - Domain Models

| Document | Purpose |
|----------|---------|
| `/docs/02-domain/accounting.md` | SAK EMKM, chart of accounts |
| `/docs/02-domain/sales-cycle.md` | Quotation → Invoice → Payment |
| `/docs/02-domain/purchasing-cycle.md` | PO → GRN → Bill → Payment |
| `/docs/02-domain/manufacturing.md` | BOM → Work Order → MRP |
| `/docs/02-domain/inventory.md` | Multi-warehouse stock |
| `/docs/02-domain/solar-epc.md` | Solar proposals (killer feature) |

---

## 03 - Workflows

| Document | Purpose |
|----------|---------|
| `/docs/03-workflows/sales-workflow.md` | Complete sales cycle |
| `/docs/03-workflows/purchasing-workflow.md` | Complete purchasing cycle |
| `/docs/03-workflows/manufacturing-workflow.md` | Work order execution |
| `/docs/03-workflows/mrp-workflow.md` | MRP demand → suggestions |
| `/docs/03-workflows/bom-variants-workflow.md` | Multi-alternative BOM |

---

## 04 - API Reference

| Document | Purpose |
|----------|---------|
| `/api.json` | OpenAPI specification (Scramble-generated) |
| `./scripts/check-api-integration.sh` | API contract validation script |

**Note:** Use Laravel Boost's `list-routes` tool for route discovery.

**API Contract Validation:**
- Run `./scripts/check-api-integration.sh` after modifying API Resources
- Pre-commit hook validates automatically
- CI/CD validates on pull requests

---

## 05 - Development Workflow

| Document | Purpose |
|----------|---------|
| `/docs/09-development/development-workflow.md` | Complete development workflow guide |
| `/README_PHPSTAN.md` | PHPStan setup and usage |
| `./scripts/check-api-integration.sh` | API contract validation |

**Key Tools:**
- Laravel Pint - Code formatting
- PHPStan - Type checking
- API Contract Validation - Schema consistency
- Pre-commit Hooks - Automated checks

---

## 06 - Entities

Entity documentation is embedded in:
- Model files: `app/Models/{Domain}/` (71 models)
- Migration files: `database/migrations/`
- Use Laravel Boost's `database-schema` tool for schema inspection

---

## 07 - Business Rules

Business rules are documented in:
- ADRs: `/docs/08-adr/` (see Indonesian Context ADRs)
- Config: `/config/accounting.php`
- Domain code: `app/Domain/*/`

---

## 08 - Code Patterns

| Document | Purpose |
|----------|---------|
| `/docs/07-code-patterns/service-pattern.md` | Service class conventions |
| `/docs/07-code-patterns/controller-pattern.md` | Thin controller pattern |
| `/docs/07-code-patterns/model-pattern.md` | Eloquent model conventions |
| `/docs/07-code-patterns/validation-pattern.md` | Form Request classes |
| `/docs/07-code-patterns/testing-pattern.md` | Pest tests, factories |
| `/docs/07-code-patterns/state-machine-pattern.md` | **Document workflow states** |
| `/docs/07-code-patterns/strategy-pattern.md` | **Pluggable algorithms** |
| `/docs/07-code-patterns/event-listener-pattern.md` | **Domain events & listeners** |
| `/docs/07-code-patterns/filter-pattern.md` | **API query filtering** |

---

## 09 - Architecture Decision Records (48 ADRs)

| Document | Purpose |
|----------|---------|
| `/docs/08-adr/README.md` | ADR index and overview |
| `/docs/08-adr/template.md` | ADR template |

### Critical ADRs (Technology)

| ADR | Title |
|-----|-------|
| 0001 | Laravel 12 framework choice |
| 0002 | PostgreSQL database |
| 0003 | Service layer pattern |
| 0004 | Sanctum authentication |
| 0005 | Single accounting namespace |
| 0006 | SAK EMKM compliance |
| 0007 | Feature flag system |
| 0008 | Integer currency storage |
| 0009 | BOM variant groups |
| 0010 | Configuration-driven rules |

### Domain ADRs

| ADR | Title |
|-----|-------|
| 0011 | Double-entry bookkeeping |
| 0012 | Chart of accounts hierarchy |
| 0013 | Multi-warehouse inventory |
| 0014 | Inventory costing methods |
| 0015 | Multi-option quotations |
| 0016 | Quotation from BOM |
| 0017 | MRP demand calculation |
| 0018 | Subcontractor retention |
| 0019 | Down payment application |
| 0020 | Sales/purchase returns |

### Indonesian Context ADRs

| ADR | Title |
|-----|-------|
| 0026 | PPN/VAT calculation (11%) |
| 0027 | Indonesian fiscal year |
| 0028 | NPWP validation |
| 0029 | IDR default currency |
| 0030 | Document number format |
| 0031 | Indonesian language |
| 0032 | Aging report buckets |
| 0033 | Payment terms |

### API Design ADRs

| ADR | Title |
|-----|-------|
| 0034 | API versioning |
| 0035 | Pagination standard |
| 0036 | Error response format |
| 0037 | Form request validation |
| 0038 | API resource transformation |
| 0039 | Eloquent vs query builder |
| 0040 | Soft deletes |

### System Design ADRs

| ADR | Title |
|-----|-------|
| 0041 | Audit logging |
| 0042 | Attachment storage |
| 0043 | RBAC system |
| 0044 | Fiscal period lock |
| 0045 | Bank reconciliation |
| 0046 | Recurring documents |
| 0047 | Dashboard KPIs |
| 0048 | Excel export |

---

## File Locations Quick Reference

| Component | Path | Count |
|-----------|------|-------|
| **Models** | `/app/Models/{Domain}/` | 71 |
| **Services** | `/app/Services/{Domain}/` | 77 |
| **Contracts** | `/app/Contracts/{Domain}/` | 40 |
| **Domain Layer** | `/app/Domain/{Domain}/` | 122 |
| **Controllers** | `/app/Http/Controllers/Api/V1/` | 49 |
| **Form Requests** | `/app/Http/Requests/Api/V1/` | 92 |
| **API Resources** | `/app/Http/Resources/Api/V1/` | 75+ |
| **Filters** | `/app/Filters/` | 16 |
| **Migrations** | `/database/migrations/` | 101+ |
| **Tests** | `/tests/Feature/`, `/tests/Unit/` | 78 files |
| **Config** | `/config/accounting.php`, `/config/features.php` | - |
| **Routes** | `/routes/api.php` | 513 routes |

---

## Search Shortcuts

**Find a pattern doc:**
```
/docs/07-code-patterns/{pattern}-pattern.md
```

**Find an ADR:**
```
/docs/08-adr/00XX-{topic}.md
```

**Find a workflow:**
```
/docs/03-workflows/{domain}-workflow.md
```

**Find Indonesian term:**
```
/docs/GLOSSARY.md → Ctrl+F → "term"
```

**Find model/service:**
```
app/Models/{Domain}/{Model}.php
app/Services/{Domain}/{Model}Service.php
```

---

## Tool Usage for Discovery

Use Laravel Boost MCP tools:

```
# Find routes
list-routes

# View database schema
database-schema

# Search docs
search-docs queries=["state machine", "workflow"]

# Execute code
tinker code="Invoice::count()"

# Check errors
last-error
browser-logs entries=10
```
