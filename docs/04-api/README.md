# API Reference

> **Complete API documentation for Enter365 ERP**
>
> Base URL: `/api/v1/`
> Authentication: Laravel Sanctum (Bearer Token)
> Total Endpoints: **513 routes**

---

## Quick Start

### Authentication

All API requests (except `/auth/login` and `/public/*`) require authentication.

```bash
# Login to get token
POST /api/v1/auth/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}

# Response
{
  "user": { ... },
  "token": "1|abc123...",
  "token_type": "Bearer"
}
```

**Using the token:**

```bash
GET /api/v1/invoices
Authorization: Bearer 1|abc123...
```

### Response Format

**Success Response:**
```json
{
  "data": { ... },
  "message": "Success"
}
```

**Paginated Response:**
```json
{
  "data": [ ... ],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 25,
    "to": 25,
    "total": 250
  }
}
```

**Error Response:**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### Common Query Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `page` | int | Page number (default: 1) |
| `per_page` | int | Items per page (default: 25, max: 100) |
| `search` | string | Search across searchable fields |
| `sort` | string | Sort field (prefix with `-` for desc) |
| `filter[field]` | mixed | Filter by specific field |

---

## API Modules

### Core / Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/login` | Login and get token |
| POST | `/auth/logout` | Logout current session |
| POST | `/auth/logout-all` | Logout all sessions |
| GET | `/auth/me` | Get current user info |
| POST | `/auth/refresh` | Refresh token |

### Accounting

