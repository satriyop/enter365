# Documentation Index

> **One-page quick reference for AI agents - all docs on one page**

---

## Entry Points

| Document | Purpose |
|----------|---------|
| `/docs/README.md` | AI agent entry point, system overview |
| `/docs/GLOSSARY.md` | Indonesian ↔ English business terms |
| `/CLAUDE.md` | Project-specific AI instructions |

---

## 00 - Getting Started

| Document | Purpose |
|----------|---------|
| `/docs/00-getting-started/README.md` | Onboarding guide |
| `/docs/00-getting-started/quick-reference.md` | Cheat sheet |
| `/docs/00-getting-started/project-overview.md` | What is Enter365 |
| `/docs/00-getting-started/codebase-navigation.md` | Where to find things |

---

## 01 - Architecture

| Document | Purpose |
|----------|---------|
| `/docs/01-architecture/README.md` | Architecture section overview |
| `/docs/01-architecture/system-overview.md` | C4 Context diagram |
| `/docs/01-architecture/components.md` | C4 Component diagram |
| `/docs/01-architecture/service-layer.md` | 39 services explained |
| `/docs/01-architecture/data-model.md` | 70 models overview |
| `/docs/01-architecture/api-design.md` | RESTful conventions |
| `/docs/01-architecture/feature-flags.md` | Module toggles |

---

## 02 - Domain Models

| Document | Purpose |
|----------|---------|
| `/docs/02-domain/README.md` | Domain section overview |
| `/docs/02-domain/accounting.md` | SAK EMKM, chart of accounts |
| `/docs/02-domain/sales-cycle.md` | Quotation → Invoice → Payment |
| `/docs/02-domain/purchasing-cycle.md` | PO → GRN → Bill → Payment |
| `/docs/02-domain/manufacturing.md` | BOM → Work Order → MRP |
| `/docs/02-domain/inventory.md` | Multi-warehouse stock |
| `/docs/02-domain/solar-epc.md` | Solar proposals (killer feature) |
| `/docs/02-domain/indonesian-context.md` | PPN, NPWP, SAK EMKM |

---

## 03 - Workflows

| Document | Purpose |
|----------|---------|
| `/docs/03-workflows/README.md` | Workflow section overview |
| `/docs/03-workflows/sales-workflow.md` | Complete sales cycle |
| `/docs/03-workflows/purchasing-workflow.md` | Complete purchasing cycle |
| `/docs/03-workflows/manufacturing-workflow.md` | Work order execution |
| `/docs/03-workflows/mrp-workflow.md` | MRP demand → suggestions |
| `/docs/03-workflows/bom-variants-workflow.md` | Multi-alternative BOM |

---

## 04 - API Reference

| Document | Purpose |
|----------|---------|
| `/docs/04-api/README.md` | API section overview |
| `/docs/04-api/conventions.md` | RESTful patterns |
| `/docs/04-api/authentication.md` | Sanctum tokens |
| `/docs/04-api/endpoints.md` | 418 routes grouped |
| `/docs/04-api/error-handling.md` | Error codes, formats |

**OpenAPI Spec:** `/api.json` (Scramble-generated)

---

## 05 - Entities (Data Dictionary)

| Document | Purpose |
|----------|---------|
| `/docs/05-entities/README.md` | Entity documentation index |
| `/docs/05-entities/account.md` | Chart of accounts |
| `/docs/05-entities/invoice.md` | Sales invoice |
| `/docs/05-entities/bill.md` | Purchase bill |
| `/docs/05-entities/quotation.md` | Quotation/penawaran |
| `/docs/05-entities/bom.md` | Bill of Materials |
| `/docs/05-entities/work-order.md` | Work order |
| `/docs/05-entities/product.md` | Product/service |
| `/docs/05-entities/contact.md` | Customer/supplier |
| `/docs/05-entities/mrp-run.md` | MRP execution |
| `/docs/05-entities/solar-proposal.md` | Solar proposal |
| ... | (70 total entity documents) |

---

## 06 - Business Rules

| Document | Purpose |
|----------|---------|
| `/docs/06-business-rules/README.md` | Business rules overview |
| `/docs/06-business-rules/accounting-rules.md` | Double-entry, SAK EMKM |
| `/docs/06-business-rules/tax-rules.md` | PPN 11%, tax calculations |
| `/docs/06-business-rules/document-numbering.md` | INV-YYYYMM-SEQ formats |
| `/docs/06-business-rules/approval-rules.md` | Approval workflows |
| `/docs/06-business-rules/credit-limits.md` | Customer credit management |

---

## 07 - Code Patterns

| Document | Purpose |
|----------|---------|
| `/docs/07-code-patterns/README.md` | Code patterns overview |
| `/docs/07-code-patterns/service-pattern.md` | Service class conventions |
| `/docs/07-code-patterns/controller-pattern.md` | Thin controller pattern |
| `/docs/07-code-patterns/model-pattern.md` | Eloquent model conventions |
| `/docs/07-code-patterns/validation-pattern.md` | Form Request classes |
| `/docs/07-code-patterns/testing-pattern.md` | Pest tests, factories |

---

## 08 - Architecture Decision Records (48 ADRs)

| Document | Purpose |
|----------|---------|
| `/docs/08-adr/README.md` | ADR index and overview |
| `/docs/08-adr/template.md` | ADR template |

### Critical ADRs (Technology)

