---
adr: "0029"
title: "IDR Default Currency"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [currency, indonesian-context]
related_adrs: [0008]
related_modules: [accounting]
impact: medium
---

# ADR-0029: IDR Default Currency

## AI Agent Quick Reference

**Use this ADR when:**
- Working with monetary values
- Formatting currency for display
- Understanding decimal handling
- Implementing currency inputs

**Key takeaway:** IDR is default currency with no decimal places, stored as integer (bigInteger).

---

## Decision

Indonesian Rupiah (IDR) is the default and only currency, with no decimal places.

---

## Context

Indonesian business context:
1. All domestic transactions in IDR
2. IDR has no cents/decimal places in practice
3. Smallest practical unit is Rp 100
4. Large numbers common (millions/billions)

---

## Implementation

### Currency Configuration

```php
// config/accounting.php
'currency' => [
    'code' => 'IDR',
    'symbol' => 'Rp',
    'name' => 'Indonesian Rupiah',
    'decimal_places' => 0,
    'thousand_separator' => '.',
    'decimal_separator' => ',',
],
```

### Storage Format

```php
// All monetary values as bigInteger
$table->bigInteger('amount');              // 1500000 = Rp 1,500,000
$table->bigInteger('subtotal');
$table->bigInteger('tax_amount');
$table->bigInteger('total');
```

### Formatting Helper

```php
// app/Helpers/Currency.php
class Currency
{
    public static function format(int $amount): string
    {
        $config = config('accounting.currency');

        return $config['symbol'] . ' ' . number_format(
            $amount,
            $config['decimal_places'],
            $config['decimal_separator'],
            $config['thousand_separator']
        );
    }

    public static function formatCompact(int $amount): string
    {
        if ($amount >= 1_000_000_000) {
            return 'Rp ' . round($amount / 1_000_000_000, 1) . 'M';  // Miliar
        }
        if ($amount >= 1_000_000) {
            return 'Rp ' . round($amount / 1_000_000, 1) . 'jt';     // Juta
        }
        if ($amount >= 1_000) {
            return 'Rp ' . round($amount / 1_000, 1) . 'rb';         // Ribu
        }
        return 'Rp ' . $amount;
    }
}
```

### Blade Directive

```php
// AppServiceProvider
Blade::directive('currency', function ($expression) {
    return "<?php echo \App\Helpers\Currency::format($expression); ?>";
});

// Usage in Blade
@currency($invoice->total)  // Output: Rp 15,000,000
```

### Input Handling

```php
// Convert user input to integer
public function setAmountAttribute($value): void
{
    // Remove formatting characters
    $clean = preg_replace('/[^0-9]/', '', $value);
    $this->attributes['amount'] = (int) $clean;
}
```

### Alpine.js Money Input

```blade
<input
    type="text"
    x-data="{ value: @js($amount) }"
    x-init="$el.value = new Intl.NumberFormat('id-ID').format(value)"
    @input="value = $el.value.replace(/\D/g, '')"
    @blur="$el.value = new Intl.NumberFormat('id-ID').format(value)"
    wire:model.defer="amount"
/>
```

### Number Abbreviations

| Indonesian | English | Value |
|------------|---------|-------|
| rb (ribu) | thousand | 1,000 |
| jt (juta) | million | 1,000,000 |
| M (miliar) | billion | 1,000,000,000 |
| T (triliun) | trillion | 1,000,000,000,000 |

### Common Ranges

| Document | Typical Range |
|----------|---------------|
| Petty cash | Rp 50,000 - Rp 1,000,000 |
| Invoice | Rp 1,000,000 - Rp 100,000,000 |
| Project | Rp 50,000,000 - Rp 5,000,000,000 |

---

## References

- [ADR-0008: Integer Currency Storage](./0008-integer-currency-storage.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

