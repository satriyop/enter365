---
section: domain
title: "Indonesian Context"
order: 6
---

# Indonesian Business Context

> **SAK EMKM, PPN, NPWP, and Indonesian business practices**
>
> Understanding the regulatory and cultural context of Indonesian SMEs.

---

## AI Agent Quick Reference

**Use this document when:**
- Implementing Indonesian-specific features
- Understanding tax calculations
- Working with document formats
- Debugging compliance issues

**Key concepts:** SAK EMKM, PPN (11%), NPWP, Indonesian fiscal year

---

## SAK EMKM (Accounting Standard)

### What is SAK EMKM?

**SAK EMKM** = Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah

Indonesian Financial Accounting Standard for Micro, Small, and Medium Entities, issued by IAI (Ikatan Akuntan Indonesia).

### Why It Matters

Indonesian SMEs must use SAK EMKM for:
- **Tax reporting** to DJP (Direktorat Jenderal Pajak)
- **Bank loan applications** (banks require compliant books)
- **Government procurement** eligibility
- **Business partner due diligence**

### SAK EMKM Requirements in Enter365

| Requirement | Implementation |
|-------------|----------------|
| Accrual Basis | Transactions recorded when they occur, not when cash moves |
| Double-Entry | Every transaction has balanced debits and credits |
| Chart of Accounts | 1-Asset, 2-Liability, 3-Equity, 4-Revenue, 5-Expense |
| Financial Statements | Balance Sheet (Neraca), Income Statement (Laporan Laba Rugi) |
| Fiscal Year | January 1 - December 31 (calendar year) |

### Chart of Accounts Structure

```
1-xxxx  Assets (Aset)
├── 1-1xxx  Current Assets (Aset Lancar)
│   ├── 1-1001  Cash (Kas)
│   ├── 1-1002  Bank
│   ├── 1-1100  Accounts Receivable (Piutang Usaha)
│   └── 1-1300  PPN Masukan (Input VAT)
├── 1-3xxx  Inventory (Persediaan)
└── 1-4xxx  Fixed Assets (Aset Tetap)

2-xxxx  Liabilities (Kewajiban)
├── 2-1xxx  Current Liabilities
│   ├── 2-1100  Accounts Payable (Hutang Usaha)
│   └── 2-1200  PPN Keluaran (Output VAT)
└── 2-3xxx  Long-term Liabilities

3-xxxx  Equity (Ekuitas)
├── 3-1xxx  Capital (Modal)
└── 3-2xxx  Retained Earnings (Laba Ditahan)

4-xxxx  Revenue (Pendapatan)
├── 4-1xxx  Operating Revenue (Pendapatan Usaha)
└── 4-2xxx  Other Income (Pendapatan Lain)

5-xxxx  Expenses (Beban)
├── 5-1xxx  Cost of Goods Sold (Harga Pokok Penjualan)
└── 5-2xxx  Operating Expenses (Beban Operasional)
```

**See:** [ADR-0006: SAK EMKM Compliance](../08-adr/0006-sak-emkm-compliance.md)

---

## PPN (Value Added Tax)

### What is PPN?

**PPN** = Pajak Pertambahan Nilai (Value Added Tax)

Current rate: **11%** (increased from 10% in April 2022)

### PPN Calculation

```php
// config/accounting.php
'tax' => [
    'default_rate' => 11.00,
    'name' => 'PPN',
],
```

### PPN in Transactions

**Sales Invoice:**
```
Subtotal:           Rp 10,000,000
PPN 11%:            Rp  1,100,000
Total:              Rp 11,100,000

Journal Entry:
DR Accounts Receivable  Rp 11,100,000
CR Sales Revenue        Rp 10,000,000
CR PPN Keluaran         Rp  1,100,000
```

**Vendor Bill:**
```
Subtotal:           Rp  5,000,000
PPN 11%:            Rp    550,000
Total:              Rp  5,550,000

Journal Entry:
DR Inventory/Expense    Rp  5,000,000
DR PPN Masukan          Rp    550,000
CR Accounts Payable     Rp  5,550,000
```