| ADR | Title |
|-----|-------|
| `/docs/08-adr/0001-laravel-framework.md` | Laravel 12 choice |
| `/docs/08-adr/0002-postgresql-database.md` | PostgreSQL choice |
| `/docs/08-adr/0003-service-layer-pattern.md` | Service layer architecture |
| `/docs/08-adr/0004-sanctum-authentication.md` | API authentication |
| `/docs/08-adr/0005-single-accounting-namespace.md` | Model organization |
| `/docs/08-adr/0006-sak-emkm-compliance.md` | Accounting standard |
| `/docs/08-adr/0007-feature-flag-system.md` | Module toggles |
| `/docs/08-adr/0008-integer-currency-storage.md` | Currency precision |
| `/docs/08-adr/0009-bom-variant-groups.md` | Multi-brand BOMs |
| `/docs/08-adr/0010-configuration-driven-rules.md` | Config-based rules |

### Domain ADRs

| ADR | Title |
|-----|-------|
| `/docs/08-adr/0011-double-entry-bookkeeping.md` | Journal entry system |
| `/docs/08-adr/0012-chart-of-accounts-hierarchy.md` | Account structure |
| `/docs/08-adr/0013-multi-warehouse-inventory.md` | Stock tracking |
| `/docs/08-adr/0014-inventory-costing-methods.md` | FIFO/AVG/Standard |
| `/docs/08-adr/0015-multi-option-quotations.md` | Budget/Standard/Premium |
| `/docs/08-adr/0016-quotation-from-bom.md` | BOM-based pricing |
| `/docs/08-adr/0017-mrp-demand-calculation.md` | MRP algorithm |
| `/docs/08-adr/0018-subcontractor-retention.md` | 5% retention |
| `/docs/08-adr/0019-down-payment-application.md` | Prepayment tracking |
| `/docs/08-adr/0020-sales-purchase-returns.md` | Credit/debit notes |

### Indonesian Context ADRs

| ADR | Title |
|-----|-------|
| `/docs/08-adr/0026-ppn-vat-calculation.md` | 11% VAT |
| `/docs/08-adr/0027-indonesian-fiscal-year.md` | Jan-Dec fiscal year |
| `/docs/08-adr/0028-npwp-validation.md` | Tax ID format |
| `/docs/08-adr/0029-idr-default-currency.md` | Currency default |
| `/docs/08-adr/0030-document-number-format.md` | Numbering scheme |
| `/docs/08-adr/0031-indonesian-language.md` | Localization |
| `/docs/08-adr/0032-aging-report-buckets.md` | 0/1-30/31-60/61-90/>90 |
| `/docs/08-adr/0033-payment-terms.md` | Default 30 days |

### API Design ADRs

| ADR | Title |
|-----|-------|
| `/docs/08-adr/0034-api-versioning.md` | v1 versioning |
| `/docs/08-adr/0035-pagination-standard.md` | 25 items/page |
| `/docs/08-adr/0036-error-response-format.md` | JSON error format |
| `/docs/08-adr/0037-form-request-validation.md` | Validation classes |
| `/docs/08-adr/0038-api-resource-transformation.md` | Resource pattern |
| `/docs/08-adr/0039-eloquent-vs-query-builder.md` | When to use each |
| `/docs/08-adr/0040-soft-deletes.md` | Data retention |

### System Design ADRs

| ADR | Title |
|-----|-------|
| `/docs/08-adr/0041-audit-logging.md` | Audit trail |
| `/docs/08-adr/0042-attachment-storage.md` | Document storage |
| `/docs/08-adr/0043-rbac-system.md` | Roles/permissions |
| `/docs/08-adr/0044-fiscal-period-lock.md` | Period closing |
| `/docs/08-adr/0045-bank-reconciliation.md` | Auto-matching |
| `/docs/08-adr/0046-recurring-documents.md` | Auto-generation |
| `/docs/08-adr/0047-dashboard-kpis.md` | KPI calculation |
| `/docs/08-adr/0048-excel-export.md` | Report exports |

---

## 09 - Development

| Document | Purpose |
|----------|---------|
| `/docs/09-development/README.md` | Development guide overview |
| `/docs/09-development/setup.md` | Local development setup |
| `/docs/09-development/testing.md` | Running 950+ tests |
| `/docs/09-development/debugging.md` | Debugging techniques |

---

## 10 - References

| Document | Purpose |
|----------|---------|
| `/docs/10-references/README.md` | References overview |
| `/docs/10-references/indonesian-accounting.md` | SAK EMKM deep dive |
| `/docs/10-references/industry-knowledge.md` | Electrical/solar domains |

---

## File Locations Quick Reference

| Component | Path | Count |
|-----------|------|-------|
| Models | `/app/Models/Accounting/` | 70 |
| Services | `/app/Services/Accounting/` | 39 |
| Controllers | `/app/Http/Controllers/Api/V1/` | 53 |
| Form Requests | `/app/Http/Requests/Api/V1/` | 100+ |
| API Resources | `/app/Http/Resources/Api/V1/` | 75+ |
| Migrations | `/database/migrations/` | 101 |
| Config | `/config/accounting.php` | 1 (284 lines) |
| Features | `/config/features.php` | 1 (13 modules) |
| Routes | `/routes/api.php` | 418 routes |
| Tests | `/tests/` | 950+ |

---

## Search Shortcuts

**Find an entity:**
```
/docs/05-entities/{entity-name}.md
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
