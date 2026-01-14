---
adr: "0008"
title: "Integer Currency Storage for Monetary Amounts"
status: accepted
date: 2024-11-01
deciders: [Architecture Team, Accounting Advisor]
tags: [data-model, accounting, database]
related_adrs: [0002, 0006, 0011]
related_modules: [all]
impact: high
---

# ADR-0008: Integer Currency Storage for Monetary Amounts

## AI Agent Quick Reference

**Use this ADR when:**
- Creating new tables with monetary columns
- Working with price/amount calculations
- Debugging currency precision issues
- Understanding why amounts are `bigInteger`

**Key takeaway:** All monetary amounts are stored as integers in the smallest unit (cents/rupiah). A value of 1500000 represents Rp 15,000.00.

---

## Context

Enter365 handles significant financial transactions:
- Quotations and invoices (potentially billions of Rupiah)
- Manufacturing costs with detailed breakdowns
- Multi-currency transactions
- Journal entries requiring exact balance

### The Problem with Decimals

Floating-point arithmetic can introduce precision errors:

```php
// Floating point problem
$a = 0.1 + 0.2;  // = 0.30000000000000004, not 0.3

// In accounting context
$subtotal = 19.99;
$quantity = 100;
$total = $subtotal * $quantity;  // May not be exactly 1999.00
```

---

## Decision Drivers

1. **Precision** - Financial calculations must be exact
2. **Range** - Support up to billions of Rupiah
3. **Performance** - Integer math is faster
4. **Portability** - Works across all databases
5. **No Rounding Errors** - Balance sheets must balance

---

## Considered Options

### Option 1: Integer Storage (Chosen)

**Description:** Store amounts as integers in smallest currency unit

**Pros:**
- Exact precision (no floating point errors)
- Fast integer arithmetic
- Large range with `bigInteger` (up to 9 quintillion)
- Works in all databases
- Easy to understand (cents/rupiah)

**Cons:**
- Must convert for display
- Must convert from user input
- Unit must be documented

### Option 2: Decimal(15,2)

**Description:** Database decimal type with 2 decimal places

**Pros:**
- Direct representation of currency
- No conversion needed
- Human-readable in database

**Cons:**
- Database-specific behavior
- Some precision edge cases
- Slower than integers

### Option 3: String Storage

**Description:** Store as strings, parse for calculations

**Pros:**
- Exact representation
- No precision issues

**Cons:**
- Slow calculations
- Complex parsing
- Unusual pattern

---

## Decision

**Chosen option:** "Integer Storage"

All monetary amounts are stored as `bigInteger` representing the smallest currency unit. For IDR, this is Rupiah (no cents). For USD, this would be cents.

---

## Rationale

### Why Integer:

1. **Exact Arithmetic**
   ```php
   // Integer math is exact
   $subtotal = 1999_00;  // Rp 19.99 in cents (if using cents)
   $quantity = 100;
   $total = $subtotal * $quantity;  // Exactly 199900_00
   ```

2. **IDR Context**
   - Indonesian Rupiah has no decimal places in practice
   - Rp 15,000 stored as 15000_00 (if using 2 decimal precision)
   - Actually stored as 1500000 (in our system, 2 implicit decimals)

3. **Range with BigInteger**
   - `bigInteger`: -9,223,372,036,854,775,808 to 9,223,372,036,854,775,807
   - Supports transactions up to Rp 92,233,720,368,547,758
   - More than enough for any SME

4. **Balance Guarantee**
   ```php
   // Debits and credits always balance exactly
   $debit_sum = $entry->lines->sum('debit');    // Integer
   $credit_sum = $entry->lines->sum('credit');  // Integer
   $balanced = $debit_sum === $credit_sum;      // Exact comparison
   ```

---

## Consequences

### Positive

- Zero precision errors in calculations
- Fast database queries on amounts
- Exact balance sheet balancing
- Works across all databases
- Simple arithmetic operations

### Negative

- Must convert for display (divide by 100)
- Must convert from input (multiply by 100)
- Team must understand convention
- Database values less human-readable

### Neutral

- Established pattern in financial software
- Well-documented in codebase

---

## Implementation Notes

**Migration Pattern:**

```php
// File: /database/migrations/xxxx_create_quotations_table.php

Schema::create('quotations', function (Blueprint $table) {
    $table->id();

    // All amounts as bigInteger (smallest unit)
    $table->bigInteger('subtotal')->default(0);
    $table->bigInteger('discount_amount')->default(0);
    $table->bigInteger('tax_amount')->default(0);
    $table->bigInteger('total')->default(0);

    // Rates can be decimal (percentages)
    $table->decimal('tax_rate', 5, 2)->default(11.00);
    $table->decimal('discount_value', 15, 2)->default(0);

    $table->timestamps();
});
```

