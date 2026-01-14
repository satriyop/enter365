# Enter365 Documentation

> **AI-First Documentation System**
>
> This documentation is optimized for AI agents (LLMs) while remaining useful for humans. Every document includes machine-parseable YAML frontmatter, explicit context, and cross-references.

---

## Quick Start for AI Agents

**New to this codebase?** Follow this sequence:

1. **Read** `/docs/INDEX.md` - One-page index of all documentation
2. **Scan** `/docs/GLOSSARY.md` - Indonesian ↔ English business terms
3. **Understand** `/docs/01-architecture/system-overview.md` - High-level architecture
4. **Explore** `/docs/02-domain/` - Business domain documentation

**Need specific information?**

| Task | Document |
|------|----------|
| Understand overall system | `/docs/01-architecture/system-overview.md` |
| Implement sales feature | `/docs/02-domain/sales-cycle.md` |
| Implement manufacturing feature | `/docs/02-domain/manufacturing.md` |
| Find API endpoint | `/docs/04-api/endpoints.md` |
| Understand an entity | `/docs/05-entities/{entity}.md` |
| Learn business rule | `/docs/06-business-rules/` |
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
| API Routes | 418 |
| Eloquent Models | 70 |
| Services | 39 |
| Database Migrations | 101 |
| Tests | 950+ |

### Tech Stack

| Component | Technology |
|-----------|------------|
| Framework | Laravel 12 |
| PHP Version | 8.4 |
| Database | PostgreSQL |
| Authentication | Sanctum (token-based) |
| Frontend | Livewire + Volt + Tailwind v4 |
| Testing | Pest v4 |
| API Docs | Scramble (OpenAPI) |

### Accounting Standard

**SAK EMKM** (Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah) - Indonesian accounting standard for micro, small, and medium enterprises.

---

## Documentation Structure

```
docs/
├── README.md                    # You are here - AI agent entry point
├── INDEX.md                     # One-page quick reference
├── GLOSSARY.md                  # Indonesian ↔ English terms
│
├── 00-getting-started/          # Onboarding for new developers/agents
├── 01-architecture/             # System design (C4, components)
├── 02-domain/                   # Business domains (sales, manufacturing)
├── 03-workflows/                # Flowcharts and process flows
├── 04-api/                      # API conventions, endpoints
├── 05-entities/                 # Entity documentation (70 models)
├── 06-business-rules/           # Business logic rules
├── 07-code-patterns/            # Code conventions, patterns
├── 08-adr/                      # Architecture Decision Records (48)
├── 09-development/              # Setup, testing, debugging
└── 10-references/               # External references, domain knowledge
```

---

## Business Domains

Enter365 covers these business domains:

### Core Accounting
- **Chart of Accounts** - SAK EMKM compliant hierarchical structure
- **Journal Entries** - Double-entry bookkeeping with auto-reversals
- **Fiscal Periods** - Period open/close/lock for compliance
- **Multi-Currency** - IDR default with exchange rate tracking

### Sales & Receivables
- **Quotations** - Standard and multi-option (Budget/Standard/Premium)
- **Invoices** - Sales invoices with payment tracking
- **Delivery Orders** - Shipment documentation
- **Down Payments** - Prepayment tracking and application
- **Sales Returns** - Credit notes and returns processing

### Purchasing & Payables
- **Purchase Orders** - Procurement documentation
- **Goods Receipt Notes (GRN)** - Multi-step receiving workflow
- **Bills** - Vendor invoices
- **Purchase Returns** - Returns to suppliers

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
- **Projects** - Lifecycle tracking (draft → active → complete)
- **Cost Tracking** - Labor, material, overhead allocation
- **Revenue Recognition** - Project revenue tracking
- **Profitability Analysis** - Per-project margins

### Solar EPC (Killer Feature)
- **Solar Proposals** - Energy savings, ROI, ESG metrics
- **Indonesian Solar Data** - Location-specific irradiance
- **PLN Tariffs** - Indonesian electricity rates
- **Public Proposal Links** - Shareable proposal URLs

