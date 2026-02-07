# Phase 6: Multi-Currency Payment Handling

> **Type**: NEW FEATURE
> **Status**: 📋 DOCUMENTED
> **Priority**: Medium
> **Effort**: Large (1 week+)
> **Sprint**: Future

## Problem

Invoice supports multi-currency:
- `currency` (e.g., 'USD', 'EUR', 'SGD')
- `exchange_rate` (rate to IDR at invoice date)
- `base_currency_total` (total converted to IDR)

However, payment handling is unclear:
- What currency is `Payment.amount` stored in?
- Can customer pay in different currency than invoice?
- How to handle exchange rate differences?
- Where do FX gains/losses go?

### Current Code Gap

```php
// app/Services/Shared/PaymentService.php:206-208

if ($amount > $invoice->getOutstandingAmount()) {
    throw new InvalidArgumentException('Jumlah pembayaran melebihi sisa tagihan.');
}

// What if invoice is in USD but payment is in IDR?
// getOutstandingAmount() returns total_amount - paid_amount
// But in which currency?
```

### User Story

> As an accountant handling international transactions, I want to receive payments in the customer's currency and properly record exchange rate differences, so our books reflect actual foreign exchange gains or losses.

---

## Business Questions

1. **Can payments be in different currency than invoice?**
   - Some systems: Payment must match invoice currency
   - Some systems: Payment can be in any currency, converted at spot rate

2. **When is exchange rate determined?**
   - At invoice date (locked)
   - At payment date (spot rate)
   - Average rate

3. **How to handle FX gain/loss?**
   - Realized gain/loss on payment
   - Which account codes?

4. **What about partial payments?**
   - Pro-rata FX gain/loss per payment?
   - Calculate at final payment?

---

## Proposed Solution

### Option A: Same Currency Only (Simpler)

Payment must be in same currency as invoice. FX conversion happens before creating payment.

```
Invoice: USD 1,000 @ 15,500 = IDR 15,500,000
Customer wires USD 1,000
Bank converts to IDR at spot rate (15,600)
Payment recorded: IDR 15,600,000
FX Gain: IDR 100,000
```

### Option B: Multi-Currency Payments (Complex)

Payment stored in original currency. System tracks both currencies.

```
Invoice: USD 1,000
Payment: USD 1,000 @ 15,600 (spot rate)
System calculates:
  - Invoice booked @ 15,500 = IDR 15,500,000
  - Payment received @ 15,600 = IDR 15,600,000
  - FX Gain = IDR 100,000
```

---

## Option A Implementation (Recommended)

### 1. Add Payment Currency Fields

```php
// database/migrations/xxxx_add_currency_to_payments.php

public function up(): void
{
    Schema::table('payments', function (Blueprint $table) {
        $table->string('currency', 3)->default('IDR')->after('amount');
        $table->decimal('exchange_rate', 12, 4)->default(1)->after('currency');
        $table->bigInteger('base_currency_amount')->nullable()->after('exchange_rate');
    });
}
```

### 2. Update Payment Model

```php
// app/Models/Shared/Payment.php

protected $fillable = [
    // ... existing
    'currency',
    'exchange_rate',
    'base_currency_amount',
];

protected function casts(): array
{
    return [
        // ... existing
        'exchange_rate' => 'decimal:4',
        'base_currency_amount' => 'integer',
    ];
}

/**
 * Calculate base currency amount.
 */
public function calculateBaseCurrencyAmount(): int
{
    if ($this->currency === 'IDR') {
        return $this->amount;
    }

    return (int) round($this->amount * $this->exchange_rate);
}
```

### 3. Update Payment Service

