# Business Rules

> **Indonesian business and accounting rules for Enter365 ERP**
>
> This document covers tax regulations, accounting standards, and business conventions specific to Indonesian SMEs.

---

## Accounting Standard: SAK EMKM

Enter365 follows **SAK EMKM** (Standar Akuntansi Keuangan Entitas Mikro, Kecil, dan Menengah) - the Indonesian accounting standard for micro, small, and medium enterprises.

### Key Principles

1. **Accrual Basis** - Revenue and expenses recognized when earned/incurred
2. **Historical Cost** - Assets recorded at acquisition cost
3. **Going Concern** - Assumes business will continue operating
4. **Consistency** - Same accounting methods year to year

### Financial Statements Required

| Statement | Indonesian | Frequency |
|-----------|------------|-----------|
| Balance Sheet | Laporan Posisi Keuangan | Monthly/Yearly |
| Income Statement | Laporan Laba Rugi | Monthly/Yearly |
| Notes to Financial Statements | Catatan atas Laporan Keuangan | Yearly |

---

## Chart of Accounts Structure

Indonesian standard account numbering:

| Range | Type | Indonesian | Example |
|-------|------|------------|---------|
| 1xxx | Asset | Aktiva | 1-1001 Kas, 1-1100 Piutang Usaha |
| 2xxx | Liability | Kewajiban | 2-1001 Hutang Usaha, 2-2001 PPN Keluaran |
| 3xxx | Equity | Modal | 3-1000 Modal Disetor, 3-2000 Laba Ditahan |
| 4xxx | Revenue | Pendapatan | 4-1001 Pendapatan Penjualan |
| 5xxx | Expense | Beban | 5-1001 Harga Pokok Penjualan |

### System Accounts (Auto-created)

| Code | Name | Purpose |
|------|------|---------|
| 1-1100 | Piutang Usaha | Accounts Receivable |
| 2-1001 | Hutang Usaha | Accounts Payable |
| 2-2001 | PPN Keluaran | Output VAT |
| 2-2002 | PPN Masukan | Input VAT |
| 3-2000 | Laba Ditahan | Retained Earnings |
| 4-1001 | Pendapatan Penjualan | Sales Revenue |
| 5-1001 | Harga Pokok Penjualan | Cost of Goods Sold |

---

## Taxation Rules

### PPN (Pajak Pertambahan Nilai) - VAT

**Current Rate:** 11% (as of April 2022)

| Transaction | Tax Treatment |
|-------------|---------------|
| Domestic sales | PPN Keluaran (Output VAT) |
| Domestic purchases | PPN Masukan (Input VAT) |
| Exports | 0% (tax-free) |
| Certain services | May be exempt |

**PPN Calculation:**
```
PPN = DPP × 11%
Where DPP = Dasar Pengenaan Pajak (Tax Base)
```

**Monthly PPN Filing:**
- Due: 15th of following month
- Calculate: Output VAT - Input VAT
- If positive: Pay to government
- If negative: Carry forward or request refund

### Faktur Pajak (Tax Invoice)

Required for PPN transactions ≥ Rp 10,000,000:

| Field | Description |
|-------|-------------|
| Nomor Faktur | Tax invoice number (from DJP) |
| Tanggal Faktur | Invoice date |
| NPWP Penjual | Seller's tax ID |
| NPWP Pembeli | Buyer's tax ID |
| DPP | Tax base amount |
| PPN | VAT amount |

### PPh (Income Tax Withholding)

| Type | Rate | Applied To |
|------|------|-----------|
| PPh 21 | Progressive | Employee salaries |
| PPh 22 | 1.5% | Import transactions |
| PPh 23 | 2% | Services, royalties |
| PPh 4(2) | 0.5-10% | Construction, rent |

---

## Currency Rules

### Default Currency

- **Primary:** IDR (Indonesian Rupiah)
- **Storage:** Integer (no decimals for IDR)
- **Display:** Rp 1.234.567 (dot as thousand separator)

### Multi-Currency (Optional)

When enabled:
- Exchange rates stored daily
- Realized gains/losses on payment
- Unrealized gains/losses on reporting date

---

## Fiscal Year

### Indonesian Fiscal Year

- **Standard:** January 1 - December 31
- **Alternative:** April 1 - March 31 (requires approval)

### Fiscal Period States

| Status | Indonesian | Allows Transactions | Allows Modifications |
|--------|------------|--------------------:|---------------------:|
| Open | Terbuka | ✅ Yes | ✅ Yes |
| Locked | Terkunci | ❌ No | ❌ No |
| Closing | Sedang Ditutup | ❌ No | ❌ No |
| Closed | Ditutup | ❌ No | ❌ No |

