# Phase 2: Enforce Variant Selection Before Conversion

> **Type**: FIX
> **Status**: ✅ COMPLETE
> **Priority**: High
> **Effort**: Small (1-2 hours)
> **Completed**: 2026-01-21
> **Tests**: 20 passed (45 assertions)

## Problem

Multi-option quotations can be converted to invoice without the customer selecting a variant. This creates incomplete invoices with potentially incorrect pricing.

### User Story

> As a sales operations manager, I want the system to prevent conversion of multi-option quotations until a variant is selected, so that invoices always have the correct pricing and configuration.

## Solution

Add guard in `canConvert()` to require variant selection for multi-option quotations.

## Implementation

### 1. State Machine Guard

```php
// app/Domain/Sales/Quotations/QuotationStateMachine.php

/**
 * Check if quotation can be converted to invoice.
 */
public function canConvert(): bool
{
    // Must be Approved
    if ($this->currentStatus !== DocumentStatus::Approved) {
        return false;
    }

    // Must not already be converted
    if ($this->quotation->converted_to_invoice_id !== null) {
        return false;
    }

    // NEW: Multi-option quotations require variant selection
    if ($this->quotation->isMultiOption() && !$this->quotation->hasSelectedVariant()) {
        return false;
    }

    return true;
}

/**
 * Get reason why conversion is blocked.
 */
public function getConversionBlockReason(): ?string
{
    if ($this->currentStatus !== DocumentStatus::Approved) {
        return 'Quotation must be approved before conversion.';
    }

    if ($this->quotation->converted_to_invoice_id !== null) {
        return 'Quotation has already been converted to invoice.';
    }

    if ($this->quotation->isMultiOption() && !$this->quotation->hasSelectedVariant()) {
        return 'Please select a variant option before converting to invoice.';
    }

    return null;
}
```

### 2. Model Helper Methods

```php
// app/Models/Sales/Quotation.php

/**
 * Check if this is a multi-option quotation.
 */
public function isMultiOption(): bool
{
    return $this->quotation_type === 'multi_option';
}

/**
 * Check if this is a single (standard) quotation.
 */
public function isSingle(): bool
{
    return $this->quotation_type === 'single' || $this->quotation_type === null;
}

/**
 * Check if a variant has been selected.
 */
public function hasSelectedVariant(): bool
{
    return $this->selected_variant_id !== null;
}

/**
 * Get the selected variant option.
 */
public function selectedVariantOption(): BelongsTo
{
    return $this->belongsTo(QuotationVariantOption::class, 'selected_variant_id');
}
```

### 3. Service Layer Update

```php
// app/Services/Sales/QuotationService.php

public function convertToInvoice(Quotation $quotation): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canConvert()) {
        $reason = $stateMachine->getConversionBlockReason()
            ?? 'Quotation cannot be converted to invoice.';

        return ServiceResult::failure($reason);
    }

    // ... rest of existing conversion logic
}
```

### 4. Controller Response Enhancement

```php
// app/Http/Controllers/Api/V1/QuotationController.php

public function convertToInvoice(Quotation $quotation)
{
    $result = $this->quotationService->convertToInvoice($quotation);

    if ($result->failed()) {
        $response = [
            'message' => $result->message,
        ];

        // Add helpful context for multi-option quotations
        if ($quotation->isMultiOption() && !$quotation->hasSelectedVariant()) {
            $response['error_code'] = 'VARIANT_NOT_SELECTED';
            $response['available_variants'] = $quotation->variantOptions()
                ->select('id', 'display_name', 'selling_price', 'is_recommended')
                ->get();
            $response['suggestion'] = 'Use POST /api/v1/quotations/{id}/select-variant to select a variant first.';
        }

        return response()->json($response, 422);
    }

    return response()->json([
        'message' => 'Invoice created successfully.',
        'quotation' => new QuotationResource($quotation->fresh()),
        'invoice' => new InvoiceResource($quotation->convertedToInvoice),
    ]);
}
```

## Tests

```php
// tests/Feature/Services/Sales/QuotationVariantSelectionTest.php

<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\Sales\QuotationVariantOption;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(\App\Models\User::factory()->create());
    $this->quotationService = app(\App\Services\Sales\QuotationService::class);
});

it('can convert single quotation without variant', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'quotation_type' => 'single',
        ]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
    expect($quotation->fresh()->status)->toBe(DocumentStatus::Converted);
});

it('cannot convert multi-option quotation without variant selection', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'quotation_type' => 'multi_option',
            'selected_variant_id' => null,
        ]);

    // Add variant options
    QuotationVariantOption::factory()->count(2)->create([
        'quotation_id' => $quotation->id,
    ]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->failed())->toBeTrue();
    expect($result->message)->toContain('select a variant');
});

it('can convert multi-option quotation with variant selected', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'quotation_type' => 'multi_option',
        ]);

    $variant = QuotationVariantOption::factory()->create([
        'quotation_id' => $quotation->id,
    ]);

    $quotation->update(['selected_variant_id' => $variant->id]);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
});

it('state machine canConvert returns false for multi-option without selection', function () {
    $quotation = Quotation::factory()->approved()->create([
        'quotation_type' => 'multi_option',
        'selected_variant_id' => null,
    ]);

    $stateMachine = $quotation->stateMachine();

    expect($stateMachine->canConvert())->toBeFalse();
    expect($stateMachine->getConversionBlockReason())
        ->toContain('select a variant');
});

it('state machine canConvert returns true for single quotation', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'quotation_type' => 'single',
        ]);

    $stateMachine = $quotation->stateMachine();

    expect($stateMachine->canConvert())->toBeTrue();
});

// API Tests
it('returns available variants when conversion blocked', function () {
    $quotation = Quotation::factory()
        ->has(QuotationItem::factory())
        ->approved()
        ->create([
            'quotation_type' => 'multi_option',
            'selected_variant_id' => null,
        ]);

    QuotationVariantOption::factory()->create([
        'quotation_id' => $quotation->id,
        'display_name' => 'Basic Package',
    ]);

    QuotationVariantOption::factory()->create([
        'quotation_id' => $quotation->id,
        'display_name' => 'Premium Package',
        'is_recommended' => true,
    ]);

    $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

    $response->assertUnprocessable()
        ->assertJsonPath('error_code', 'VARIANT_NOT_SELECTED')
        ->assertJsonCount(2, 'available_variants');
});
```

## Verification

```bash
# Run specific tests
php artisan test --filter=QuotationVariantSelection

# Run all quotation tests
php artisan test --filter=Quotation
```

## Checklist

- [x] Model helper methods exist (`isMultiOption`, `hasSelectedVariant`)
- [x] State machine guard updated with variant check
- [x] State machine `getConversionBlockReason()` method added
- [x] Service layer updated to use reason
- [x] Controller enhanced with helpful error response (error_code, available_variants, suggestion)
- [x] Tests written and passing (20 tests, 45 assertions)

## Migration for Existing Data

Check if there are any multi-option quotations in Approved status without variant selection:

```php
// Check for problematic data
$problematic = Quotation::where('quotation_type', 'multi_option')
    ->where('status', DocumentStatus::Approved)
    ->whereNull('selected_variant_id')
    ->get();

if ($problematic->isNotEmpty()) {
    Log::warning('Multi-option quotations without variant selection', [
        'count' => $problematic->count(),
        'ids' => $problematic->pluck('id'),
    ]);
}
```

These will simply be blocked from conversion until variant is selected - no data migration needed.
