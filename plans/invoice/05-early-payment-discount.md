# Phase 5: Early Payment Discount Workflow

> **Type**: NEW FEATURE
> **Status**: 📋 DOCUMENTED
> **Priority**: Medium
> **Effort**: Medium (1-2 days)
> **Sprint**: Future

## Problem

Invoice model has early payment discount fields:
- `early_discount_percent` (e.g., 2%)
- `early_discount_days` (e.g., 10 days)
- `early_discount_deadline` (calculated date)
- `early_discount_amount` (calculated amount)

Helper methods exist:
- `hasEarlyPaymentDiscount()` - checks if discount available
- `calculateEarlyDiscountAmount()` - computes discount
- `getEarlyPaymentTotal()` - returns discounted total

**But the workflow is not implemented:**
- Payment validation ignores discount
- No journal entry for discount
- No UI to apply discount

### Current Payment Validation

```php
// app/Services/Shared/PaymentService.php:206-208

if ($amount > $invoice->getOutstandingAmount()) {
    throw new InvalidArgumentException('Jumlah pembayaran melebihi sisa tagihan.');
}

// getOutstandingAmount() = total_amount - paid_amount
// Does NOT consider early discount!
```

### User Story

> As an accounts receivable clerk, I want to accept early payments with automatic discount application, so I don't have to manually calculate and adjust the invoice.

---

## Proposed Solution

### Option A: Auto-Apply Discount (Simpler)

If payment made within deadline AND payment = discounted total, auto-apply.

```
Invoice Total: 1,000,000
Discount (2%): 20,000
Discounted Total: 980,000

Customer Pays: 980,000 (within deadline)
→ System accepts as full payment
→ Creates discount journal entry
→ Marks invoice as Paid
```

### Option B: Manual Confirmation (More Control)

Clerk explicitly chooses to apply discount when recording payment.

```
Payment form shows:
☐ Apply early payment discount (2% = Rp 20,000)
  Discounted total: Rp 980,000
```

### Option C: Separate Discount Line

Discount recorded as separate adjustment, not affecting invoice total.

---

## Business Questions

1. **Should discount auto-apply** if customer pays the discounted amount within deadline?
2. **What if customer pays full amount** within deadline? (Overpayment or no discount?)
3. **What if customer pays partial + within deadline?** (Proportional discount?)
4. **Accounting treatment:**
   - Sales Discount (contra-revenue)?
   - Write-off adjustment?
   - Which account code?

---

## Proposed Implementation (Option A)

### 1. Update Payment Validation

```php
// app/Services/Shared/PaymentService.php

private function validateInvoicePayment(Invoice $invoice, int $amount): void
{
    if (! $this->canReceivePayment($invoice)) {
        throw new InvalidArgumentException('Faktur tidak dalam status yang bisa dibayar.');
    }

    // Check against both regular and discounted amounts
    $outstanding = $invoice->getOutstandingAmount();
    $discountedOutstanding = $invoice->qualifiesForEarlyDiscount()
        ? $outstanding - $invoice->calculateEarlyDiscountAmount()
        : $outstanding;

    if ($amount > $outstanding) {
        throw new InvalidArgumentException('Jumlah pembayaran melebihi sisa tagihan.');
    }

    // Accept discounted amount as valid
    // This will be handled in updatePayableAfterPayment
}
```

### 2. Detect and Apply Discount

```php
// app/Services/Shared/PaymentService.php

private function updatePayableAfterPayment(Model $payable, Payment $payment): void
{
    $previousPaidAmount = $payable->paid_amount;

    // Detect early payment discount scenario
    $discountApplied = 0;
    if ($payable instanceof Invoice && $this->shouldApplyEarlyDiscount($payable, $payment)) {
        $discountApplied = $payable->calculateEarlyDiscountAmount();
        $this->createDiscountJournalEntry($payable, $discountApplied);
    }

    $payable->paid_amount += $payment->amount + $discountApplied;
    $payable->save();

    // ... rest of method
}

private function shouldApplyEarlyDiscount(Invoice $invoice, Payment $payment): bool
{
    // Must qualify for discount
    if (! $invoice->qualifiesForEarlyDiscount()) {
        return false;
    }

    $outstanding = $invoice->getOutstandingAmount();
    $discountAmount = $invoice->calculateEarlyDiscountAmount();
    $discountedOutstanding = $outstanding - $discountAmount;

    // Payment amount matches discounted total (within tolerance)
    $tolerance = 100; // 1 rupiah rounding
    return abs($payment->amount - $discountedOutstanding) <= $tolerance;
}

private function createDiscountJournalEntry(Invoice $invoice, int $discountAmount): void
{
    // Fail-fast: validate discount account exists
    $discountAccount = $this->accountLookup->findByCodeOrFail(
        '4-2001', // Sales Discount (contra-revenue)
        'early payment discount'
    );

    $receivableAccount = $invoice->receivableAccount
        ?? $this->accountLookup->findByCodeOrFail('1-1100', 'discount');

    $this->journalService->createEntry([
        'entry_date' => now()->toDateString(),
        'description' => "Diskon pembayaran awal: {$invoice->invoice_number}",
        'reference' => $invoice->invoice_number,
        'source_type' => 'invoice_discount',
        'source_id' => $invoice->id,
        'lines' => [
            [
                'account_id' => $discountAccount->id,
                'description' => 'Diskon pembayaran awal',
                'debit' => $discountAmount,
                'credit' => 0,
            ],
            [
                'account_id' => $receivableAccount->id,
                'description' => 'Pengurangan piutang (diskon)',
                'debit' => 0,
                'credit' => $discountAmount,
            ],
        ],
    ], autoPost: true);
}
```

