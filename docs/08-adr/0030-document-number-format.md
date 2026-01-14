---
adr: "0030"
title: "Document Number Format"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [documents, indonesian-context]
related_adrs: []
related_modules: [accounting]
impact: medium
---

# ADR-0030: Document Number Format

## AI Agent Quick Reference

**Use this ADR when:**
- Creating new document types
- Implementing auto-numbering
- Understanding document prefixes
- Building number generators

**Key takeaway:** Documents use PREFIX-YYYYMM-NNNN format with automatic sequence per month.

---

## Decision

Use standardized document numbering: `PREFIX-YYYYMM-NNNN` with automatic sequence reset per month.

---

## Context

Indonesian business documents need:
1. Unique, sequential numbers
2. Date-based grouping (for tax periods)
3. Easy identification by type
4. Audit-friendly sequences

---

## Implementation

### Number Format

```
PREFIX-YYYYMM-NNNN

PREFIX:  Document type code (2-4 chars)
YYYY:    4-digit year
MM:      2-digit month
NNNN:    4-digit sequence (0001-9999)

Example: INV-202401-0042
         QUO-202312-0001
         PO-202401-0015
```

### Document Prefixes

| Document | Prefix | Example |
|----------|--------|---------|
| Quotation | QUO | QUO-202401-0001 |
| Sales Order | SO | SO-202401-0001 |
| Invoice | INV | INV-202401-0001 |
| Payment | PAY | PAY-202401-0001 |
| Purchase Order | PO | PO-202401-0001 |
| Goods Receipt | GRN | GRN-202401-0001 |
| Bill | BILL | BILL-202401-0001 |
| Work Order | WO | WO-202401-0001 |
| Journal Entry | JE | JE-202401-0001 |
| Stock Opname | STO | STO-202401-0001 |
| Project | PRJ | PRJ-202401-0001 |

### Sequence Model

```php
// DocumentSequence - tracks sequences per type/month
$table->string('document_type');          // invoice, quotation, etc.
$table->integer('year');
$table->integer('month');
$table->integer('last_number')->default(0);
$table->unique(['document_type', 'year', 'month']);
```

### Number Generator Service

```php
// app/Services/Accounting/DocumentNumberService.php
class DocumentNumberService
{
    public function generate(string $type): string
    {
        return DB::transaction(function () use ($type) {
            $prefix = $this->getPrefix($type);
            $year = now()->year;
            $month = now()->month;

            // Lock row for update
            $sequence = DocumentSequence::lockForUpdate()
                ->firstOrCreate(
                    ['document_type' => $type, 'year' => $year, 'month' => $month],
                    ['last_number' => 0]
                );

            $sequence->increment('last_number');

            return sprintf(
                '%s-%d%02d-%04d',
                $prefix,
                $year,
                $month,
                $sequence->last_number
            );
        });
    }

    private function getPrefix(string $type): string
    {
        return config("accounting.document_prefixes.{$type}", strtoupper(substr($type, 0, 3)));
    }
}
```

### Configuration

```php
// config/accounting.php
'document_prefixes' => [
    'quotation' => 'QUO',
    'sales_order' => 'SO',
    'invoice' => 'INV',
    'payment' => 'PAY',
    'purchase_order' => 'PO',
    'goods_receipt' => 'GRN',
    'bill' => 'BILL',
    'work_order' => 'WO',
    'journal_entry' => 'JE',
    'stock_opname' => 'STO',
    'project' => 'PRJ',
],
```

### Model Integration

```php
// In model boot or observer
protected static function boot()
{
    parent::boot();

    static::creating(function ($model) {
        if (empty($model->number)) {
            $model->number = app(DocumentNumberService::class)
                ->generate('invoice');
        }
    });
}
```

### Uniqueness Validation

```php
// Migration
$table->string('number')->unique();

// Form Request
'number' => ['required', 'unique:invoices,number'],
```

### Manual Number Override

```php
// Allow manual entry with gap detection
public function setCustomNumber(string $number): void
{
    // Validate format
    if (!preg_match('/^[A-Z]{2,4}-\d{6}-\d{4}$/', $number)) {
        throw new InvalidDocumentNumberException($number);
    }

    // Check uniqueness
    if (static::where('number', $number)->exists()) {
        throw new DuplicateDocumentNumberException($number);
    }

    $this->number = $number;
}
```

---

## References

- [Indonesian Context](../02-domain/indonesian-context.md)

