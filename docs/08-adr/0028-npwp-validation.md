---
adr: "0028"
title: "NPWP Validation"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [tax, indonesian-context, validation]
related_adrs: [0026]
related_modules: [accounting]
impact: medium
---

# ADR-0028: NPWP Validation

## AI Agent Quick Reference

**Use this ADR when:**
- Validating contact tax IDs
- Working with tax invoice (Faktur Pajak)
- Implementing supplier/customer forms
- Building tax reports

**Key takeaway:** NPWP is 15-digit tax ID (XX.XXX.XXX.X-XXX.XXX), required for tax invoices.

---

## Decision

Validate and format NPWP (Nomor Pokok Wajib Pajak) according to Indonesian tax authority format.

---

## Context

NPWP (Indonesian Tax ID):
1. Required for issuing tax invoices (Faktur Pajak)
2. 15 digits with specific format
3. Contains tax office code and registration number
4. Used for tax reporting to DJP

---

## Implementation

### NPWP Format

```
XX.XXX.XXX.X-XXX.XXX

Position 1-2:   Tax identity type
Position 3-5:   Registration number
Position 6-8:   Registration number (continued)
Position 9:     Check digit
Position 10-12: Tax office code (KPP)
Position 13-15: Branch code (000 = main, 001+ = branch)
```

### Validation Rule

```php
// app/Rules/Npwp.php
class Npwp implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Remove formatting
        $npwp = preg_replace('/[^0-9]/', '', $value);

        // Must be 15 digits
        if (strlen($npwp) !== 15) {
            $fail('NPWP must be 15 digits.');
            return;
        }

        // Optional: Validate check digit (position 9)
        if (!$this->validateCheckDigit($npwp)) {
            $fail('Invalid NPWP check digit.');
        }
    }

    private function validateCheckDigit(string $npwp): bool
    {
        // Indonesian NPWP uses modulus 10 check
        $weights = [1, 2, 1, 2, 1, 2, 1, 2];
        $sum = 0;

        for ($i = 0; $i < 8; $i++) {
            $product = (int) $npwp[$i] * $weights[$i];
            $sum += $product > 9 ? $product - 9 : $product;
        }

        $checkDigit = (10 - ($sum % 10)) % 10;

        return (int) $npwp[8] === $checkDigit;
    }
}
```

### Database Storage

```php
// Store raw digits, format on display
$table->string('npwp', 15)->nullable();

// Contact model
protected $casts = [
    'npwp' => NpwpCast::class,
];
```

### NPWP Cast

```php
// app/Casts/NpwpCast.php
class NpwpCast implements CastsAttributes
{
    public function get($model, $key, $value, $attributes): ?string
    {
        if (!$value) return null;

        // Format: XX.XXX.XXX.X-XXX.XXX
        return sprintf(
            '%s.%s.%s.%s-%s.%s',
            substr($value, 0, 2),
            substr($value, 2, 3),
            substr($value, 5, 3),
            substr($value, 8, 1),
            substr($value, 9, 3),
            substr($value, 12, 3)
        );
    }

    public function set($model, $key, $value, $attributes): ?string
    {
        if (!$value) return null;

        // Store raw digits only
        return preg_replace('/[^0-9]/', '', $value);
    }
}
```

### Form Input

```blade
<x-input
    label="NPWP"
    wire:model="npwp"
    placeholder="XX.XXX.XXX.X-XXX.XXX"
    x-mask="99.999.999.9-999.999"
/>
```

### Validation Usage

```php
// In Form Request
public function rules(): array
{
    return [
        'npwp' => ['nullable', new Npwp()],
    ];
}
```

### Tax Invoice Requirements

| Has NPWP | Can Issue | Document Type |
|----------|-----------|---------------|
| Yes | Tax Invoice | Faktur Pajak |
| No | Regular Invoice | Non-PKP Invoice |

---

## References

- [ADR-0026: PPN VAT Calculation](./0026-ppn-vat-calculation.md)
- [Indonesian Context](../02-domain/indonesian-context.md)

