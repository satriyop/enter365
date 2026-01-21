# Document Number Generation

Number generation patterns for documents.

---

## Format Configuration

**Location:** `config/accounting.php`

```php
'document_formats' => [
    'quotation'       => 'QUO-{YEAR}{MONTH}-{SEQ}',
    'invoice'         => 'INV-{YEAR}{MONTH}-{SEQ}',
    'bill'            => 'BILL-{YEAR}{MONTH}-{SEQ}',
    'payment_receive' => 'RCV-{YEAR}{MONTH}-{SEQ}',
    'payment_send'    => 'PAY-{YEAR}{MONTH}-{SEQ}',
    'purchase_order'  => 'PO-{YEAR}{MONTH}-{SEQ}',
    'delivery_order'  => 'DO-{YEAR}{MONTH}-{SEQ}',
    'down_payment'    => 'DP-{YEAR}{MONTH}-{SEQ}',
    'sales_return'    => 'SR-{YEAR}{MONTH}-{SEQ}',
    'purchase_return' => 'PR-{YEAR}{MONTH}-{SEQ}',
    'work_order'      => 'WO-{YEAR}{MONTH}-{SEQ}',
    'project'         => 'PRJ-{YEAR}{MONTH}-{SEQ}',
    'bom'             => 'BOM-{SEQ}',
]
```

### Placeholders

| Placeholder | Value | Example |
|-------------|-------|---------|
| `{YEAR}` | 4-digit year | `2026` |
| `{MONTH}` | 2-digit month | `01` |
| `{SEQ}` | Padded sequence | `0001` |
| `{PREFIX}` | Document prefix | `INV` |

---

## Generated Number Examples

```
INV-202601-0001   (First invoice Jan 2026)
INV-202601-0002   (Second invoice Jan 2026)
INV-202602-0001   (First invoice Feb 2026 - resets!)
QUO-202601-0001-R2 (Quotation revision 2)
PRJ-001-WO-001    (Work order for project)
GRN-20260115-0001 (GRN with daily reset)
```

---

## Implementation Patterns

### Sequential Strategy (Most Common)

**Location:** `app/Domain/Shared/NumberGeneration/SequentialNumberStrategy.php`

```php
public function generate(string $prefix, string $table, string $column): string
{
    $lastRecord = DB::table($table)
        ->where($column, 'like', $prefix.'%')
        ->orderBy($column, 'desc')
        ->first();

    if ($lastRecord) {
        $lastNumber = (int) substr($lastRecord->$column, -4);
        $nextNumber = $lastNumber + 1;
    } else {
        $nextNumber = 1;
    }

    return $prefix.str_pad((string) $nextNumber, 4, '0', STR_PAD_LEFT);
}
```

### Project-Based Strategy

**Location:** `app/Domain/Shared/NumberGeneration/ProjectBasedNumberStrategy.php`

Format: `{PROJECT_NUMBER}/{PREFIX}/{SEQ}`

```php
// PRJ-001/WO/001
// PRJ-001/WO/002
// PRJ-002/WO/001
```

---

## Using in Services

### AbstractDocumentService Pattern

```php
abstract class AbstractDocumentService
{
    abstract protected function getDocumentNumberField(): string;
    abstract protected function getDocumentNumberPrefix(): string;
    abstract protected function getDocumentNumberConfig(): array;

    protected function generateDocumentNumber(): string
    {
        return $this->numberGenerator->generate(
            $this->getDocumentNumberPrefix(),
            $this->getDocumentNumberConfig()['table'],
            $this->getDocumentNumberConfig()['column']
        );
    }
}
```

### Invoice Service Example

```php
class InvoiceService extends AbstractDocumentService
{
    protected function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'INV-'.now()->format('Ym').'-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return [
            'table' => 'invoices',
            'column' => 'invoice_number',
        ];
    }
}
```

---

## Specialized Generators

### PaymentNumberGenerator

Type-based prefix:
```php
$prefix = ($type === 'receive' ? 'RCV' : 'PAY').'-'.now()->format('Ym').'-';
// RCV-202601-0001 or PAY-202601-0001
```

### DownPaymentNumberGenerator

Type-based prefix:
```php
$prefix = $type === 'receivable' ? 'DPR-' : 'DPP-';
// DPR-202601-0001 or DPP-202601-0001
```

### GoodsReceiptNoteNumberGenerator

Daily reset:
```php
$prefix = 'GRN-'.now()->format('Ymd').'-';
// GRN-20260115-0001
```

### WorkOrderNumberGenerator

Project-aware:
```php
if ($project) {
    $prefix = $project->project_number.'-WO-';
    // PRJ-001-WO-0001
} else {
    $prefix = 'WO-'.now()->format('Ym').'-';
    // WO-202601-0001
}
```

### QuotationNumberGenerator

With revision tracking:
```php
public function getFullNumber(Quotation $quotation): string
{
    if ($quotation->revision > 0) {
        return "{$quotation->quotation_number}-R{$quotation->revision}";
    }
    return $quotation->quotation_number;
}
// QUO-202601-0001-R2
```

---

## Document Number Summary

| Document | Prefix | Reset | Special |
|----------|--------|-------|---------|
| Invoice | `INV-YYYYMM-` | Monthly | - |
| Quotation | `QUO-YYYYMM-` | Monthly | Revision suffix |
| Bill | `BILL-YYYYMM-` | Monthly | - |
| Purchase Order | `PO-YYYYMM-` | Monthly | - |
| Delivery Order | `DO-YYYYMM-` | Monthly | - |
| Payment (Receive) | `RCV-YYYYMM-` | Monthly | Type-based |
| Payment (Send) | `PAY-YYYYMM-` | Monthly | Type-based |
| Down Payment | `DPR/DPP-YYYYMM-` | Monthly | Type-based |
| Work Order | `WO-YYYYMM-` | Monthly | Project-aware |
| Material Req | `MR-YYYYMM-` | Monthly | - |
| GRN | `GRN-YYYYMMDD-` | Daily | - |
| Sales Return | `SR-YYYYMM-` | Monthly | - |
| Purchase Return | `PR-YYYYMM-` | Monthly | - |

---

## Creating New Generator

### Step 1: Add Config

```php
// config/accounting.php
'document_formats' => [
    // ...
    'your_document' => 'YD-{YEAR}{MONTH}-{SEQ}',
]
```

### Step 2: Create Generator (if specialized)

```php
namespace App\Services\YourModule;

class YourDocumentNumberGenerator
{
    public function generate(): string
    {
        $prefix = 'YD-'.now()->format('Ym').'-';

        $last = YourDocument::query()
            ->where('document_number', 'like', $prefix.'%')
            ->orderByDesc('document_number')
            ->first();

        if ($last && preg_match('/-(\d{4})$/', $last->document_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
```

### Step 3: Use in Service

```php
class YourDocumentService
{
    public function __construct(
        private YourDocumentNumberGenerator $generator
    ) {}

    public function create(array $data): YourDocument
    {
        $data['document_number'] = $this->generator->generate();
        return YourDocument::create($data);
    }
}
```

---

## Key Points

1. **4-digit padding** - Sequence numbers are zero-padded to 4 digits
2. **Monthly reset** - Most documents reset sequence monthly
3. **Query last** - Always query for highest existing number
4. **Atomic generation** - Generate within transaction to prevent duplicates
5. **Prefix matching** - Use `LIKE prefix%` to find same-period records