---

## Key Architectural Patterns

| Pattern | Implementation | Location |
|---------|---------------|----------|
| Service Layer | Business logic in services, not controllers | `/app/Services/Accounting/` |
| Form Requests | Validation in dedicated request classes | `/app/Http/Requests/` |
| API Resources | Response transformation | `/app/Http/Resources/` |
| Feature Flags | Module toggling via middleware | `/config/features.php` |
| Soft Deletes | Data retention on all models | All models |
| Transaction Wrapping | DB::transaction() in services | All services |

---

## ADR System

Architecture Decision Records document the "why" behind decisions.

**48 ADRs documented** covering:
- Technology choices (Laravel, PostgreSQL, Sanctum)
- Architectural patterns (service layer, feature flags)
- Domain decisions (SAK EMKM, BOM variants, MRP)
- Indonesian context (PPN, NPWP, fiscal year)

**ADR Template:** `/docs/08-adr/template.md`
**ADR Index:** `/docs/08-adr/README.md`

---

## Documentation Conventions

### YAML Frontmatter

Every document includes machine-parseable frontmatter:

```yaml
---
domain: sales-cycle
entities: [Quotation, Invoice, Payment]
services: [QuotationService, InvoiceService]
related_adrs: [0009, 0015]
tags: [sales, receivables]
---
```

### AI Agent Guidance

Every document includes a "Quick Reference" section:

```markdown
## AI Agent Quick Reference

**Use this document when:**
- Implementing sales-related features
- Debugging quotation → invoice flow

**Related documents:**
- `/docs/03-workflows/sales-workflow.md`
- `/docs/05-entities/quotation.md`
```

### Cross-References

Documents link to related content:
- ADRs link to related ADRs and modules
- Domain models link to workflows and entities
- Code patterns link to example files

### Code Examples

All code examples include file paths:

```php
// File: /app/Services/Accounting/QuotationService.php

public function createFromBom(array $data): Quotation
{
    return DB::transaction(function () use ($data) {
        // Implementation
    });
}
```

---

## File Locations Quick Reference

| Component | Path |
|-----------|------|
| Models | `/app/Models/Accounting/` (70 files) |
| Services | `/app/Services/Accounting/` (39 files) |
| Controllers | `/app/Http/Controllers/Api/V1/` (53 files) |
| Form Requests | `/app/Http/Requests/Api/V1/` (100+ files) |
| API Resources | `/app/Http/Resources/Api/V1/` (75+ files) |
| Migrations | `/database/migrations/` (101 files) |
| Tests | `/tests/Feature/`, `/tests/Unit/` |
| Config | `/config/accounting.php`, `/config/features.php` |
| Routes | `/routes/api.php` |

---

## Related Files Outside docs/

| File | Purpose |
|------|---------|
| `/CLAUDE.md` | AI agent instructions (project-specific) |
| `/api.json` | OpenAPI specification (Scramble-generated) |
| `/ideas/WORKFLOW.md` | Legacy workflow documentation |
| `/ideas/ABOUT_APPLICATION.md` | Project overview |
| `/ideas/CURRENT_STATE_APP.md` | Implementation status |

---

## Maintenance

**When to update documentation:**

| Event | Update |
|-------|--------|
| New architectural decision | Create ADR in `/docs/08-adr/` |
| New model added | Create entity doc in `/docs/05-entities/` |
| Business rule changed | Update `/docs/06-business-rules/` |
| New Indonesian term | Add to `/docs/GLOSSARY.md` |
| Workflow changed | Update `/docs/03-workflows/` |

---

## Getting Help

- **Feature implementation**: Start with domain docs (`/docs/02-domain/`)
- **Bug investigation**: Check entity docs (`/docs/05-entities/`)
- **"Why" questions**: Search ADRs (`/docs/08-adr/`)
- **Indonesian terms**: Check glossary (`/docs/GLOSSARY.md`)
- **Code patterns**: Review patterns (`/docs/07-code-patterns/`)