### PPN Reporting

Monthly PPN reporting to DJP:
- **PPN Keluaran** (Output VAT) - collected from customers
- **PPN Masukan** (Input VAT) - paid to vendors
- **Net PPN** = Keluaran - Masukan (payable to government)

---

## NPWP (Tax ID)

### What is NPWP?

**NPWP** = Nomor Pokok Wajib Pajak (Taxpayer Identification Number)

Format: `XX.XXX.XXX.X-XXX.XXX`

Example: `01.234.567.8-901.000`

### NPWP in Enter365

```php
// Contact model
$table->string('npwp', 20)->nullable();

// Validation
'npwp' => ['nullable', 'regex:/^\d{2}\.\d{3}\.\d{3}\.\d-\d{3}\.\d{3}$/'],
```

### NPWP Requirements

| Document | NPWP Required? |
|----------|---------------|
| Tax Invoice (Faktur Pajak) | Yes |
| Regular Invoice | No |
| Purchase Order | No |
| Vendor Bill | For tax credit |

---

## Indonesian Fiscal Year

### Calendar Year

Indonesian fiscal year = **January 1 to December 31**

```php
// config/accounting.php
'fiscal_year' => [
    'start_month' => 1,  // January
    'start_day' => 1,
],
```

### Fiscal Period Management

```php
// FiscalPeriod model
$table->string('name');           // "Tahun Buku 2024"
$table->date('start_date');       // 2024-01-01
$table->date('end_date');         // 2024-12-31
$table->string('status');         // open, closed

// Prevent posting to closed periods
if ($fiscalPeriod->status === 'closed') {
    throw new Exception('Periode sudah ditutup.');
}
```

---

## Indonesian Currency (IDR)

### Rupiah

- **Currency code:** IDR
- **Symbol:** Rp
- **No decimal places** in practice (smallest unit is Rp 1)

### Formatting

```php
// Display format
'Rp 15.000.000'  // Fifteen million Rupiah

// Decimal separator: comma (,)
// Thousands separator: period (.)

// In code
number_format($amount / 100, 0, ',', '.');
```

### Storage

All amounts stored as integers (see [ADR-0008](../08-adr/0008-integer-currency-storage.md)):
- Rp 15,000.00 → stored as 1500000
- Rp 1,000,000.00 → stored as 100000000

---

## Common Business Practices

### Payment Terms

| Term | Indonesian | Description |
|------|------------|-------------|
| COD | Tunai | Cash on delivery |
| Net 7 | 7 hari | Payment within 7 days |
| Net 30 | 30 hari | Payment within 30 days |
| Net 45 | 45 hari | Payment within 45 days |
| Net 60 | 60 hari | Payment within 60 days |

```php
// config/accounting.php
'payment' => [
    'default_term_days' => 30,
    'available_terms' => [0, 7, 14, 30, 45, 60, 90],
],
```

### Down Payment (Uang Muka)

Common practice: **30-50% down payment** before work begins.

```
Project Value:      Rp 100,000,000
Down Payment (30%): Rp  30,000,000  ← Paid before start
Progress (30%):     Rp  30,000,000  ← Paid at 50% completion
Final (40%):        Rp  40,000,000  ← Paid on delivery
```

### Retention (Retensi)

For construction/manufacturing contracts: **5% retention** held until warranty period ends.

```
Invoice Total:      Rp 100,000,000
Less Retention 5%:  Rp   5,000,000
Payment Due:        Rp  95,000,000
Retention Release:  After 3-12 months
```

**See:** [ADR-0018: Subcontractor Retention](../08-adr/0018-subcontractor-retention.md)

---

## Document Numbering

### Indonesian Conventions

