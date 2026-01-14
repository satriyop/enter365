# Architecture Decision Records (ADR)

> **Documenting the "why" behind architectural decisions in Enter365**
>
> ADRs capture important architectural decisions along with their context and consequences. They serve as a historical record for understanding why the system is designed the way it is.

---

## AI Agent Quick Reference

**Use ADRs when:**
- Understanding why a particular approach was chosen
- Evaluating whether a pattern should be changed
- Onboarding to understand historical decisions
- Making new architectural decisions (create a new ADR)

**How to find relevant ADRs:**
- Search by tag (e.g., `api`, `database`, `domain`)
- Check related ADRs in document frontmatter
- Use the category index below

---

## ADR Index

### Status Summary

| Status | Count | Description |
|--------|-------|-------------|
| Accepted | 48 | Current active decisions |
| Proposed | 0 | Under discussion |
| Deprecated | 0 | No longer recommended |
| Superseded | 0 | Replaced by newer ADR |

---

## Critical ADRs (Technology Foundation)

These 10 ADRs form the foundation of Enter365's architecture. Read these first.

| # | ADR | Title | Impact | Tags |
|---|-----|-------|--------|------|
| 1 | [0001](./0001-laravel-framework.md) | Laravel 12 Framework | High | `framework` |
| 2 | [0002](./0002-postgresql-database.md) | PostgreSQL Database | High | `database`, `infrastructure` |
| 3 | [0003](./0003-service-layer-pattern.md) | Service Layer Pattern | High | `architecture`, `patterns` |
| 4 | [0004](./0004-sanctum-authentication.md) | Sanctum Token Authentication | High | `api`, `authentication` |
| 5 | [0005](./0005-single-accounting-namespace.md) | Single Accounting Namespace | High | `architecture`, `structure` |
| 6 | [0006](./0006-sak-emkm-compliance.md) | SAK EMKM Compliance | High | `domain`, `compliance` |
| 7 | [0007](./0007-feature-flag-system.md) | Feature Flag System | High | `architecture`, `modularity` |
| 8 | [0008](./0008-integer-currency-storage.md) | Integer Currency Storage | High | `data-model`, `accounting` |
| 9 | [0009](./0009-bom-variant-groups.md) | BOM Variant Groups | High | `domain`, `manufacturing` |
| 10 | [0010](./0010-configuration-driven-rules.md) | Configuration-Driven Business Rules | High | `architecture`, `configuration` |

---

## Domain Model ADRs

Decisions about business domain implementation.

| # | ADR | Title | Impact | Tags |
|---|-----|-------|--------|------|
| 11 | [0011](./0011-double-entry-bookkeeping.md) | Double-Entry Bookkeeping | High | `accounting`, `domain` |
| 12 | [0012](./0012-chart-of-accounts-hierarchy.md) | Chart of Accounts Hierarchy | High | `accounting`, `data-model` |
| 13 | [0013](./0013-multi-warehouse-inventory.md) | Multi-Warehouse Inventory | Medium | `inventory`, `domain` |
| 14 | [0014](./0014-inventory-costing-methods.md) | Inventory Costing (FIFO/AVG/Standard) | Medium | `inventory`, `accounting` |
| 15 | [0015](./0015-multi-option-quotations.md) | Multi-Option Quotations | Medium | `sales`, `domain` |
| 16 | [0016](./0016-quotation-from-bom.md) | Quotation from BOM | Medium | `sales`, `manufacturing` |
| 17 | [0017](./0017-mrp-demand-calculation.md) | MRP Demand Calculation | High | `manufacturing`, `mrp` |
| 18 | [0018](./0018-subcontractor-retention.md) | Subcontractor 5% Retention | Medium | `manufacturing`, `payables` |
| 19 | [0019](./0019-down-payment-application.md) | Down Payment Application | Medium | `receivables`, `domain` |
| 20 | [0020](./0020-sales-purchase-returns.md) | Sales/Purchase Returns Flow | Medium | `sales`, `purchasing` |
| 21 | [0021](./0021-grn-workflow.md) | GRN Multi-Step Workflow | Medium | `purchasing`, `inventory` |
| 22 | [0022](./0022-stock-opname-variance.md) | Stock Opname Variance Handling | Medium | `inventory`, `domain` |
| 23 | [0023](./0023-project-cost-allocation.md) | Project Cost Allocation | Medium | `projects`, `accounting` |
| 24 | [0024](./0024-material-consumption-tracking.md) | Material Consumption Tracking | Medium | `manufacturing`, `inventory` |
| 25 | [0025](./0025-solar-proposal-system.md) | Solar Proposal System | High | `solar`, `domain` |

---

## Indonesian Context ADRs

Decisions related to Indonesian business practices and compliance.

| # | ADR | Title | Impact | Tags |
|---|-----|-------|--------|------|
| 26 | [0026](./0026-ppn-vat-calculation.md) | PPN (VAT) 11% Calculation | High | `indonesian-context`, `tax` |
| 27 | [0027](./0027-indonesian-fiscal-year.md) | Indonesian Fiscal Year (Jan-Dec) | Medium | `indonesian-context`, `accounting` |
| 28 | [0028](./0028-npwp-validation.md) | NPWP Tax ID Validation | Medium | `indonesian-context`, `compliance` |
| 29 | [0029](./0029-idr-default-currency.md) | IDR as Default Currency | Medium | `indonesian-context`, `accounting` |
| 30 | [0030](./0030-document-number-format.md) | Document Number Format | Medium | `indonesian-context`, `domain` |
| 31 | [0031](./0031-indonesian-language.md) | Indonesian Language Support | Medium | `indonesian-context`, `localization` |
| 32 | [0032](./0032-aging-report-buckets.md) | Aging Report Buckets | Low | `indonesian-context`, `reporting` |
| 33 | [0033](./0033-payment-terms.md) | Default Payment Terms (30 days) | Low | `indonesian-context`, `domain` |