### Year-End Close Process

1. **Lock Period** - Prevent new transactions
2. **Validate Checklist**:
   - All journals posted
   - Trial balance balanced
   - Required accounts exist
3. **Close Temporary Accounts** - Zero revenue/expense to retained earnings
4. **Close Dividends** - Transfer to retained earnings (if applicable)
5. **Mark Period Closed**
6. **Create Next Period** (optional)
7. **Populate Opening Balances** (optional)

---

## Document Numbering

### Format Convention

```
{PREFIX}-{YEAR}{MONTH}-{SEQUENCE}
```

| Document | Prefix | Example |
|----------|--------|---------|
| Quotation | QUO | QUO-2601-0001 |
| Invoice | INV | INV-2601-0001 |
| Delivery Order | DO | DO-2601-0001 |
| Purchase Order | PO | PO-2601-0001 |
| Goods Receipt Note | GRN | GRN-2601-0001 |
| Bill | BILL | BILL-2601-0001 |
| Journal Entry | JE | JE-2601-0001 |
| Payment | PAY | PAY-2601-0001 |

### Sequence Reset

- Monthly reset (configurable)
- Yearly reset (configurable)
- Never reset (continuous)

---

## Payment Terms

### Standard Terms

| Code | Days | Description |
|------|------|-------------|
| COD | 0 | Cash on Delivery |
| NET15 | 15 | Net 15 days |
| NET30 | 30 | Net 30 days |
| NET45 | 45 | Net 45 days |
| NET60 | 60 | Net 60 days |

### Due Date Calculation

```
Due Date = Invoice Date + Payment Term Days
```

### Aging Buckets

| Bucket | Days |
|--------|------|
| Current | 0 |
| 1-30 Days | 1-30 |
| 31-60 Days | 31-60 |
| 61-90 Days | 61-90 |
| Over 90 Days | 90+ |

---

## Inventory Valuation

### Supported Methods

| Method | Indonesian | Use Case |
|--------|------------|----------|
| FIFO | Masuk Pertama Keluar Pertama | General merchandise |
| Average | Rata-rata | High-volume items |
| Specific | Spesifik | Serialized items |

### COGS Recognition

| Strategy | When Recognized | Best For |
|----------|-----------------|----------|
| On Invoice | Invoice posted | Service companies |
| On Delivery | Goods shipped | Retail/wholesale |
| Manual | User triggered | Project-based |

---

## Credit Management

### Credit Limit Rules

| Rule | Action |
|------|--------|
| Over credit limit | Block new orders (warning) |
| Overdue invoices | Flag customer (warning) |
| Blacklisted | Block all transactions |

### Credit Check on:
- Creating quotation
- Converting to invoice
- Shipping goods

---

## Manufacturing Rules

### BOM Costing

```
Total Cost = Material Cost + Labor Cost + Overhead
```

| Component | Calculation |
|-----------|-------------|
| Material | Σ(Qty × Unit Cost) |
| Labor | Hours × Rate |
| Overhead | % of Material or Fixed |

### Subcontractor Retention

- **Standard:** 5% of invoice held
- **Release:** On final inspection
- **Period:** 30-90 days after completion

---

## Rounding Rules

### Currency Rounding (IDR)

| Context | Rounding |
|---------|----------|
| Line items | Round to nearest 1 |
| Tax calculation | Round down |
| Totals | Round to nearest 1 |

### Quantity Rounding

| Type | Precision |
|------|-----------|
| Pieces | 0 decimals |
| Weight (kg) | 2 decimals |
| Length (m) | 2 decimals |
| Volume (L) | 2 decimals |

---

## Related Documentation

- [Indonesian Context](/docs/02-domain/indonesian-context.md)
- [Accounting Domain](/docs/02-domain/accounting.md)
- [PPN/VAT ADR](/docs/08-adr/0026-ppn-vat-calculation.md)
- [Fiscal Year ADR](/docs/08-adr/0027-indonesian-fiscal-year.md)
- [Document Numbering ADR](/docs/08-adr/0030-document-number-format.md)
- [Aging Buckets ADR](/docs/08-adr/0032-aging-report-buckets.md)

---

## Configuration

All business rules are configurable in `/config/accounting.php`:

```php
return [
    'default_currency' => 'IDR',
    'tax_rate' => 11, // PPN rate
    'fiscal_year_start' => 1, // January
    'aging_buckets' => [0, 30, 60, 90],
    'payment_terms' => ['COD', 'NET15', 'NET30', 'NET45', 'NET60'],
    // ... more settings
];
```
