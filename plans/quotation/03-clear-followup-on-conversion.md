# Phase 3: Clear Follow-Up on Conversion

> **Type**: FIX
> **Status**: ✅ COMPLETE
> **Priority**: Medium
> **Effort**: Small (30 minutes)
> **Completed**: 2026-01-21
> **Tests**: 7 passed (19 assertions)

## Problem

When a quotation is converted to invoice, the `next_follow_up_at` field isn't cleared. Sales reps continue receiving follow-up reminders for already-converted quotations.

### Current Behavior

```php
// QuotationService::convertToInvoice() does NOT clear follow-up
// OutcomeManager::markAsWon() DOES clear follow-up

// Result: Convert without markAsWon = stale follow-ups
```

### Impact

1. Sales dashboard shows outdated follow-up tasks
2. Notification systems may alert for closed deals
3. Confusion about quotation status

## Solution

Clear follow-up data when quotation is converted to invoice.

## Implementation

### Option A: Clear in Conversion (Recommended)

```php
// app/Services/Sales/QuotationService.php

public function convertToInvoice(Quotation $quotation): ServiceResult
{
    // ... existing validation

    return DB::transaction(function () use ($quotation) {
        // ... existing invoice creation logic

        // Clear follow-up data (quotation is now closed)
        $quotation->next_follow_up_at = null;

        // Optionally auto-mark as Won if not already set
        if ($quotation->outcome === null) {
            $quotation->outcome = 'won';
            $quotation->won_reason = 'converted_to_invoice';
            $quotation->outcome_at = now();
        }

        $quotation->transitionTo(DocumentStatus::Converted);
        $quotation->save();

        // ... rest of method
    });
}
```

### Option B: Clear in QuotationConversionService

If conversion is delegated to a separate service:

```php
// app/Services/Sales/QuotationConversionService.php

public function convertToInvoice(Quotation $quotation): Invoice
{
    // ... existing conversion logic

    // After successful conversion
    $quotation->update([
        'next_follow_up_at' => null,
        'outcome' => $quotation->outcome ?? 'won',
        'outcome_at' => $quotation->outcome_at ?? now(),
    ]);

    return $invoice;
}
```

## Tests

```php
// tests/Feature/Services/Sales/QuotationConversionFollowUpTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $this->quotationService = app(\App\Services\Sales\QuotationService::class);
});

it('clears follow-up when quotation is converted', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'next_follow_up_at' => now()->addDays(3),
            'assigned_to' => 1,
        ]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh())
        ->next_follow_up_at->toBeNull()
        ->status->toBe(DocumentStatus::Converted);
});

it('auto-marks as won when converted without outcome', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'outcome' => null,
        ]);

    $this->quotationService->convertToInvoice($quotation);

    expect($quotation->fresh())
        ->outcome->toBe('won')
        ->won_reason->toBe('converted_to_invoice')
        ->outcome_at->not->toBeNull();
});

it('preserves existing outcome when converted', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'outcome' => 'won',
            'won_reason' => 'harga_kompetitif',
            'outcome_at' => now()->subHour(),
        ]);

    $originalOutcomeAt = $quotation->outcome_at;

    $this->quotationService->convertToInvoice($quotation);

    expect($quotation->fresh())
        ->outcome->toBe('won')
        ->won_reason->toBe('harga_kompetitif')
        ->outcome_at->toEqual($originalOutcomeAt);
});

it('quotation without follow-up converts normally', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'next_follow_up_at' => null,
        ]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
});
```

## Verification

```bash
# Run specific tests
php artisan test --filter=QuotationConversionFollowUp

# Check for existing converted quotations with stale follow-ups
php artisan tinker --execute="
    App\Models\Sales\Quotation::where('status', 'converted')
        ->whereNotNull('next_follow_up_at')
        ->count()
"
```

## Data Cleanup (Optional)

Clean up existing converted quotations with stale follow-ups:

```php
// One-time cleanup script or migration
Quotation::where('status', DocumentStatus::Converted)
    ->whereNotNull('next_follow_up_at')
    ->update(['next_follow_up_at' => null]);
```

## Checklist

- [x] Update `convertToInvoice()` to clear `next_follow_up_at`
- [x] Add auto-mark as Won (with reason 'converted_to_invoice')
- [x] Added 'converted_to_invoice' to WON_REASONS enum
- [x] Tests written and passing (7 tests, 19 assertions)
- [ ] Clean up existing data (optional - run if needed)

## Business Decision

**Question**: Should conversion automatically mark as "Won"?

| Option | Behavior | Pros | Cons |
|--------|----------|------|------|
| **Yes** | Auto-set outcome=won | Cleaner data, automatic | Less control |
| **No** | Keep outcome separate | Manual control | May forget to set |

**Recommendation**: Auto-mark as Won if outcome is null, preserve if already set.
