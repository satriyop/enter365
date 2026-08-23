# Indonesian ↔ English Business Glossary

> **Essential reference for understanding Indonesian business terms used in Enter365**
>
> This glossary covers accounting, tax, manufacturing, and general business terms specific to Indonesian SME operations.

---

## Quick Reference by Category

- [Accounting Terms](#accounting-terms)
- [Tax & Compliance](#tax--compliance)
- [Sales & Receivables](#sales--receivables)
- [Point of Sale (kasir)](#point-of-sale-kasir)
- [Purchasing & Payables](#purchasing--payables)
- [Inventory & Warehousing](#inventory--warehousing)
- [Manufacturing](#manufacturing)
- [Projects & Costing](#projects--costing)
- [Solar EPC](#solar-epc)
- [Document Types](#document-types)
- [Status Terms](#status-terms)
- [Regulatory Bodies](#regulatory-bodies)

---

## Accounting Terms

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Akun** | Account | Chart of accounts entry | `Account` model |
| **Buku Besar** | General Ledger | Main accounting record | `journal_entries` table |
| **Jurnal** | Journal Entry | Double-entry transaction | `JournalEntry` model |
| **Debit** | Debit | Left side of entry | `debit` column |
| **Kredit** | Credit | Right side of entry | `credit` column |
| **Saldo** | Balance | Account balance | `getBalance()` |
| **Neraca** | Balance Sheet | Financial position statement | `FinancialReportService` |
| **Laba Rugi** | Income Statement | Profit/loss statement | `FinancialReportService` |
| **Arus Kas** | Cash Flow | Cash flow statement | `CashFlowReportService` |
| **Periode Fiskal** | Fiscal Period | Accounting period | `FiscalPeriod` model |
| **Tahun Buku** | Fiscal Year | Accounting year (Jan-Dec) | `fiscal_periods` table |
| **Tutup Buku** | Period Closing | Close accounting period | `FiscalPeriodService::close()` |
| **Modal** | Equity/Capital | Owner's equity | `Account::TYPE_EQUITY` |
| **Aset** | Asset | Company assets | `Account::TYPE_ASSET` |
| **Kewajiban** | Liability | Company liabilities | `Account::TYPE_LIABILITY` |
| **Pendapatan** | Revenue | Income/sales | `Account::TYPE_REVENUE` |
| **Beban** | Expense | Costs/expenses | `Account::TYPE_EXPENSE` |
| **Harga Pokok Penjualan (HPP)** | Cost of Goods Sold (COGS) | Direct costs | `COGSReportService` |
| **Laba Kotor** | Gross Profit | Revenue - COGS | Financial reports |
| **Laba Bersih** | Net Profit | After all expenses | Financial reports |
| **Mata Uang** | Currency | Currency code | `currencies` table |
| **Kurs** | Exchange Rate | Currency rate | `exchange_rates` table |

---

## Tax & Compliance

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **PPN** | VAT (Value Added Tax) | 11% sales tax | `tax_rate: 11` |
| **PPh** | Income Tax | Various income taxes | Tax calculations |
| **Pajak Masukan** | Input Tax | Tax paid on purchases | Tax reports |
| **Pajak Keluaran** | Output Tax | Tax collected on sales | Tax reports |
| **Faktur Pajak** | Tax Invoice | Official tax document | Tax invoice list |
| **NPWP** | Tax ID Number | Nomor Pokok Wajib Pajak | `contact.npwp` |
| **NIK** | National ID | Nomor Induk Kependudukan | `contact.nik` |
| **SAK EMKM** | SME Accounting Standard | Indonesian GAAP for SMEs | Core compliance |
| **DJP** | Tax Authority | Direktorat Jenderal Pajak | Regulatory |
| **SPT** | Tax Return | Surat Pemberitahuan | Tax filing |
| **PKP** | Taxable Entrepreneur | Pengusaha Kena Pajak | VAT registered |

### SAK EMKM (Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah)

Indonesian Financial Accounting Standard for Micro, Small, and Medium Entities. Key requirements:

- **Accrual basis** accounting (not cash basis)
- **Double-entry** bookkeeping
- **Standard chart of accounts** structure
- **Required reports**: Balance Sheet, Income Statement
- **Fiscal year**: Calendar year (January - December)

---

## Sales & Receivables

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Penawaran** | Quotation | Price quote to customer | `Quotation` model |
| **Faktur** | Invoice | Sales invoice | `Invoice` model |
| **Faktur Penjualan** | Sales Invoice | Same as Faktur | `Invoice` model |
| **Nota Kredit** | Credit Note | Sales return credit | `SalesReturn` model |
| **Piutang** | Receivable | Money owed to us | `receivable_account_id` |
| **Piutang Usaha** | Accounts Receivable (AR) | Trade receivables | Aging reports |
| **Uang Muka (UM)** | Down Payment | Prepayment received | `DownPayment` model |
| **Pelanggan** | Customer | Buyer | `Contact` (type: customer) |
| **Surat Jalan** | Delivery Order | Shipping document | `DeliveryOrder` model |
| **Retur Penjualan** | Sales Return | Returned goods | `SalesReturn` model |
| **Diskon** | Discount | Price reduction | `discount_percent` |
| **Jatuh Tempo** | Due Date | Payment deadline | `due_date` |
| **Lewat Jatuh Tempo** | Overdue | Past due date | `isOverdue()` |
| **Umur Piutang** | Aging | Days since invoice | `AgingReportService` |
| **Syarat Pembayaran** | Payment Terms | e.g., Net 30 | `payment_terms` |
| **Termin** | Payment Terms | Same as above | Config setting |

---

## Point of Sale (kasir)

Counter sales are **not** Faktur. See `CONTEXT.md` and ADR-0051.

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Penjualan Kasir** | POS Sale | Completed counter sale, paid at the till; tracked lines post HPP | `App\Models\Pos\PosSale` `POS-YYYYMM-NNNN` |
| **Sesi Kasir** | POS Session | One kasir, one till, `open`/`closed` | `App\Models\Pos\PosSession` `PSS-YYYYMM-NNNN` |
| **Tender Kasir** | Tender | Applied to payable (cash/QRIS); not cash handed over | `pos_sale_tenders` (not `Payment`) |
| **Kembalian** | Change | Cash received − cash tender; not a tender line | `pos_sales.change_amount` |
| **Selisih Kas** | Cash difference | Counted − expected at tutup kasir; does not block close | `pos_sessions.cash_difference_amount` |
| **Diskon Kasir** | POS discount | Header cut off inclusive payable; PPN after, per line | Not on V1 till (no Diskon control / column) |
| **Batal** | Void | Whole POS Sale reversed while session open | `PosService::voidSale()` while session `open` |
| **Keranjang Ditahan** | Held cart | Parked basket; not a document | `pos_session_holds` (max 5) |
| **Paket POS** | POS pack | Optional pack; preset `pos` + `full` on; not an add-on | `FEATURE_POS` / `FEATURE_PRESET=pos` |
| **Kasir** | Cashier (role) | Till operator: own session + checkout/void, not Faktur | `Role::CASHIER`; `pos.*` (ADR-0061) |

_Avoid for kasir:_ Faktur, Invoice, Sales Order, dummy Pelanggan “UMUM”, treating checkout Tender as Pembayaran (AR collection), pay-later at the till, Retur Penjualan as kasir void.

---

## Purchasing & Payables

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Pesanan Pembelian (PO)** | Purchase Order | Order to supplier | `PurchaseOrder` model |
| **Tagihan** | Bill | Vendor invoice | `Bill` model |
| **Hutang** | Payable | Money we owe | `payable_account_id` |
| **Hutang Usaha** | Accounts Payable (AP) | Trade payables | Aging reports |
| **Pemasok** | Supplier | Vendor | `Contact` (type: supplier) |
| **Vendor** | Vendor | Same as Pemasok | `Contact` model |
| **Penerimaan Barang** | Goods Receipt | Receiving goods | `GoodsReceiptNote` model |
| **Retur Pembelian** | Purchase Return | Return to supplier | `PurchaseReturn` model |
| **Nota Debit** | Debit Note | Purchase return debit | `PurchaseReturn` model |

---

## Inventory & Warehousing

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Persediaan** | Inventory | Stock items | `Product` model |
| **Stok** | Stock | Quantity on hand | `ProductStock` model |
| **Gudang** | Warehouse | Storage location | `Warehouse` model |
| **Barang** | Goods/Product | Physical product | `Product::TYPE_PRODUCT` |
| **Jasa** | Service | Service item | `Product::TYPE_SERVICE` |
| **SKU** | SKU | Stock Keeping Unit | `product.sku` |
| **Satuan** | Unit | Unit of measure | `unit` field |
| **Mutasi Stok** | Stock Movement | Inventory transaction | `InventoryMovement` |
| **Stock Opname** | Physical Count | Inventory counting | `StockOpname` model |
| **Selisih Stok** | Stock Variance | Count difference | Stock opname |
| **Stok Minimum** | Minimum Stock | Reorder point | `min_stock` |
| **Stok Maksimum** | Maximum Stock | Max stock level | `max_stock` |
| **Safety Stock** | Safety Stock | Buffer stock | `safety_stock` |
| **Lead Time** | Lead Time | Procurement time | `lead_time_days` |
| **FIFO** | FIFO | First In First Out | Costing method |
| **Rata-rata** | Average | Weighted average cost | Costing method |

---

## Manufacturing

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **BOM** | Bill of Materials | Product recipe | `Bom` model |
| **Perintah Kerja** | Work Order | Production order | `WorkOrder` model |
| **Perintah Produksi** | Production Order | Same as above | `WorkOrder` model |
| **Material** | Material | Raw materials | `BomItem::TYPE_MATERIAL` |
| **Tenaga Kerja** | Labor | Labor cost | `BomItem::TYPE_LABOR` |
| **Overhead** | Overhead | Indirect costs | `BomItem::TYPE_OVERHEAD` |
| **Permintaan Material** | Material Requisition | Request materials | `MaterialRequisition` |
| **Konsumsi Material** | Material Consumption | Actual usage | `MaterialConsumption` |
| **MRP** | MRP | Material Requirements Planning | `MrpRun` model |
| **Saran Pengadaan** | Procurement Suggestion | MRP recommendation | `MrpSuggestion` |
| **Subkontraktor** | Subcontractor | Outsourced work | `is_subcontractor` |
| **Retensi** | Retention | Withheld amount (5%) | `retention_percent` |
| **Waste/Sisa** | Waste | Material waste | `waste_percentage` |

---

## Projects & Costing

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **Proyek** | Project | Work project | `Project` model |
| **Biaya Proyek** | Project Cost | Project expenses | `ProjectCost` model |
| **Pendapatan Proyek** | Project Revenue | Project income | `ProjectRevenue` model |
| **Profitabilitas** | Profitability | Profit margin | Calculations |
| **Margin** | Margin | Profit percentage | `margin_percent` |

---

## Solar EPC

| Indonesian | English | Description | Code Usage |
|------------|---------|-------------|------------|
| **PLTS** | Solar PV System | Pembangkit Listrik Tenaga Surya | Solar module |
| **Panel Surya** | Solar Panel | PV panel | Solar calculations |
| **Inverter** | Inverter | DC to AC converter | Solar calculations |
| **kWp** | Peak Kilowatt | Panel capacity | `capacity_kwp` |
| **kWh** | Kilowatt Hour | Energy unit | Energy calculations |
| **Iradiasi** | Irradiance | Solar radiation | `indonesia_solar_data` |
| **PLN** | State Electric Company | Electricity provider | `pln_tariffs` table |
| **Tarif Listrik** | Electricity Rate | PLN rate/kWh | `pln_tariffs.rate` |
| **Penghematan** | Savings | Energy/cost savings | Proposal calculations |
| **ROI** | Return on Investment | Investment return | Proposal calculations |
| **Payback Period** | Payback Period | Break-even time | Proposal calculations |
| **ESG** | ESG | Environmental, Social, Governance | Proposal metrics |
| **CO2** | Carbon Dioxide | Emissions reduction | ESG metrics |

---

## Document Types

| Indonesian | English | Code/Number Format |
|------------|---------|-------------------|
| Penawaran | Quotation | `QUO-YYYYMM-XXXX` |
| Faktur | Invoice | `INV-YYYYMM-XXXX` |
| Tagihan | Bill | `BILL-YYYYMM-XXXX` |
| Pesanan Pembelian | Purchase Order | `PO-YYYYMM-XXXX` |
| Surat Jalan | Delivery Order | `DO-YYYYMM-XXXX` |
| Perintah Kerja | Work Order | `WO-YYYYMM-XXXX` |
| Penerimaan | Goods Receipt | `GRN-YYYYMM-XXXX` |
| Pembayaran | Payment | `RCV-YYYYMM-XXXX` (receive) |
| Pembayaran | Payment | `PAY-YYYYMM-XXXX` (send) |
| Jurnal | Journal Entry | `JE-YYYYMM-XXXX` |
| MRP Run | MRP Run | `MRP-YYYYMM-XXXX` |
| Penjualan Kasir | POS Sale | `POS-YYYYMM-NNNN` |
| Sesi Kasir | POS Session | `PSS-YYYYMM-NNNN` |

---

## Status Terms

| Indonesian | English | Context |
|------------|---------|---------|
| **Draft** | Draft | Initial/editable state |
| **Dikirim** | Sent | Sent to customer/supplier |
| **Disetujui** | Approved | Approved by authority |
| **Ditolak** | Rejected | Rejected/declined |
| **Dibatalkan** | Cancelled | Cancelled/voided |
| **Lunas** | Paid | Fully paid |
| **Sebagian** | Partial | Partially paid |
| **Selesai** | Completed | Finished/done |
| **Aktif** | Active | Currently active |
| **Tidak Aktif** | Inactive | Disabled |
| **Menang** | Won | Quotation won |
| **Kalah** | Lost | Quotation lost |
| **Kadaluarsa** | Expired | Past validity date |

---

## Regulatory Bodies

| Abbreviation | Full Name (Indonesian) | English | Purpose |
|--------------|----------------------|---------|---------|
| **DJP** | Direktorat Jenderal Pajak | Tax Authority | Tax administration |
| **OJK** | Otoritas Jasa Keuangan | Financial Services Authority | Financial regulation |
| **IAI** | Ikatan Akuntan Indonesia | Indonesian Accountants Association | Accounting standards |
| **PLN** | Perusahaan Listrik Negara | State Electricity Company | Power utility |

---

## Common Abbreviations in Code

| Abbreviation | Full Form | Usage |
|--------------|-----------|-------|
| `AR` | Accounts Receivable | Piutang |
| `AP` | Accounts Payable | Hutang |
| `PO` | Purchase Order | Pesanan Pembelian |
| `DO` | Delivery Order | Surat Jalan |
| `GRN` | Goods Receipt Note | Penerimaan Barang |
| `WO` | Work Order | Perintah Kerja |
| `BOM` | Bill of Materials | - |
| `MRP` | Material Requirements Planning | - |
| `COGS` | Cost of Goods Sold | HPP |
| `VAT` | Value Added Tax | PPN |
| `SKU` | Stock Keeping Unit | - |
| `UM` | Uang Muka | Down Payment |

---

## Usage in Error Messages

The application uses Indonesian error messages. Common patterns:

```php
// Validation errors
'contact_id.required' => 'Pelanggan wajib dipilih.'  // Customer is required
'items.required' => 'Minimal satu item harus diisi.' // At least one item required
'due_date.after' => 'Tanggal jatuh tempo harus setelah tanggal faktur.' // Due date must be after invoice date

// Status errors
'Penawaran tidak dapat disetujui.' // Quotation cannot be approved
'Faktur sudah lunas.' // Invoice already paid
'Stok tidak mencukupi.' // Insufficient stock
```

---

## Numbers & Currency

| Indonesian | Format | Example |
|------------|--------|---------|
| Currency | Rp X.XXX.XXX | Rp 1.500.000 |
| Decimal separator | , (comma) | 1.500,50 |
| Thousands separator | . (period) | 1.500.000 |
| Percentage | X,XX% | 11,00% |
| Date | DD/MM/YYYY | 25/12/2024 |

**Note:** In code, currency is stored as integers (smallest unit) to avoid floating-point precision issues.
