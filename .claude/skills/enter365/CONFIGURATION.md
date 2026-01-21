# Configuration Reference

Quick reference for Enter365 configuration values.

---

## Feature Flags

**File:** `config/features.php`

Toggle modules on/off via environment variables.

```php
// Access in code
if (config('features.modules.mrp')) {
    // MRP is enabled
}

// Or via FeatureManager
app(FeatureManager::class)->isEnabled('mrp');
```

### Available Modules

| Module | Env Variable | Default |
|--------|--------------|---------|
| Products | `FEATURE_PRODUCTS` | true |
| Quotations | `FEATURE_QUOTATIONS` | true |
| Delivery Orders | `FEATURE_DELIVERY_ORDERS` | true |
| Sales Returns | `FEATURE_SALES_RETURNS` | true |
| Down Payments | `FEATURE_DOWN_PAYMENTS` | true |
| Purchase Orders | `FEATURE_PURCHASE_ORDERS` | true |
| GRN | `FEATURE_GRN` | true |
| Purchase Returns | `FEATURE_PURCHASE_RETURNS` | true |
| Inventory | `FEATURE_INVENTORY` | true |
| Stock Opname | `FEATURE_STOCK_OPNAME` | true |
| Warehouses | `FEATURE_WAREHOUSES` | true |
| Manufacturing | `FEATURE_MANUFACTURING` | true |
| BOM | `FEATURE_BOM` | true |
| Work Orders | `FEATURE_WORK_ORDERS` | true |
| Material Requisitions | `FEATURE_MATERIAL_REQUISITIONS` | true |
| MRP | `FEATURE_MRP` | true |
| Subcontracting | `FEATURE_SUBCONTRACTING` | true |
| Projects | `FEATURE_PROJECTS` | true |
| Solar Proposals | `FEATURE_SOLAR_PROPOSALS` | true |
| Budgeting | `FEATURE_BUDGETING` | true |
| Recurring | `FEATURE_RECURRING` | true |
| Multi-Currency | `FEATURE_MULTI_CURRENCY` | true |
| Bank Reconciliation | `FEATURE_BANK_RECONCILIATION` | true |

### Disable in .env

```env
FEATURE_MRP=false
FEATURE_SOLAR_PROPOSALS=false
```

---

## Accounting Configuration

**File:** `config/accounting.php`

### Company Information

```php
config('accounting.company.name')     // PT Contoh Indonesia
config('accounting.company.npwp')     // Company tax ID
config('accounting.company.address')
config('accounting.company.city')
config('accounting.company.phone')
config('accounting.company.email')
```

### Tax Settings (PPN)

```php
config('accounting.tax.default_rate')  // 11.00 (11%)
config('accounting.tax.name')          // 'PPN'
```

**Usage:**

```php
$taxRate = config('accounting.tax.default_rate') / 100;  // 0.11
$taxAmount = (int) round($subtotal * $taxRate);
```

### Payment Terms

```php
config('accounting.payment.default_term_days')  // 30
config('accounting.payment.available_terms')    // [0, 7, 14, 30, 45, 60, 90]
```

### Credit Limits

```php
config('accounting.credit_limit.enabled')        // true
config('accounting.credit_limit.default_limit')  // 0 (no limit)
config('accounting.credit_limit.warn_at_percent') // 80
config('accounting.credit_limit.block_at_percent') // 100
```

### Document Number Formats

```php
config('accounting.document_formats.quotation')  // 'QUO-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.invoice')    // 'INV-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.bill')       // 'BILL-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.delivery_order') // 'DO-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.purchase_order') // 'PO-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.work_order') // 'WO-{YEAR}{MONTH}-{SEQ}'
config('accounting.document_formats.journal')    // 'JE-{YEAR}{MONTH}-{SEQ}'
```

**Placeholders:**
- `{PREFIX}` - Document prefix
- `{YEAR}` - 4-digit year (2024)
- `{MONTH}` - 2-digit month (01-12)
- `{SEQ}` - Sequence number (padded)

### Aging Buckets

```php
config('accounting.aging_buckets')
// Returns:
// [
//     ['min' => 0, 'max' => 0, 'label' => 'Belum Jatuh Tempo'],
//     ['min' => 1, 'max' => 30, 'label' => '1-30 Hari'],
//     ['min' => 31, 'max' => 60, 'label' => '31-60 Hari'],
//     ['min' => 61, 'max' => 90, 'label' => '61-90 Hari'],
//     ['min' => 91, 'max' => null, 'label' => '> 90 Hari'],
// ]
```

