---
adr: "0031"
title: "Indonesian Language Support"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [i18n, indonesian-context]
related_adrs: []
related_modules: [core]
impact: low
---

# ADR-0031: Indonesian Language Support

## AI Agent Quick Reference

**Use this ADR when:**
- Adding new UI text
- Working with date/time formatting
- Implementing business document text
- Understanding localization approach

**Key takeaway:** Indonesian (Bahasa Indonesia) is primary language, mixed with English for technical terms.

---

## Decision

Use Indonesian as primary UI language with English for technical/accounting terms that are commonly used in Indonesian business.

---

## Context

Indonesian SME users:
1. Prefer Indonesian for UI and documents
2. Familiar with English accounting terms (Invoice, PO, etc.)
3. Use Indonesian date format (DD/MM/YYYY)
4. Expect Indonesian number format (1.000.000)

---

## Implementation

### Laravel Localization

```php
// config/app.php
'locale' => 'id',
'fallback_locale' => 'en',

// resources/lang/id/
// - validation.php
// - auth.php
// - pagination.php
// - accounting.php (custom)
```

### Accounting Terms

```php
// resources/lang/id/accounting.php
return [
    'invoice' => 'Faktur',
    'quotation' => 'Penawaran',
    'purchase_order' => 'Pesanan Pembelian',
    'payment' => 'Pembayaran',
    'bill' => 'Tagihan',
    'journal_entry' => 'Jurnal',

    // Actions
    'approve' => 'Setujui',
    'reject' => 'Tolak',
    'submit' => 'Kirim',
    'cancel' => 'Batalkan',
    'print' => 'Cetak',
    'export' => 'Ekspor',

    // Status
    'draft' => 'Draf',
    'pending' => 'Menunggu',
    'approved' => 'Disetujui',
    'completed' => 'Selesai',
    'cancelled' => 'Dibatalkan',
];
```

### Mixed Language Approach

| Category | Language | Example |
|----------|----------|---------|
| Menu/Navigation | Indonesian | Penjualan, Pembelian |
| Document titles | Indonesian | Faktur Penjualan |
| Field labels | Indonesian | Tanggal, Jumlah |
| Technical terms | English | BOM, MRP, ERP |
| Status codes | English | draft, approved |
| Button actions | Indonesian | Simpan, Hapus |

### Date Formatting

```php
// Indonesian date format
Carbon::setLocale('id');

// Formatting
$date->format('d/m/Y');           // 25/12/2024
$date->translatedFormat('d F Y'); // 25 Desember 2024
$date->diffForHumans();           // 2 hari yang lalu
```

### Number Formatting

```php
// Indonesian number format
number_format($amount, 0, ',', '.'); // 1.500.000

// Or use NumberFormatter
$formatter = new NumberFormatter('id_ID', NumberFormatter::DECIMAL);
$formatter->format(1500000); // 1.500.000
```

### Document Templates

```blade
{{-- Invoice template in Indonesian --}}
<h1>FAKTUR PENJUALAN</h1>
<p>Nomor: {{ $invoice->number }}</p>
<p>Tanggal: {{ $invoice->date->format('d/m/Y') }}</p>
<p>Jatuh Tempo: {{ $invoice->due_date->format('d/m/Y') }}</p>

<table>
    <thead>
        <tr>
            <th>Deskripsi</th>
            <th>Qty</th>
            <th>Harga</th>
            <th>Jumlah</th>
        </tr>
    </thead>
</table>

<p>Subtotal: @currency($invoice->subtotal)</p>
<p>PPN (11%): @currency($invoice->tax_amount)</p>
<p>Total: @currency($invoice->total)</p>
```

### Common Indonesian Terms

| Indonesian | English | Context |
|------------|---------|---------|
| Simpan | Save | Button |
| Hapus | Delete | Button |
| Ubah | Edit | Button |
| Cari | Search | Input |
| Semua | All | Filter |
| Tanggal | Date | Field |
| Jumlah | Amount | Field |
| Catatan | Notes | Field |
| Terlampir | Attached | Status |
| Termasuk | Including | Text |

---

## References

- [GLOSSARY.md](../GLOSSARY.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

