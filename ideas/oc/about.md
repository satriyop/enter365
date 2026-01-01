# Enter365 - Indonesian SME Accounting & ERP System
## Application Overview
Enter365 is a comprehensive RESTful API-based accounting and ERP system built specifically for Indonesian SMEs in electrical panel manufacturing and solar EPC contracting.
## Tech Stack
- Backend: Laravel 12 + PHP 8.4 + PostgreSQL
- API: RESTful v1 with 418 endpoints
- Auth: Sanctum token-based
- Frontend: Livewire 3 + Volt + Tailwind CSS v4
- Testing: Pest 4 (~950+ tests)
## Target Customers
1. Electrical Panel Manufacturers - Custom panel builders needing multiple alternative BOMs from brands like ABB, Siemens, Schneider
2. Solar EPC Contractors - Solar installation project companies requiring proposal calculations with environmental metrics
## Core Modules
1. 📊 Accounting (SAK EMKM Compliant)
- Chart of Accounts with hierarchical structure
- Journal Entries with double-entry bookkeeping
- Fiscal Periods (open, close, lock, reopen)
- Multi-currency support with exchange rates
- Budgeting with variance analysis
- Tax reports (PPN, input tax)
2. 💰 Sales & Receivables
- Quotations with multi-variant options (Budget/Standard/Premium)
- Invoice creation from BOM or quotations
- Down Payments with application tracking
- Delivery Orders with shipping workflow
- Sales Returns with approval flow
- Quotation follow-up & sales pipeline management
- AR Aging reports
🛒 Purchasing & Payables
- Purchase Orders with approval workflow
- Goods Receipt Notes (GRN) with receiving
- Bills creation from POs
- Purchase Returns
- AP Aging reports
3. 📦 Inventory Management
- Products with hierarchical categories
- Multi-warehouse stock tracking
- Stock movements (in, out, transfer, adjust)
- Stock Opname (physical inventory counting)
- Stock valuation (FIFO/AVG/Standard)
4. 🏭 Manufacturing (MRP)
- Bills of Material with alternatives
- BOM Variant Groups with side-by-side cost comparison
- Component Cross-Reference - Brand equivalents (ABB ↔ Siemens ↔ Schneider)
- BOM Templates for reusable panel configurations
- Work Orders with material consumption
- Material Requisitions with approval
- MRP Runs with demand forecasting
- Subcontractor Work Orders with retention tracking
5. ☀️ Solar Proposals (Killer Feature)
- Energy savings calculations
- ESG metrics (CO2 reduction, trees saved)
- ROI & financial analysis
- Environmental impact tracking
- PLN tariff integration (Indonesian utility)
- Public-facing customer portal
6. 📈 Project Costing
- Project lifecycle (draft → active → complete)
- Cost tracking (material, labor, overhead, subcontractor)
- Revenue recognition
- Profitability analysis per project
7. 📑 Financial Reports
- Balance Sheet, Income Statement, Trial Balance
- Cash Flow Statement
- COGS Reports (summary, by product/category, trends)
- Bank Reconciliation with auto-matching
- Tax reports (PPN Keluaran/Masukan)
8. 👥 System Features
- Authentication & RBAC (roles, permissions)
- Users management with role assignment
- Attachments (multi-type document storage)
- Audit logging
- Dashboard APIs (KPIs, cash flow, receivables/payables)
- Data Export (Excel format)

## Database
79 tables including accounts, invoices, bills, payments, products, warehouses, work_orders, projects, mrp_runs, stock_opnames, bom_variant_groups, quotation_variant_options, and more.
## Pending Features (High Priority)
- Email Notifications for payment reminders
- Bank Statement Import (CSV/OFX)
- Solar Proposal Generator with ESG Metrics enhancement
## Design Philosophy
- Value-First: Dashboards show actionable metrics
- Project-Centric: All elements connect back to projects
- Action-Oriented: Alerts include direct action buttons
- Progressive Disclosure: Summary first, details on demand
- Indonesian Context: Rupiah (Rp) currency, PPN 11% tax, DD MMM YYYY dates