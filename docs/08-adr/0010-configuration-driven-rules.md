---
adr: "0010"
title: "Configuration-Driven Business Rules"
status: accepted
date: 2024-11-01
deciders: [Architecture Team, Product Team]
tags: [architecture, configuration, business-rules]
related_adrs: [0003, 0006, 0007]
related_modules: [all]
impact: high
---

# ADR-0010: Configuration-Driven Business Rules

## AI Agent Quick Reference

**Use this ADR when:**
- Adding new configurable business rules
- Understanding where business defaults come from
- Modifying tax rates, payment terms, document formats
- Working with the `config/accounting.php` file

**Key takeaway:** Business rules like tax rates, payment terms, document formats, and aging buckets are defined in `config/accounting.php`, not hardcoded.

---

## Context

Enter365 must support Indonesian SME business practices that vary:
- Tax rates (PPN currently 11%, was 10%, may change)
- Payment terms (30, 45, 60 days common)
- Document number formats (company preferences)
- Credit limits and overdue thresholds
- Aging report periods

Hardcoding these would require code changes for each customer or regulation change.

---

## Decision Drivers

1. **Regulatory Changes** - Tax rates change (10% → 11%)
2. **Customer Preferences** - Different document formats
3. **Industry Variations** - Different payment terms
4. **Maintainability** - Change config, not code
5. **Deployment Flexibility** - Environment-specific settings

---

## Considered Options

### Option 1: Configuration File (Chosen)

**Description:** Central config file with all business rules

**Pros:**
- Single source of truth
- Environment variable overrides
- Laravel config caching
- Easy to find and modify
- No code changes needed

**Cons:**
- Requires redeploy for changes
- Not admin-UI editable

### Option 2: Database Settings

**Description:** Settings stored in database table

**Pros:**
- Runtime changes
- Admin UI possible
- Per-tenant settings

**Cons:**
- Database queries for config
- Migration needed for new settings
- More complex implementation

### Option 3: Hardcoded Constants

**Description:** Constants in code

**Pros:**
- Type-safe
- IDE autocomplete

**Cons:**
- Code changes for any modification
- Different per customer = different code
- Inflexible

---

## Decision

**Chosen option:** "Configuration File"

All business rules are defined in `config/accounting.php` with environment variable overrides where appropriate.

---

## Rationale

### Why Configuration:

1. **Change Without Code**
   - Tax rate changes: update `.env`
   - Payment terms: update config
   - Document formats: update config

2. **Environment Flexibility**
   ```bash
   # Production
   TAX_DEFAULT_RATE=11.00

   # Testing
   TAX_DEFAULT_RATE=10.00
   ```

3. **Clear Documentation**
   - All rules in one file
   - Comments explain each setting
   - Self-documenting

4. **Laravel Integration**
   - `config('accounting.tax.default_rate')`
   - Config caching for performance
   - Environment variable support

---

## Consequences

### Positive

- Business rules in one place
- Easy to modify without code changes
- Environment-specific configuration
- Self-documenting config file
- Laravel config caching

### Negative

- Requires deployment for changes
- No runtime admin UI (by design)
- Must remember to update config

### Neutral

- Config file becomes central reference
- Team must know config locations

---

## Implementation Notes

**Main Configuration File:**