```php
// app/Services/Shared/PaymentService.php

public function create(array $data): Payment
{
    return DB::transaction(function () use ($data) {
        // ... existing validation

        // Handle currency conversion
        $currency = $data['currency'] ?? 'IDR';
        $exchangeRate = $data['exchange_rate'] ?? 1;
        $amount = $data['amount'];
        $baseCurrencyAmount = $currency === 'IDR'
            ? $amount
            : (int) round($amount * $exchangeRate);

        // Validate against invoice in same currency
        if ($payable instanceof Invoice && $payable->currency !== $currency) {
            // Convert payment to invoice currency for comparison
            $paymentInInvoiceCurrency = $this->convertCurrency(
                $amount,
                $currency,
                $payable->currency,
                $exchangeRate
            );

            if ($paymentInInvoiceCurrency > $payable->getOutstandingAmount()) {
                throw new InvalidArgumentException(
                    "Pembayaran ({$currency} {$amount}) melebihi sisa tagihan ({$payable->currency} {$payable->getOutstandingAmount()})."
                );
            }
        }

        $payment = Payment::create([
            ...$data,
            'currency' => $currency,
            'exchange_rate' => $exchangeRate,
            'base_currency_amount' => $baseCurrencyAmount,
            // ...
        ]);

        // Handle FX gain/loss if applicable
        if ($payable instanceof Invoice && $payable->currency !== 'IDR') {
            $this->recordFxGainLoss($payable, $payment);
        }

        // ... rest of method
    });
}

private function recordFxGainLoss(Invoice $invoice, Payment $payment): void
{
    // Invoice was booked at invoice_date rate
    $bookedRate = $invoice->exchange_rate;
    $bookedAmount = (int) round($payment->amount * $bookedRate);

    // Payment received at payment_date rate
    $receivedRate = $payment->exchange_rate;
    $receivedAmount = (int) round($payment->amount * $receivedRate);

    $fxDifference = $receivedAmount - $bookedAmount;

    if ($fxDifference === 0) {
        return;
    }

    $fxAccount = $this->accountLookup->findByCodeOrFail(
        config($fxDifference > 0 ? 'accounting.default_accounts.foreign_exchange_gain' : 'accounting.default_accounts.foreign_exchange_loss'), // 4-2004 / 5-3006
        'FX gain/loss'
    );

    $receivableAccount = $invoice->receivableAccount
        ?? $this->accountLookup->findByCodeOrFail('1-1100', 'FX');

    $this->journalService->createEntry([
        'entry_date' => $payment->payment_date->toDateString(),
        'description' => "Selisih kurs: {$invoice->invoice_number}",
        'reference' => $payment->payment_number,
        'source_type' => 'fx_gain_loss',
        'source_id' => $payment->id,
        'lines' => $fxDifference > 0
            ? [
                // FX Gain: Credit income
                ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => abs($fxDifference)],
                ['account_id' => $fxAccount->id, 'debit' => abs($fxDifference), 'credit' => 0],
            ]
            : [
                // FX Loss: Debit expense
                ['account_id' => $fxAccount->id, 'debit' => abs($fxDifference), 'credit' => 0],
                ['account_id' => $receivableAccount->id, 'debit' => 0, 'credit' => abs($fxDifference)],
            ],
    ], autoPost: true);
}
```

### 4. Update Payment Validation

```php
// app/Services/Shared/PaymentService.php

private function validateInvoicePayment(Invoice $invoice, int $amount, string $currency = 'IDR'): void
{
    if (! $this->canReceivePayment($invoice)) {
        throw new InvalidArgumentException('Faktur tidak dalam status yang bisa dibayar.');
    }

    // Convert to invoice currency if needed
    $amountInInvoiceCurrency = $amount;
    if ($currency !== $invoice->currency) {
        // This would need exchange rate lookup
        throw new InvalidArgumentException(
            "Pembayaran harus dalam mata uang yang sama dengan faktur ({$invoice->currency})."
        );
    }

    if ($amountInInvoiceCurrency > $invoice->getOutstandingAmount()) {
        throw new InvalidArgumentException('Jumlah pembayaran melebihi sisa tagihan.');
    }
}
```

### 5. Update API Request

```php
// app/Http/Requests/Api/V1/StorePaymentRequest.php

public function rules(): array
{
    return [
        // ... existing
        'currency' => ['sometimes', 'string', 'size:3'],
        'exchange_rate' => ['required_unless:currency,IDR', 'numeric', 'min:0.0001'],
    ];
}
```

---

## Account Setup Required

Add to chart of accounts if not exists:

