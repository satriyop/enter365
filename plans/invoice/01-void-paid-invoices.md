# Phase 1: Prevent Voiding Paid Invoices

> **Type**: DECISION + FIX
> **Status**: 📋 AWAITING DECISION
> **Priority**: High
> **Effort**: Small (Option A) / Large (Option B)

## Problem

Paid invoices can currently be voided, which creates accounting inconsistencies:

1. Customer pays invoice → AR cleared to zero
2. User voids invoice → System reverses AR journal
3. Result: **Negative AR balance** (impossible in real accounting)

### Current Flow (Problematic)

```
Invoice Posted:
  Dr. Accounts Receivable    1,000,000
  Cr. Revenue                          1,000,000

Payment Received:
  Dr. Cash/Bank              1,000,000
  Cr. Accounts Receivable              1,000,000

  AR Balance: 0

Invoice Voided (PROBLEM):
  Dr. Revenue                1,000,000
  Cr. Accounts Receivable              1,000,000

  AR Balance: -1,000,000 ← NEGATIVE!
```

### User Story

> As an accountant, I need the system to prevent voiding paid invoices, so that our AR balance remains accurate and audit-compliant.

## Current Code Location

```php
// app/Domain/Sales/Invoices/InvoiceStateMachine.php:184-186
DocumentStatus::Paid->value => [
    DocumentStatus::Cancelled->value,  // ← This allows Paid → Cancelled
],
```

```php
// app/Services/Sales/InvoiceService.php:223-249
public function void(Invoice $invoice, string $reason): ServiceResult
{
    // No check for paid status before voiding
    if (! $invoice->stateMachine()->canCancel()) {
        throw StateTransitionException::actionNotAvailable(...);
    }
    // Reverses journal...
}
```

---

## Options

### Option A: Block Voiding Paid Invoices (Recommended)

**Effort**: Small (2-3 hours)

Simply prevent the transition. If customer needs refund, manual process outside system.

#### Implementation

```php
// app/Domain/Sales/Invoices/InvoiceStateMachine.php

protected function getTransitions(): array
{
    return [
        // ...
        DocumentStatus::Paid->value => [
            // REMOVE: DocumentStatus::Cancelled->value,
            // Paid invoices cannot be cancelled - use Credit Memo instead
        ],
        // ...
    ];
}
```

```php
// app/Services/Sales/InvoiceService.php

public function void(Invoice $invoice, string $reason): ServiceResult
{
    // Add explicit check with helpful message
    if ($invoice->status === DocumentStatus::Paid) {
        throw new \InvalidArgumentException(
            'Faktur yang sudah lunas tidak dapat dibatalkan. Gunakan Credit Memo untuk pengembalian.'
        );
    }

    // ... rest of method
}
```

#### Pros
- Simple to implement
- Prevents accounting issues
- Clear audit trail

#### Cons
- No in-system refund mechanism
- Manual process for returns

---

### Option B: Implement Credit Memo + Refund Flow

**Effort**: Large (1-2 weeks)

Full accounting-compliant solution with proper refund workflow.

#### New Workflow

```
Paid Invoice Needs Refund:
  1. Create Credit Memo (reverses revenue)
  2. Apply Credit Memo to AR
  3. Process Refund (returns cash)

Credit Memo Created:
  Dr. Revenue                1,000,000
  Cr. Accounts Receivable              1,000,000
  (or Cr. Customer Credit)

Refund Processed:
  Dr. Accounts Receivable    1,000,000
  (or Dr. Customer Credit)
  Cr. Cash/Bank                        1,000,000
```

#### New Components Required

1. **CreditMemo Model & Migration**
   - `credit_memos` table
   - Links to original invoice
   - Line items mirror invoice

2. **CreditMemoService**
   - `createFromInvoice(Invoice $invoice): CreditMemo`
   - `apply(CreditMemo $memo): void`
   - Journal entry creation

3. **RefundService**
   - `processRefund(CreditMemo $memo, int $amount): Payment`
   - Cash outflow journal

4. **API Endpoints**
   - `POST /invoices/{id}/credit-memo`
   - `POST /credit-memos/{id}/apply`
   - `POST /credit-memos/{id}/refund`