```php
// File: /config/accounting.php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    */
    'company' => [
        'name' => env('COMPANY_NAME', 'PT Contoh Indonesia'),
        'npwp' => env('COMPANY_NPWP', ''),
        'address' => env('COMPANY_ADDRESS', ''),
        'city' => env('COMPANY_CITY', 'Jakarta'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Currency
    |--------------------------------------------------------------------------
    */
    'default_currency' => env('DEFAULT_CURRENCY', 'IDR'),

    /*
    |--------------------------------------------------------------------------
    | Tax Settings (PPN - Pajak Pertambahan Nilai)
    |--------------------------------------------------------------------------
    */
    'tax' => [
        'default_rate' => env('TAX_DEFAULT_RATE', 11.00),
        'name' => 'PPN',
        'registration_number_label' => 'NPWP',
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Terms
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'default_term_days' => env('PAYMENT_DEFAULT_TERM_DAYS', 30),
        'available_terms' => [0, 7, 14, 30, 45, 60, 90],
    ],

    /*
    |--------------------------------------------------------------------------
    | Early Payment Discount
    |--------------------------------------------------------------------------
    */
    'early_payment_discount' => [
        'enabled' => env('EARLY_PAYMENT_DISCOUNT_ENABLED', true),
        'default_discount_percent' => 2.00,
        'default_discount_days' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Credit Limit Settings
    |--------------------------------------------------------------------------
    */
    'credit_limit' => [
        'enabled' => env('CREDIT_LIMIT_ENABLED', true),
        'default_limit' => 0,
        'warn_at_percent' => 80,
        'block_at_percent' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Overdue Settings
    |--------------------------------------------------------------------------
    */
    'overdue' => [
        'check_daily' => true,
        'grace_period_days' => 0,
        'reminder_intervals' => [1, 7, 14, 30],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recurring Documents
    |--------------------------------------------------------------------------
    */
    'recurring' => [
        'enabled' => env('RECURRING_ENABLED', true),
        'frequencies' => [
            'daily' => 'Harian',
            'weekly' => 'Mingguan',
            'monthly' => 'Bulanan',
            'quarterly' => 'Triwulan',
            'yearly' => 'Tahunan',
        ],
        'auto_post' => false,
        'generate_days_before' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Aging Report Buckets
    |--------------------------------------------------------------------------
    */
    'aging_buckets' => [
        ['min' => 0, 'max' => 0, 'label' => 'Belum Jatuh Tempo'],
        ['min' => 1, 'max' => 30, 'label' => '1-30 Hari'],
        ['min' => 31, 'max' => 60, 'label' => '31-60 Hari'],
        ['min' => 61, 'max' => 90, 'label' => '61-90 Hari'],
        ['min' => 91, 'max' => null, 'label' => '> 90 Hari'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Number Formats
    |--------------------------------------------------------------------------
    */
    'document_formats' => [
        'quotation' => 'QUO-{YEAR}{MONTH}-{SEQ}',
        'invoice' => 'INV-{YEAR}{MONTH}-{SEQ}',
        'bill' => 'BILL-{YEAR}{MONTH}-{SEQ}',
        'payment_receive' => 'RCV-{YEAR}{MONTH}-{SEQ}',
        'payment_send' => 'PAY-{YEAR}{MONTH}-{SEQ}',
        'journal_entry' => 'JE-{YEAR}{MONTH}-{SEQ}',
        'purchase_order' => 'PO-{YEAR}{MONTH}-{SEQ}',
        'delivery_order' => 'DO-{YEAR}{MONTH}-{SEQ}',
        'down_payment' => 'DP-{YEAR}{MONTH}-{SEQ}',
        'sales_return' => 'SR-{YEAR}{MONTH}-{SEQ}',
        'purchase_return' => 'PR-{YEAR}{MONTH}-{SEQ}',
        'project' => 'PRJ-{YEAR}{MONTH}-{SEQ}',
        'bom' => 'BOM-{SEQ}',
    ],

    /*
    |--------------------------------------------------------------------------
    | Quotation Settings
    |--------------------------------------------------------------------------
    */
    'quotation' => [
        'default_validity_days' => 30,
        'terms_conditions' => [
            'id' => "SYARAT DAN KETENTUAN:\n1. Harga berlaku selama masa penawaran...",
            'en' => "TERMS AND CONDITIONS:\n1. Prices are valid during...",
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year
    |--------------------------------------------------------------------------
    */
    'fiscal_year' => [
        'start_month' => 1, // January
        'start_day' => 1,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Account Codes
    |--------------------------------------------------------------------------
    */
    'default_accounts' => [
        'cash' => '1-1001',
        'bank' => '1-1002',
        'accounts_receivable' => '1-1100',
        'accounts_payable' => '2-1100',
        'sales_revenue' => '4-1001',
        'purchase_expense' => '5-1002',
        'tax_payable' => '2-1200',
        'tax_receivable' => '1-1300',
    ],
];
```

**Usage in Services:**