### Recurring Documents

```php
config('accounting.recurring.enabled')              // true
config('accounting.recurring.frequencies')          // daily, weekly, monthly, quarterly, yearly
config('accounting.recurring.auto_post')            // false
config('accounting.recurring.generate_days_before') // 3
```

---

## Accounting Policies

**File:** `config/accounting.php` → `policies`

### Inventory Method

```php
config('accounting.policies.inventory_method')  // 'average'
```

| Value | Description |
|-------|-------------|
| `average` | Weighted average cost (default) |
| `fifo` | First-in, first-out |
| `standard` | Standard costing |

### COGS Recognition

```php
config('accounting.policies.cogs_recognition')  // 'on_invoice'
```

| Value | Description |
|-------|-------------|
| `on_invoice` | Recognize COGS when invoice is posted |
| `on_delivery` | Recognize COGS when goods are shipped |
| `manual` | Manual COGS entries only |

### Return Accounting

```php
config('accounting.policies.return_accounting')  // 'credit'
```

| Value | Description |
|-------|-------------|
| `credit` | Issue credit note (default) |
| `refund` | Issue refund |

### Manufacturing Cost

```php
config('accounting.policies.manufacturing_cost')  // 'standard'
```

| Value | Description |
|-------|-------------|
| `standard` | Standard cost |
| `actual` | Actual cost |

---

## Default Accounts

```php
// Access default account codes
config('accounting.default_accounts.accounts_receivable')  // '1-1200'
config('accounting.default_accounts.accounts_payable')     // '2-1100'
config('accounting.default_accounts.sales_revenue')        // '4-1000'
config('accounting.default_accounts.cogs')                 // '5-1000'
config('accounting.default_accounts.inventory')            // '1-1300'
config('accounting.default_accounts.bank')                 // '1-1100'
config('accounting.default_accounts.ppn_output')           // '2-1200'
config('accounting.default_accounts.ppn_input')            // '1-1400'
```

---

## Environment Variables Reference

### Company

```env
COMPANY_NAME="PT Contoh Indonesia"
COMPANY_NPWP="01.234.567.8-901.000"
COMPANY_ADDRESS="Jl. Contoh No. 123"
COMPANY_CITY="Jakarta"
COMPANY_PHONE="+62-21-1234567"
COMPANY_EMAIL="info@contoh.co.id"
```

### Accounting

```env
DEFAULT_CURRENCY=IDR
TAX_DEFAULT_RATE=11
PAYMENT_DEFAULT_TERM_DAYS=30
CREDIT_LIMIT_ENABLED=true
EARLY_PAYMENT_DISCOUNT_ENABLED=true
RECURRING_ENABLED=true
```

### Features

```env
FEATURE_MRP=true
FEATURE_SOLAR_PROPOSALS=true
FEATURE_MULTI_CURRENCY=true
# ... see Feature Flags section
```

---

## Usage in Code

### Check Feature Flag

```php
use App\Contracts\FeatureManager;

// Via facade/helper
if (config('features.modules.mrp')) {
    // ...
}

// Via service
$features = app(FeatureManager::class);
if ($features->isEnabled('mrp')) {
    // ...
}
```

### Get Tax Rate

```php
$taxRate = config('accounting.tax.default_rate') / 100;
$taxAmount = (int) round($subtotal * $taxRate);
```

### Get Default Account

```php
$accountCode = config('accounting.default_accounts.accounts_receivable');
$account = Account::where('code', $accountCode)->first();
```

### Get Document Format

```php
$format = config('accounting.document_formats.invoice');
// 'INV-{YEAR}{MONTH}-{SEQ}'

// Use SequenceNumberGenerator to generate
$number = app(SequenceNumberGenerator::class)->generate('invoice');
// 'INV-202401-0001'
```

### Check Credit Limit

```php
$limit = config('accounting.credit_limit.default_limit');
$warnPercent = config('accounting.credit_limit.warn_at_percent');

if ($limit > 0) {
    $usage = ($outstanding / $limit) * 100;
    if ($usage >= $warnPercent) {
        // Warn user
    }
}
```

---

## Testing with Config

```php
it('uses configured tax rate', function () {
    config(['accounting.tax.default_rate' => 12]);

    $invoice = Invoice::factory()->create(['subtotal' => 100_00]);

    expect($invoice->tax_amount)->toBe(12_00);
});

it('respects feature flag', function () {
    config(['features.modules.mrp' => false]);

    $response = $this->getJson('/api/v1/mrp/runs');

    $response->assertNotFound();
});
```