---

## API Design ADRs

Decisions about API architecture and conventions.

| # | ADR | Title | Impact | Tags |
|---|-----|-------|--------|------|
| 34 | [0034](./0034-api-versioning.md) | API v1 Versioning Strategy | High | `api`, `versioning` |
| 35 | [0035](./0035-pagination-standard.md) | Pagination Standard (25/page) | Low | `api`, `conventions` |
| 36 | [0036](./0036-error-response-format.md) | Error Response Format | Medium | `api`, `error-handling` |
| 37 | [0037](./0037-form-request-validation.md) | Form Request Validation | Medium | `api`, `validation` |
| 38 | [0038](./0038-api-resource-transformation.md) | API Resource Transformation | Medium | `api`, `patterns` |
| 39 | [0039](./0039-eloquent-vs-query-builder.md) | Eloquent vs Query Builder | Medium | `data-model`, `performance` |
| 40 | [0040](./0040-soft-deletes.md) | Soft Deletes for Data Retention | Medium | `data-model`, `compliance` |

---

## System Design ADRs

Decisions about system-level architecture and operations.

| # | ADR | Title | Impact | Tags |
|---|-----|-------|--------|------|
| 41 | [0041](./0041-audit-logging.md) | Audit Logging Strategy | Medium | `compliance`, `architecture` |
| 42 | [0042](./0042-attachment-storage.md) | Attachment Storage | Medium | `infrastructure`, `domain` |
| 43 | [0043](./0043-rbac-system.md) | Role-Based Access Control | High | `authentication`, `architecture` |
| 44 | [0044](./0044-fiscal-period-lock.md) | Fiscal Period Lock Mechanism | Medium | `accounting`, `compliance` |
| 45 | [0045](./0045-bank-reconciliation.md) | Bank Reconciliation Auto-Matching | Medium | `accounting`, `domain` |
| 46 | [0046](./0046-recurring-documents.md) | Recurring Document Generation | Low | `domain`, `automation` |
| 47 | [0047](./0047-dashboard-kpis.md) | Dashboard KPI Calculation | Low | `domain`, `reporting` |
| 48 | [0048](./0048-excel-export.md) | Excel Export Strategy | Low | `infrastructure`, `reporting` |

---

## ADRs by Tag

### Technology
- [0001](./0001-laravel-framework.md) Laravel 12 Framework
- [0002](./0002-postgresql-database.md) PostgreSQL Database
- [0004](./0004-sanctum-authentication.md) Sanctum Authentication

### Architecture
- [0003](./0003-service-layer-pattern.md) Service Layer Pattern
- [0005](./0005-single-accounting-namespace.md) Single Namespace
- [0007](./0007-feature-flag-system.md) Feature Flags
- [0010](./0010-configuration-driven-rules.md) Config-Driven Rules

### Domain (Accounting)
- [0006](./0006-sak-emkm-compliance.md) SAK EMKM Compliance
- [0008](./0008-integer-currency-storage.md) Integer Currency
- [0011](./0011-double-entry-bookkeeping.md) Double-Entry
- [0012](./0012-chart-of-accounts-hierarchy.md) Chart of Accounts

### Domain (Manufacturing)
- [0009](./0009-bom-variant-groups.md) BOM Variant Groups
- [0016](./0016-quotation-from-bom.md) Quotation from BOM
- [0017](./0017-mrp-demand-calculation.md) MRP Algorithm
- [0024](./0024-material-consumption-tracking.md) Material Consumption

### Domain (Solar)
- [0025](./0025-solar-proposal-system.md) Solar Proposals

### Indonesian Context
- [0026](./0026-ppn-vat-calculation.md) PPN/VAT
- [0027](./0027-indonesian-fiscal-year.md) Fiscal Year
- [0028](./0028-npwp-validation.md) NPWP
- [0030](./0030-document-number-format.md) Document Numbering
- [0031](./0031-indonesian-language.md) Indonesian Language

### API
- [0034](./0034-api-versioning.md) Versioning
- [0036](./0036-error-response-format.md) Error Format
- [0037](./0037-form-request-validation.md) Validation
- [0038](./0038-api-resource-transformation.md) Resources

---

## Creating New ADRs

1. Copy template from [`template.md`](./template.md)
2. Use next sequential number (currently: 0049)
3. Follow naming convention: `XXXX-short-kebab-case-title.md`
4. Fill in all sections (use short format for simple decisions)
5. Add to this index under appropriate category
6. Link related ADRs in frontmatter

---

## ADR Lifecycle

```
proposed → accepted → [deprecated or superseded]
```

**When to update status:**
- `proposed → accepted`: After team review/approval
- `accepted → deprecated`: When approach is no longer recommended
- `accepted → superseded`: When replaced by a new ADR

**When superseding:**
1. Create new ADR with updated decision
2. Update old ADR: `superseded_by: XXXX`
3. Update new ADR: `supersedes: XXXX`

---

## Related Documentation

- [`/docs/01-architecture/`](../01-architecture/) - Architecture overview
- [`/docs/07-code-patterns/`](../07-code-patterns/) - Implementation patterns
- [`/docs/GLOSSARY.md`](../GLOSSARY.md) - Indonesian terms
- [`/CLAUDE.md`](/CLAUDE.md) - AI agent instructions
