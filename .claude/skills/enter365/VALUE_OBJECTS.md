# Value Objects & Calculators

Immutable value objects and calculation patterns.

---

## Value Objects Location

```
app/Domain/Shared/ValueObjects/
├── Money.php
├── CurrencyCode.php
├── Quantity.php
├── Percentage.php
└── DateRange.php

app/Domain/Accounting/FiscalPeriods/ValueObjects/
├── ClosingProgress.php
└── ClosingChecklist.php
```

---

## Money

Currency-aware immutable money handling.

```php
use App\Domain\Shared\ValueObjects\Money;

// Creating
$money = Money::of(150000, 'IDR');
$money = Money::fromFloat(1500.00, 'IDR');

// Arithmetic (returns NEW instance)
$total = $money->add($other);
$diff = $money->subtract($other);
$scaled = $money->multiply(1.11);
$tax = $money->percentage(11);

// Comparisons
$money->greaterThan($other);
$money->lessThan($other);
$money->equals($other);

// Formatting
$money->format();  // "Rp 1.500.000"

// Currency conversion
$idr = $money->toIDR(15000);  // Convert using rate
```

**Key:** Amounts stored as integers (smallest unit, e.g., rupiah).

---

## CurrencyCode

Validated currency with formatting.

```php
use App\Domain\Shared\ValueObjects\CurrencyCode;

// Creating
$idr = CurrencyCode::IDR();
$usd = CurrencyCode::USD();
$code = CurrencyCode::fromString('EUR');

// Queries
$code->isIDR();
$code->getName();     // "Indonesian Rupiah"
$code->getSymbol();   // "Rp"

// Formatting
$code->formatMoney(1500000);  // "Rp 1.500.000"

// Conversion
$idrAmount = $code->convertTo(100, CurrencyCode::IDR(), 15000);
```

**Supported:** IDR, USD, EUR, GBP, JPY, CNY, SGD, MYR, THB, AUD, CAD, CHF, HKD, NZD, KRW, INR, PHP, VND

---

## Quantity

Unit-aware quantity handling.

```php
use App\Domain\Shared\ValueObjects\Quantity;

// Creating
$qty = Quantity::of(10.5, 'box');
$qty = Quantity::fromInteger(10, 'pcs');

// Arithmetic
$total = $qty->add($other);      // Must be same unit
$diff = $qty->subtract($other);
$scaled = $qty->multiply(2);

// Unit conversion
$pcs = $qty->toUnit('pcs', 50);  // 10.5 box × 50 = 525 pcs

// Checks
$qty->isZero();
$qty->isWholeNumber();
$qty->isSameUnit($other);

// Formatting
$qty->format();  // "10.5 box"
```

---

## Percentage

Range-validated percentage (0-100).

```php
use App\Domain\Shared\ValueObjects\Percentage;

// Creating
$rate = Percentage::of(11);
$rate = Percentage::eleven();  // Tax rate shortcut
$rate = Percentage::fromDecimal(0.11);

// Calculations
$taxAmount = $rate->calculate(1000000);  // 110000
$taxMoney = $rate->calculateMoney(1000000);  // Money object

// Conversions
$decimal = $rate->toDecimal();  // 0.11

// Arithmetic
$combined = $rate->add($other);  // Throws if > 100%

// Formatting
$rate->format();  // "11,00%"
```

---

## DateRange

Immutable date period handling.

```php
use App\Domain\Shared\ValueObjects\DateRange;

// Creating
$range = DateRange::of('2025-01-01', '2025-01-31');
$range = DateRange::thisMonth();
$range = DateRange::thisYear();
$range = DateRange::forMonth(2025, 1);
$range = DateRange::lastDays(30);

// Queries
$range->contains('2025-01-15');  // true
$range->overlaps($otherRange);
$range->isAdjacentTo($otherRange);

// Calculations
$range->days();      // 31
$range->months();    // 1
$range->isSingleDay();
$range->isFullMonth();

// Formatting
$range->format();  // "1 Jan 2025 - 31 Jan 2025"
```

---

## Calculators

### QuotationCalculator

```php
use App\Domain\Sales\Quotations\QuotationCalculator;

$calculator = app(QuotationCalculatorInterface::class);

$totals = $calculator->calculate(
    lineTotals: [100000, 50000, 25000],
    taxRate: 11.0,
    discountType: 'percentage',
    discountValue: 10.0,
    currency: 'IDR',
    exchangeRate: 1.0
);

$totals->subtotal;         // 175000
$totals->discountAmount;   // 17500
$totals->taxAmount;        // 17325
$totals->totalAmount;      // 174825
$totals->baseCurrencyTotal;
```

### InvoiceCalculator

```php
use App\Domain\Sales\Invoices\InvoiceCalculator;

$totals = $calculator->calculate(
    lineTotals: [100000, 50000],
    taxRate: 11.0,
    discountAmount: 5000,  // Pre-calculated fixed
    currency: 'IDR',
    exchangeRate: 1.0
);
```

### PurchaseOrderCalculator

Same pattern as QuotationCalculator.

---

## Calculation Sequence

### Quotation/PO (Discount Before Tax)

```
Subtotal = sum(line items)
Discount = Subtotal × discount% (or fixed)
Taxable = Subtotal - Discount
Tax = Taxable × tax_rate
Total = Taxable + Tax
```

### Invoice (Tax Before Discount)

```
Subtotal = sum(line items)
Tax = Subtotal × tax_rate
Total = Subtotal + Tax - Discount
```

---

## Helper Calculators

### DiscountCalculator

```php
use App\Domain\Sales\Quotations\DiscountCalculator;

$amount = DiscountCalculator::calculate('percentage', 10, 100000);  // 10000
$amount = DiscountCalculator::calculate('fixed', 15000, 100000);    // 15000
$amount = DiscountCalculator::calculate(null, 0, 100000);           // 0
```

### TaxCalculator

```php
use App\Domain\Sales\Quotations\TaxCalculator;

// From items
$tax = TaxCalculator::calculateFromItems($items, 11);

// Direct
$tax = TaxCalculator::calculateFromSubtotal(150000, 11);  // 16500
```

---

## Money Handling Rules

1. **Store as integers** - No floating point for money
2. **Round explicitly** - Use `(int) round()` for conversions
3. **Keep immutable** - All operations return new instances
4. **Validate early** - Constructor validates constraints
5. **Currency match** - Operations require matching currencies

---

## Creating New Value Object

```php
<?php

namespace App\Domain\YourModule\ValueObjects;

readonly class YourValueObject
{
    public function __construct(
        public int $value,
        public string $unit
    ) {
        // Validation in constructor
        if ($value < 0) {
            throw new InvalidArgumentException('Value cannot be negative');
        }
    }

    public static function of(int $value, string $unit = 'default'): self
    {
        return new self($value, $unit);
    }

    public function add(self $other): self
    {
        if ($this->unit !== $other->unit) {
            throw new InvalidArgumentException('Units must match');
        }
        return new self($this->value + $other->value, $this->unit);
    }

    public function format(): string
    {
        return "{$this->value} {$this->unit}";
    }
}
```