**Model Cast:**

```php
// File: /app/Models/Accounting/Quotation.php

class Quotation extends Model
{
    protected function casts(): array
    {
        return [
            'subtotal' => 'integer',
            'discount_amount' => 'integer',
            'tax_amount' => 'integer',
            'total' => 'integer',
            'tax_rate' => 'decimal:2',
        ];
    }
}
```

**Accessor for Display:**

```php
// File: /app/Models/Accounting/Quotation.php

/**
 * Get formatted total for display.
 */
public function getFormattedTotalAttribute(): string
{
    return 'Rp ' . number_format($this->total / 100, 2, ',', '.');
}

/**
 * Get total in base units (divide by 100).
 */
public function getTotalInRupiahAttribute(): float
{
    return $this->total / 100;
}
```

**Mutator for Input:**

```php
// File: /app/Models/Accounting/Quotation.php

/**
 * Set total from user input (multiply by 100).
 */
public function setTotalFromInputAttribute(float $value): void
{
    $this->attributes['total'] = (int) round($value * 100);
}
```

**Service Calculation:**

```php
// File: /app/Services/Accounting/QuotationService.php

public function calculateTotals(Quotation $quotation): void
{
    // All calculations in integers
    $subtotal = $quotation->items->sum(function ($item) {
        return $item->quantity * $item->unit_price; // Both integers
    });

    // Discount calculation
    $discountAmount = match ($quotation->discount_type) {
        'percentage' => (int) round($subtotal * $quotation->discount_value / 100),
        'fixed' => (int) round($quotation->discount_value * 100),
        default => 0,
    };

    $afterDiscount = $subtotal - $discountAmount;

    // Tax calculation
    $taxAmount = (int) round($afterDiscount * $quotation->tax_rate / 100);

    $total = $afterDiscount + $taxAmount;

    $quotation->update([
        'subtotal' => $subtotal,
        'discount_amount' => $discountAmount,
        'tax_amount' => $taxAmount,
        'total' => $total,
    ]);
}
```

**API Resource Transformation:**

```php
// File: /app/Http/Resources/Api/V1/QuotationResource.php

class QuotationResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'quotation_number' => $this->quotation_number,

            // Return both raw (for calculations) and formatted (for display)
            'subtotal' => $this->subtotal,
            'subtotal_formatted' => $this->formatted_subtotal,
            'total' => $this->total,
            'total_formatted' => $this->formatted_total,

            // Rates are decimals, no conversion needed
            'tax_rate' => $this->tax_rate,
        ];
    }
}
```

**Key Convention:**

```
Database Storage:  1500000    (integer)
Actual Value:      Rp 15,000.00
Conversion:        1500000 / 100 = 15000.00
```

**Visual Guide:**

| User Sees | Stored Value | Conversion |
|-----------|--------------|------------|
| Rp 100.00 | 10000 | 10000 / 100 |
| Rp 15,000.00 | 1500000 | 1500000 / 100 |
| Rp 1,000,000.00 | 100000000 | 100000000 / 100 |
| Rp 50,000,000.00 | 5000000000 | 5000000000 / 100 |

---

## Validation

**Verification Steps:**

1. Check migrations use `bigInteger` for amounts
2. Verify models cast amounts to `integer`
3. Test calculations produce exact results
4. Confirm journal entries balance exactly

**Tests:**

```php
// File: /tests/Unit/Services/QuotationServiceTest.php

it('calculates totals exactly', function () {
    $quotation = Quotation::factory()->create([
        'tax_rate' => 11.00,
    ]);

    QuotationItem::factory()->create([
        'quotation_id' => $quotation->id,
        'quantity' => 3,
        'unit_price' => 10000_00, // Rp 100.00 each
    ]);

    app(QuotationService::class)->calculateTotals($quotation);

    $quotation->refresh();

    expect($quotation->subtotal)->toBe(30000_00);      // 3 * 100.00
    expect($quotation->tax_amount)->toBe(3300_00);     // 11% of 300.00
    expect($quotation->total)->toBe(33300_00);         // 300.00 + 33.00
});

it('balances journal entries exactly', function () {
    $entry = JournalEntry::factory()
        ->has(JournalEntryLine::factory()->count(2))
        ->create();

    $debits = $entry->lines->sum('debit');
    $credits = $entry->lines->sum('credit');

    expect($debits)->toBe($credits); // Exact integer comparison
});
```

---

## References

- [Money Pattern (Fowler)](https://martinfowler.com/eaaCatalog/money.html)
- ADR-0002: PostgreSQL Database
- ADR-0006: SAK EMKM Compliance
- ADR-0011: Double-Entry Bookkeeping

---

## Metadata

**Last Updated:** 2024-11-26
**Author:** Architecture Team
**Reviewers:** Accounting Advisor, Backend Team