```php
// config/accounting.php
'document_formats' => [
    'quotation' => 'QUO-{YEAR}{MONTH}-{SEQ}',      // QUO-202401-0001
    'invoice' => 'INV-{YEAR}{MONTH}-{SEQ}',        // INV-202401-0001
    'bill' => 'BILL-{YEAR}{MONTH}-{SEQ}',
    'purchase_order' => 'PO-{YEAR}{MONTH}-{SEQ}',
    'delivery_order' => 'DO-{YEAR}{MONTH}-{SEQ}',  // Surat Jalan
    'journal_entry' => 'JE-{YEAR}{MONTH}-{SEQ}',
],
```

### Document Names

| English | Indonesian | Code |
|---------|------------|------|
| Quotation | Penawaran | QUO |
| Invoice | Faktur | INV |
| Bill | Tagihan | BILL |
| Purchase Order | Pesanan Pembelian | PO |
| Delivery Order | Surat Jalan | DO |
| Goods Receipt Note | Bukti Terima Barang | GRN |
| Journal Entry | Jurnal | JE |
| Down Payment | Uang Muka | DP |
| Sales Return | Retur Penjualan | SR |
| Purchase Return | Retur Pembelian | PR |

---

## Aging Report Buckets

Indonesian standard aging periods:

```php
// config/accounting.php
'aging_buckets' => [
    ['min' => 0, 'max' => 0, 'label' => 'Belum Jatuh Tempo'],
    ['min' => 1, 'max' => 30, 'label' => '1-30 Hari'],
    ['min' => 31, 'max' => 60, 'label' => '31-60 Hari'],
    ['min' => 61, 'max' => 90, 'label' => '61-90 Hari'],
    ['min' => 91, 'max' => null, 'label' => '> 90 Hari'],
],
```

---

## Indonesian Language

### UI Language

Enter365 uses Indonesian for:
- Error messages
- Validation messages
- Email notifications
- Document templates

```php
// Example validation messages
'contact_id.required' => 'Pelanggan wajib dipilih.',
'items.required' => 'Minimal 1 item diperlukan.',
'amount.min' => 'Jumlah minimal Rp 1.',
```

### Key Terms

See [GLOSSARY.md](../GLOSSARY.md) for complete Indonesian ↔ English term mapping.

---

## PLN (Electricity)

### PLN Tariffs

For solar EPC features, Enter365 includes PLN tariff database:

| Category | Code | Power | Rate/kWh |
|----------|------|-------|----------|
| Residential Small | R1/1300 | 1,300 VA | Rp 1,444 |
| Residential Medium | R1/2200 | 2,200 VA | Rp 1,444 |
| Business Small | B1/1300 | 1,300 VA | Rp 1,444 |
| Business Medium | B2/6600 | 6,600 VA | Rp 1,444 |
| Industrial | I3/200kVA+ | 200+ kVA | Varies |

### Usage in Solar Calculations

```php
$tariff = PlnTariff::where('code', 'R1/2200')->first();
$annualSavings = $annualProduction * $tariff->rate_per_kwh;
```

---

## Regulatory Compliance

### Required Reports

| Report | Frequency | Authority |
|--------|-----------|-----------|
| SPT Masa PPN | Monthly | DJP |
| SPT Tahunan | Yearly | DJP |
| Laporan Keuangan | Yearly | Internal/Bank |

### Audit Trail

SAK EMKM requires complete audit trail:
- All transactions dated and timestamped
- User who created/modified recorded
- No deletion of posted transactions
- Soft deletes for data retention

```php
// AuditLog model
$table->morphs('auditable');
$table->string('event');        // created, updated, deleted
$table->json('old_values');
$table->json('new_values');
$table->foreignId('user_id');
$table->timestamp('created_at');
```

---

## Related Documentation

- [ADR-0006: SAK EMKM Compliance](../08-adr/0006-sak-emkm-compliance.md)
- [ADR-0026: PPN VAT Calculation](../08-adr/0026-ppn-vat-calculation.md)
- [ADR-0027: Indonesian Fiscal Year](../08-adr/0027-indonesian-fiscal-year.md)
- [ADR-0028: NPWP Validation](../08-adr/0028-npwp-validation.md)
- [GLOSSARY.md](../GLOSSARY.md) - Complete term mapping