| Resource | Endpoints | Description |
|----------|-----------|-------------|
| [Accounts](#accounts) | 7 | Chart of Accounts (COA) |
| [Journal Entries](#journal-entries) | 5 | Manual journal entries |
| [Fiscal Periods](#fiscal-periods) | 8 | Fiscal year management |
| [Budgets](#budgets) | 15 | Budget planning |

### Sales

| Resource | Endpoints | Description |
|----------|-----------|-------------|
| [Quotations](#quotations) | 20+ | Sales quotations with variants |
| [Invoices](#invoices) | 12 | Sales invoices |
| [Delivery Orders](#delivery-orders) | 12 | Shipment tracking |
| [Down Payments](#down-payments) | 12 | Prepayment management |
| [Sales Returns](#sales-returns) | 10 | Return processing |
| [Payments](#payments) | 4 | Payment recording |

### Purchasing

| Resource | Endpoints | Description |
|----------|-----------|-------------|
| [Purchase Orders](#purchase-orders) | 15 | PO management |
| [Goods Receipt Notes](#goods-receipt-notes) | 10 | Receiving workflow |
| [Bills](#bills) | 10 | Vendor invoices |
| [Purchase Returns](#purchase-returns) | 10 | Return processing |

### Inventory

| Resource | Endpoints | Description |
|----------|-----------|-------------|
| [Products](#products) | 12 | Product catalog |
| [Warehouses](#warehouses) | 7 | Multi-warehouse |
| [Inventory](#inventory) | 10 | Stock movements |
| [Stock Opname](#stock-opname) | 15 | Physical counting |

### Manufacturing

| Resource | Endpoints | Description |
|----------|-----------|-------------|
| [BOMs](#boms) | 15+ | Bill of Materials |
| [BOM Templates](#bom-templates) | 15 | Reusable BOM templates |
| [BOM Variant Groups](#bom-variant-groups) | 10 | Multi-brand alternatives |
| [Work Orders](#work-orders) | 15 | Production orders |
| [Material Requisitions](#material-requisitions) | 8 | Material requests |
| [MRP](#mrp) | 15 | Material planning |
| [Subcontractor WO](#subcontractor-work-orders) | 15 | Outsourced work |

### Reports

| Endpoint | Description |
|----------|-------------|
| `/reports/balance-sheet` | Balance Sheet |
| `/reports/income-statement` | Profit & Loss |
| `/reports/cash-flow` | Cash Flow Statement |
| `/reports/trial-balance` | Trial Balance |
| `/reports/general-ledger` | General Ledger |
| `/reports/receivable-aging` | AR Aging |
| `/reports/payable-aging` | AP Aging |
| `/reports/contacts/{id}/aging` | Customer/Vendor Statement |
| `/reports/ppn-summary` | VAT Summary (PPN) |
| `/reports/cogs-summary` | Cost of Goods Sold |
| `/reports/project-profitability` | Project P&L |
| `/reports/work-order-costs` | Manufacturing Costs |

---

## Detailed Endpoints

### Accounts

Chart of Accounts management following Indonesian SAK EMKM standards.

```
GET    /accounts              # List accounts (tree structure)
POST   /accounts              # Create account
GET    /accounts/{id}         # Get account details
PUT    /accounts/{id}         # Update account
DELETE /accounts/{id}         # Delete account (if no transactions)
GET    /accounts/{id}/balance # Get account balance as of date
GET    /accounts/{id}/ledger  # Get ledger entries
```

**Account Types:**
- `asset` (1xxx) - Aktiva
- `liability` (2xxx) - Kewajiban
- `equity` (3xxx) - Modal
- `revenue` (4xxx) - Pendapatan
- `expense` (5xxx) - Beban

### Journal Entries

Manual journal entry creation with double-entry validation.

```
GET    /journal-entries              # List entries
POST   /journal-entries              # Create entry (draft)
GET    /journal-entries/{id}         # Get entry details
POST   /journal-entries/{id}/post    # Post entry (make permanent)
POST   /journal-entries/{id}/reverse # Reverse posted entry
```

**Create Entry Request:**
```json
{
  "entry_date": "2026-01-15",
  "description": "Manual adjustment",
  "reference": "ADJ-001",
  "lines": [
    { "account_id": 1, "debit": 1000000, "credit": 0, "description": "Debit line" },
    { "account_id": 2, "debit": 0, "credit": 1000000, "description": "Credit line" }
  ]
}
```

### Fiscal Periods

Fiscal year lifecycle management with year-end close workflow.

```
GET    /fiscal-periods                        # List periods
POST   /fiscal-periods                        # Create period
GET    /fiscal-periods/{id}                   # Get period details
POST   /fiscal-periods/{id}/lock              # Lock period (no new transactions)
POST   /fiscal-periods/{id}/unlock            # Unlock period
GET    /fiscal-periods/{id}/closing-checklist # Pre-close validation
POST   /fiscal-periods/{id}/close             # Execute year-end close
POST   /fiscal-periods/{id}/reopen            # Reopen closed period
```

**Status Flow:**
```
Open → Locked → Closing → Closed
  ↑       ↓        ↓         │
  └───────┴────────┴─────────┘ (reopen)
```

**Closing Checklist Response:**
```json
{
  "can_close": true,
  "items": {
    "unposted_journals": { "status": "ok", "count": 0, "message": "All journals posted" },
    "trial_balance": { "status": "ok", "count": 0, "message": "Trial balance is balanced" },
    "required_accounts": { "status": "ok", "count": 0, "message": "All required accounts exist" }
  },
  "summary": "Siap untuk ditutup"
}
```

### Quotations

Sales quotations with multi-option variants (Budget/Standard/Premium).

```
GET    /quotations                              # List quotations
POST   /quotations                              # Create quotation
POST   /quotations/from-bom                     # Create from BOM
GET    /quotations/{id}                         # Get details
PUT    /quotations/{id}                         # Update quotation
DELETE /quotations/{id}                         # Delete draft
POST   /quotations/{id}/submit                  # Submit for approval
POST   /quotations/{id}/approve                 # Approve quotation
POST   /quotations/{id}/reject                  # Reject quotation
POST   /quotations/{id}/revise                  # Create revision
POST   /quotations/{id}/convert-to-invoice      # Convert to invoice
POST   /quotations/{id}/duplicate               # Duplicate quotation
GET    /quotations/{id}/variant-options         # Get variant options
PUT    /quotations/{id}/variant-options         # Update variants
GET    /quotations/{id}/variant-comparison      # Compare variants
POST   /quotations/{id}/select-variant          # Select winning variant
POST   /quotations/{id}/mark-won                # Mark as won
POST   /quotations/{id}/mark-lost               # Mark as lost
GET    /quotations/{id}/pdf                     # Download PDF
```

### Invoices

Sales invoices with automatic journal entry creation.

```
GET    /invoices                           # List invoices
POST   /invoices                           # Create invoice
GET    /invoices/{id}                      # Get details
PUT    /invoices/{id}                      # Update draft invoice
DELETE /invoices/{id}                      # Delete draft
POST   /invoices/{id}/post                 # Post invoice (creates journal)
POST   /invoices/{id}/create-delivery-order # Create DO from invoice
POST   /invoices/{id}/create-sales-return  # Create return
POST   /invoices/{id}/make-recurring       # Setup recurring
GET    /invoices/{id}/delivery-orders      # List related DOs
GET    /invoices/{id}/sales-returns        # List related returns
```

### Purchase Orders

Purchase order workflow with approval and receiving.

```
GET    /purchase-orders                    # List POs
POST   /purchase-orders                    # Create PO
GET    /purchase-orders/{id}               # Get details
PUT    /purchase-orders/{id}               # Update draft PO
DELETE /purchase-orders/{id}               # Delete draft
POST   /purchase-orders/{id}/submit        # Submit for approval
POST   /purchase-orders/{id}/approve       # Approve PO
POST   /purchase-orders/{id}/reject        # Reject PO
POST   /purchase-orders/{id}/cancel        # Cancel PO
POST   /purchase-orders/{id}/receive       # Quick receive all
POST   /purchase-orders/{id}/convert-to-bill # Convert to bill
POST   /purchase-orders/{id}/create-grn    # Create GRN
POST   /purchase-orders/{id}/duplicate     # Duplicate PO
GET    /purchase-orders/{id}/goods-receipt-notes # List GRNs
GET    /purchase-orders-outstanding        # Outstanding POs
GET    /purchase-orders-statistics         # PO statistics
```

### Work Orders

Manufacturing work order execution.

```
GET    /work-orders                          # List work orders
POST   /work-orders                          # Create work order
GET    /work-orders/{id}                     # Get details
PUT    /work-orders/{id}                     # Update work order
DELETE /work-orders/{id}                     # Delete draft
POST   /work-orders/{id}/confirm             # Confirm work order
POST   /work-orders/{id}/start               # Start production
POST   /work-orders/{id}/complete            # Complete production
POST   /work-orders/{id}/cancel              # Cancel work order
POST   /work-orders/{id}/record-consumption  # Record material usage
POST   /work-orders/{id}/record-output       # Record finished goods
POST   /work-orders/{id}/material-requisitions # Create MR
GET    /work-orders/{id}/material-status     # Material availability
GET    /work-orders/{id}/cost-summary        # Production costs
GET    /work-orders/{id}/sub-work-orders     # Sub-work orders
POST   /work-orders/{id}/sub-work-orders     # Create sub-WO
GET    /work-orders-statistics               # WO statistics
```

---

## OpenAPI Specification

Full OpenAPI 3.0 specification is available at:

- **JSON:** `/api.json` (generated by Scramble)
- **UI:** `/docs/api` (Swagger UI)

Generate fresh spec:
```bash
php artisan scramble:export --path=api.json
```

---

## Rate Limiting

Default rate limits (configurable):

| Route Group | Limit |
|-------------|-------|
| API (authenticated) | 60 requests/minute |
| Auth endpoints | 5 requests/minute |
| Public endpoints | 30 requests/minute |

---

## Error Codes

| HTTP Code | Meaning |
|-----------|---------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (successful delete) |
| 400 | Bad Request (validation failed) |
| 401 | Unauthorized (invalid/missing token) |
| 403 | Forbidden (insufficient permissions) |
| 404 | Not Found |
| 422 | Unprocessable Entity (business rule violation) |
| 429 | Too Many Requests (rate limited) |
| 500 | Internal Server Error |

---

## Related Documentation

- [Architecture Overview](/docs/01-architecture/README.md)
- [API Design Decisions](/docs/01-architecture/api-design.md)
- [Authentication ADR](/docs/08-adr/0004-sanctum-authentication.md)
- [Error Response Format ADR](/docs/08-adr/0036-error-response-format.md)