5. **State Machine for CreditMemo**
   - Draft → Applied → Refunded

#### Pros
- Industry-standard accounting
- Full audit trail
- Supports partial refunds

#### Cons
- Significant development effort
- New model/service/API layer
- UI work needed

---

### Option C: Keep Current + Add Warning (Not Recommended)

Keep existing behavior but add warning in UI/API response.

#### Implementation

```php
// app/Http/Controllers/Api/V1/InvoiceController.php

public function void(VoidInvoiceRequest $request, Invoice $invoice): JsonResponse
{
    $warnings = [];

    if ($invoice->status === DocumentStatus::Paid) {
        $warnings[] = [
            'code' => 'VOIDING_PAID_INVOICE',
            'message' => 'Warning: Voiding a paid invoice will create negative AR. Consider Credit Memo instead.',
        ];
    }

    $result = $this->invoiceService->void($invoice, $request->validated('reason'));

    return response()->json([
        'data' => new InvoiceResource($result->getData()),
        'warnings' => $warnings,
    ]);
}
```

#### Pros
- No behavior change
- User warned

#### Cons
- Still allows bad accounting
- Warning easily ignored
- Audit concerns remain

---

## Recommendation

**Start with Option A**, implement Option B in future sprint if refunds are common.

Option A is:
- Quick to implement
- Prevents immediate accounting issues
- Can be upgraded to Option B later
- Industry-standard behavior (many systems don't allow voiding paid invoices)

---

## Tests (For Option A)

```php
// tests/Feature/Services/Sales/InvoiceVoidPaidTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $this->service = app(\App\Services\Sales\InvoiceService::class);
});

it('cannot void paid invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Paid,
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
        ]);

    $this->service->void($invoice, 'Test void');
})->throws(\InvalidArgumentException::class, 'sudah lunas');

it('can void sent invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->sent()
        ->create();

    $result = $this->service->void($invoice, 'Customer cancelled order');

    expect($result->isSuccess())->toBeTrue();
    expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('can void partial invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Partial,
            'total_amount' => 1000000,
            'paid_amount' => 500000,
        ]);

    $result = $this->service->void($invoice, 'Partial payment returned');

    expect($result->isSuccess())->toBeTrue();
    expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('can void overdue invoice', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Overdue,
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

    $result = $this->service->void($invoice, 'Write-off bad debt');

    expect($result->isSuccess())->toBeTrue();
    expect($invoice->fresh()->status)->toBe(DocumentStatus::Cancelled);
});

it('state machine canCancel returns false for paid', function () {
    $invoice = Invoice::factory()->create([
        'status' => DocumentStatus::Paid,
    ]);

    expect($invoice->stateMachine()->canCancel())->toBeFalse();
});

// API Test
it('returns 422 when trying to void paid invoice via API', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory(), 'items')
        ->create([
            'status' => DocumentStatus::Paid,
            'total_amount' => 1000000,
            'paid_amount' => 1000000,
        ]);

    $response = $this->postJson("/api/v1/invoices/{$invoice->id}/void", [
        'reason' => 'Attempting to void paid',
    ]);

    $response->assertUnprocessable();
    expect($invoice->fresh()->status)->toBe(DocumentStatus::Paid);
});
```

---

## Verification

```bash
# Run tests
php artisan test --filter=InvoiceVoidPaid

# Check for existing voided paid invoices (data quality)
php artisan tinker --execute="
    \$count = App\Models\Sales\Invoice::where('status', 'cancelled')
        ->where('paid_amount', '>', 0)
        ->count();
    echo \"Voided invoices with payments: \$count\n\";
"
```

---

## Checklist (Option A)

- [ ] Business decision confirmed
- [ ] State machine transition removed
- [ ] Service layer check added
- [ ] Indonesian error message
- [ ] Tests written and passing
- [ ] API returns 422 with helpful message
- [ ] API docs updated (`php artisan scramble:export --path=api.json`)

---

## Decision Required

Please confirm:

1. **Which option?** A (Block) / B (Credit Memo) / C (Warning)
2. **If Option A**: Are manual refunds acceptable?
3. **If Option B**: Should we plan for future sprint?