### 3. Update InvoicePaymentService

```php
// app/Services/Sales/InvoicePaymentService.php

/**
 * Get payment options for an invoice.
 *
 * Returns both full and discounted payment options.
 */
public function getPaymentOptions(Invoice $invoice): array
{
    $outstanding = $invoice->getOutstandingAmount();

    $options = [
        'full_amount' => $outstanding,
        'early_discount_available' => $invoice->hasEarlyPaymentDiscount(),
    ];

    if ($invoice->hasEarlyPaymentDiscount()) {
        $discount = $invoice->calculateEarlyDiscountAmount();
        $options['discount_percent'] = $invoice->early_discount_percent;
        $options['discount_amount'] = $discount;
        $options['discounted_amount'] = $outstanding - $discount;
        $options['discount_deadline'] = $invoice->early_discount_deadline->toDateString();
        $options['days_remaining'] = $invoice->early_discount_deadline->diffInDays(now());
    }

    return $options;
}
```

### 4. API Endpoint for Payment Options

```php
// app/Http/Controllers/Api/V1/InvoiceController.php

/**
 * Get payment options for invoice.
 *
 * Returns available payment amounts including early discount if applicable.
 */
public function paymentOptions(Invoice $invoice): JsonResponse
{
    $paymentService = app(InvoicePaymentService::class);

    return response()->json([
        'data' => $paymentService->getPaymentOptions($invoice),
    ]);
}
```

```php
// routes/api.php

Route::get('invoices/{invoice}/payment-options', [InvoiceController::class, 'paymentOptions']);
```

---

## Tests

```php
// tests/Feature/Services/Sales/InvoiceEarlyDiscountTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Shared\PaymentService;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $this->paymentService = app(PaymentService::class);
});

it('accepts discounted payment and marks as paid', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'early_discount_percent' => 2.00,
            'early_discount_days' => 10,
            'early_discount_deadline' => now()->addDays(5),
        ]);

    // Pay discounted amount (980,000)
    $payment = $this->paymentService->create([
        'type' => 'receive',
        'payment_date' => now(),
        'amount' => 980000,
        'invoice_id' => $invoice->id,
        'cash_account_id' => 1, // Assume exists
        'contact_id' => $invoice->contact_id,
    ]);

    $invoice->refresh();

    expect($invoice)
        ->status->toBe(DocumentStatus::Paid)
        ->paid_amount->toBe(1000000); // Includes discount
});

it('creates discount journal entry when early discount applied', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'total_amount' => 1000000,
            'early_discount_percent' => 2.00,
            'early_discount_deadline' => now()->addDays(5),
        ]);

    $this->paymentService->create([
        'type' => 'receive',
        'payment_date' => now(),
        'amount' => 980000,
        'invoice_id' => $invoice->id,
        'cash_account_id' => 1,
        'contact_id' => $invoice->contact_id,
    ]);

    // Check discount journal was created
    $discountJournal = \App\Models\Accounting\JournalEntry::where('source_type', 'invoice_discount')
        ->where('source_id', $invoice->id)
        ->first();

    expect($discountJournal)->not->toBeNull();
    expect($discountJournal->getTotalDebit())->toBe(20000);
});

it('does not apply discount if payment is full amount', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'total_amount' => 1000000,
            'early_discount_percent' => 2.00,
            'early_discount_deadline' => now()->addDays(5),
        ]);

    $this->paymentService->create([
        'type' => 'receive',
        'payment_date' => now(),
        'amount' => 1000000, // Full amount, not discounted
        'invoice_id' => $invoice->id,
        'cash_account_id' => 1,
        'contact_id' => $invoice->contact_id,
    ]);

    $invoice->refresh();

    expect($invoice->paid_amount)->toBe(1000000);

    // No discount journal
    $discountJournal = \App\Models\Accounting\JournalEntry::where('source_type', 'invoice_discount')
        ->where('source_id', $invoice->id)
        ->first();

    expect($discountJournal)->toBeNull();
});

it('does not apply discount if deadline passed', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'total_amount' => 1000000,
            'early_discount_percent' => 2.00,
            'early_discount_deadline' => now()->subDays(1), // Expired
        ]);

    expect($invoice->hasEarlyPaymentDiscount())->toBeFalse();
});

it('returns payment options via API', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create([
            'total_amount' => 1000000,
            'early_discount_percent' => 2.00,
            'early_discount_days' => 10,
            'early_discount_deadline' => now()->addDays(5),
        ]);

    $response = $this->getJson("/api/v1/invoices/{$invoice->id}/payment-options");

    $response->assertOk()
        ->assertJsonPath('data.full_amount', 1000000)
        ->assertJsonPath('data.early_discount_available', true)
        ->assertJsonPath('data.discount_amount', 20000)
        ->assertJsonPath('data.discounted_amount', 980000);
});
```

---

## Account Setup Required

Add to chart of accounts if not exists:

| Code | Name | Type |
|------|------|------|
| 4-2001 | Diskon Penjualan | Contra-Revenue |

---

## Checklist

- [ ] Business decision confirmed (auto vs manual)
- [ ] Payment validation updated
- [ ] Discount detection logic added
- [ ] Discount journal entry created
- [ ] Account code configured (4-2001)
- [ ] Payment options endpoint added
- [ ] API Resource includes discount info
- [ ] Tests written and passing
- [ ] API docs exported
- [ ] UI updated (if applicable)

---

## Future Enhancements

- Partial discount for partial early payment
- Multiple discount tiers (2/10, 1/20, net 30)
- Customer-specific discount overrides
- Discount reporting/analytics