```php
// File: /app/Services/Accounting/QuotationService.php

class QuotationService
{
    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            // Get defaults from config
            $data['tax_rate'] = $data['tax_rate']
                ?? config('accounting.tax.default_rate');

            $data['valid_until'] = $data['valid_until']
                ?? now()->addDays(config('accounting.quotation.default_validity_days'));

            $data['terms_conditions'] = $data['terms_conditions']
                ?? config('accounting.quotation.terms_conditions.id');

            $data['quotation_number'] = $this->generateNumber();

            return Quotation::create($data);
        });
    }

    protected function generateNumber(): string
    {
        $format = config('accounting.document_formats.quotation');
        // QUO-{YEAR}{MONTH}-{SEQ} → QUO-202401-0001

        return str_replace(
            ['{YEAR}', '{MONTH}', '{SEQ}'],
            [now()->format('Y'), now()->format('m'), $this->getNextSequence()],
            $format
        );
    }
}
```

**Usage in Reports:**

```php
// File: /app/Services/Accounting/AgingReportService.php

class AgingReportService
{
    public function generate(): array
    {
        $buckets = config('accounting.aging_buckets');

        $report = [];
        foreach ($buckets as $bucket) {
            $report[] = [
                'label' => $bucket['label'],
                'total' => $this->calculateBucket($bucket['min'], $bucket['max']),
            ];
        }

        return $report;
    }
}
```

**Usage in Controllers:**

```php
// File: /app/Http/Controllers/Api/V1/SettingsController.php

class SettingsController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'tax' => config('accounting.tax'),
            'payment' => config('accounting.payment'),
            'document_formats' => config('accounting.document_formats'),
            'aging_buckets' => config('accounting.aging_buckets'),
        ]);
    }
}
```

**Environment Overrides:**

```bash
# File: /.env

# Tax (regulation changes)
TAX_DEFAULT_RATE=11.00

# Payment (company policy)
PAYMENT_DEFAULT_TERM_DAYS=45

# Features
EARLY_PAYMENT_DISCOUNT_ENABLED=true
CREDIT_LIMIT_ENABLED=true
RECURRING_ENABLED=true
MULTI_CURRENCY_ENABLED=false

# Company
COMPANY_NAME="PT Elektrik Jaya"
COMPANY_NPWP="01.234.567.8-901.000"
```

**Configuration Categories:**

| Category | Purpose | Examples |
|----------|---------|----------|
| `company` | Company identity | Name, NPWP, address |
| `tax` | Tax settings | PPN rate, labels |
| `payment` | Payment policies | Term days, early discount |
| `credit_limit` | Credit control | Limits, warnings |
| `overdue` | Overdue handling | Grace period, reminders |
| `recurring` | Recurring docs | Frequencies, auto-post |
| `aging_buckets` | Aging reports | Period definitions |
| `document_formats` | Doc numbering | Format templates |
| `quotation` | Quotation defaults | Validity, terms |
| `fiscal_year` | Accounting period | Start month/day |
| `default_accounts` | GL mapping | Account codes |

---

## Validation

**Verification Steps:**

1. Check `config/accounting.php` has all settings
2. Verify services use `config()` not hardcoded values
3. Test environment variable overrides work
4. Confirm config caching doesn't break

**Tests:**

```php
// File: /tests/Unit/ConfigurationTest.php

it('uses configured tax rate', function () {
    config(['accounting.tax.default_rate' => 12.00]);

    $quotation = app(QuotationService::class)->create([
        'contact_id' => Contact::factory()->create()->id,
        // tax_rate not provided, should use config
    ]);

    expect($quotation->tax_rate)->toBe(12.00);
});

it('generates document numbers from format', function () {
    config(['accounting.document_formats.quotation' => 'Q-{YEAR}-{SEQ}']);

    $number = app(QuotationService::class)->generateNumber();

    expect($number)->toMatch('/^Q-\d{4}-\d+$/');
});
```

---

## References

- ADR-0006: SAK EMKM Compliance
- ADR-0007: Feature Flag System
- `/config/accounting.php`
- [Laravel Configuration](https://laravel.com/docs/12.x/configuration)

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Product Team, Backend Team