| Code | Name | Type | Config Key |
|------|------|------|------------|
| 4-2004 | Keuntungan Selisih Kurs | Other Revenue | `foreign_exchange_gain` |
| 4-2005 | Keuntungan Selisih Kurs Belum Direalisasi | Other Revenue | `unrealized_fx_gain` |
| 5-3006 | Kerugian Selisih Kurs | Other Expense | `foreign_exchange_loss` |
| 5-3007 | Kerugian Selisih Kurs Belum Direalisasi | Other Expense | `unrealized_fx_loss` |

---

## Tests

```php
// tests/Feature/Services/Shared/PaymentMultiCurrencyTest.php

<?php

declare(strict_types=1);

use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Shared\PaymentService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('records FX gain when spot rate higher than booked', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'currency' => 'USD',
            'exchange_rate' => 15500, // Booked at 15,500
            'total_amount' => 1000_00, // USD 1,000 (in cents)
            'base_currency_total' => 15500_000_00,
        ]);

    // Payment at higher rate = FX gain
    $payment = app(PaymentService::class)->create([
        'type' => 'receive',
        'invoice_id' => $invoice->id,
        'amount' => 1000_00, // USD 1,000
        'currency' => 'USD',
        'exchange_rate' => 15600, // Spot rate 15,600
        // ...
    ]);

    // Check FX gain journal created
    $fxJournal = \App\Models\Accounting\JournalEntry::where('source_type', 'fx_gain_loss')
        ->where('source_id', $payment->id)
        ->first();

    expect($fxJournal)->not->toBeNull();
    // Gain = (15,600 - 15,500) * 1,000 = 100,000
    expect($fxJournal->getTotalDebit())->toBe(100_000_00);
});

it('records FX loss when spot rate lower than booked', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'currency' => 'USD',
            'exchange_rate' => 15500,
            'total_amount' => 1000_00,
        ]);

    $payment = app(PaymentService::class)->create([
        'type' => 'receive',
        'invoice_id' => $invoice->id,
        'amount' => 1000_00,
        'currency' => 'USD',
        'exchange_rate' => 15400, // Lower rate = loss
        // ...
    ]);

    // Loss = (15,500 - 15,400) * 1,000 = 100,000
    $fxJournal = \App\Models\Accounting\JournalEntry::where('source_type', 'fx_gain_loss')
        ->where('source_id', $payment->id)
        ->first();

    expect($fxJournal)->not->toBeNull();
});

it('rejects payment in different currency than invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'currency' => 'USD',
            'total_amount' => 1000_00,
        ]);

    app(PaymentService::class)->create([
        'type' => 'receive',
        'invoice_id' => $invoice->id,
        'amount' => 15500_000_00, // IDR amount
        'currency' => 'IDR', // Different currency
        // ...
    ]);
})->throws(\InvalidArgumentException::class, 'mata uang yang sama');

it('handles IDR invoice with IDR payment normally', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'currency' => 'IDR',
            'total_amount' => 1000000,
        ]);

    $payment = app(PaymentService::class)->create([
        'type' => 'receive',
        'invoice_id' => $invoice->id,
        'amount' => 1000000,
        'currency' => 'IDR',
        // ...
    ]);

    // No FX journal for same-currency
    $fxJournal = \App\Models\Accounting\JournalEntry::where('source_type', 'fx_gain_loss')
        ->where('source_id', $payment->id)
        ->first();

    expect($fxJournal)->toBeNull();
});
```

---

## Checklist

- [ ] Business decision confirmed
- [ ] Migration for payment currency fields
- [ ] Payment model updated
- [ ] Payment service currency handling
- [ ] FX gain/loss journal creation
- [ ] Account codes configured (4-2004, 4-2005, 5-3006, 5-3007 via config/accounting.php)
- [ ] API validation updated
- [ ] Tests written and passing
- [ ] API docs exported
- [ ] Exchange rate source configured (manual/API)

---

## Future Enhancements

- Real-time exchange rate API integration
- Unrealized FX gain/loss on open invoices
- Multi-currency AR aging report
- Currency revaluation batch job
- Bank account currency matching
