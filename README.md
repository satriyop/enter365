# Enter365

Modern, comprehensive Indonesian SME ERP and Point-of-Sale (POS) system built with Laravel 12 and PostgreSQL, fully compliant with **SAK EMKM** (*Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah*).

---

## Key Modules

* **Accounting & Financial Reporting**:
  * Double-entry bookkeeping with strict balance sheet invariant guarantees.
  * Indonesian Standard Chart of Accounts (COA) with hierarchical classification.
  * Real-time financial reports: Neraca (Balance Sheet), Laba Rugi (Income Statement), Arus Kas (Cash Flow), Buku Besar (General Ledger), and Partner Ledger.
  * Bank reconciliation, multi-currency support, asset depreciation schedules, and fiscal lock dates.
* **Sales & Invoicing**:
  * Quotations (Penawaran) with multi-option variant comparison and PDF generation.
  * Sales Invoices (Faktur Penjualan), Delivery Orders (Surat Jalan), and Down Payment tracking.
  * Customer payment allocations, overdue dunning workflows, and sales returns.
* **Point of Sale (POS Till)**:
  * Fast counter sales designed for tablet touchscreens (Kopitiam / cafe & retail workflows).
  * Shift session lifecycle with blind cash-up counting, cash rounding, and selisih tracking.
  * Instant checkout with idempotency key protection, supporting Cash and QRIS tenders.
  * Dynamic pricing modes: inclusive PPN or add-on service charge + PBJT.
* **Purchasing & AP**:
  * Purchase Orders, Vendor Bills, and Goods Receipt Notes (GRN).
  * Landed cost allocation across receipt lines and purchase returns.
* **Inventory Management**:
  * Multi-warehouse stock tracking with reservation pipelines (Incoming, Outgoing, Free-to-use).
  * Inventory costing methods (FIFO and Weighted Average).
  * Stock opname with discrepancy review workflows and internal stock transfers.
* **Manufacturing & Projects**:
  * Multi-level Bill of Materials (BOM) with component variant swaps.
  * Work Orders, Material Requisitions, MRP shortage calculations, and Subcontracting.
  * Project timesheets, task tracking, and milestone budgeting.
* **Indonesian Tax Localization**:
  * Automatic PPN (VAT) and PPh withholding calculations.
  * Nomor Seri Faktur Pajak (NSFP) range tracking and e-Faktur export readiness.

---

## Tech Stack

* **Backend**: PHP 8.4+, [Laravel 12](https://laravel.com)
* **Database**: [PostgreSQL 16+](https://www.postgresql.org)
* **Auth**: Laravel Sanctum (Bearer Token / SPA session)
* **API Documentation**: [Dedoc Scramble](https://scramble.dedoc.co) (OpenAPI 3.1)
* **Testing**: [Pest v4](https://pestphp.com) (Feature, Unit, Browser, and PgSQL Concurrency Lock tests)
* **Frontend**: Vue.js, Tailwind CSS v4, Vite

---

## Getting Started

### Prerequisites

* PHP 8.4 or higher
* PostgreSQL 16 or higher
* Composer 2.x
* Node.js 20+ & npm

### Installation

1. **Clone the repository**:
   ```bash
   git clone git@github.com:satriyop/enter365.git
   cd enter365
   ```

2. **Install dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Update your database credentials in `.env`:
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=enter365
   DB_USERNAME=postgres
   DB_PASSWORD=your_password
   ```

4. **Run database migrations and seeders**:
   ```bash
   php artisan migrate --seed
   ```
   Or seed tailored presets:
   ```bash
   # Demo data for full ERP + POS:
   php artisan seed:demo
   ```

5. **Start development servers**:
   ```bash
   composer run dev
   ```

---

## Testing

Enter365 features extensive test coverage across business workflows, concurrency locks, and API contract schemas:

```bash
# Run feature and unit tests
php artisan test

# Run PostgreSQL pessimistic lock suite
composer run test:pgsql

# Check API contract schema compliance
php check-api-mismatches.php
```

---

## API Documentation

When the application is running locally in development, interactive API documentation is automatically accessible at:
```
http://localhost:8000/docs/api
```
Export OpenAPI spec:
```bash
php artisan scramble:export --path=api.json
```

---

## License

This project is licensed under the [MIT License](LICENSE).
