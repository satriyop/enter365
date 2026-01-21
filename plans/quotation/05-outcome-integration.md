# Phase 5: Outcome-Workflow Integration

> **Type**: 📋 NEW FEATURE
> **Status**: Documented for Future Development
> **Priority**: Medium
> **Effort**: Medium (2-3 days)
> **Dependencies**: None

## Problem Statement

The current Outcome tracking (Won/Lost) is completely disconnected from the document workflow:

```php
// Current behavior allows this contradiction:
$quotation->status = DocumentStatus::Approved;
$quotation->outcome = 'lost';
$quotation->convertToInvoice(); // Still works! Creates invoice for "lost" deal
```

### Current Issues

1. **Won quotation can expire** - Customer said yes, but quotation expires
2. **Lost quotation can convert** - Deal is lost, but still creates invoice
3. **No automation** - Marking as Won doesn't trigger any workflow action
4. **Confusing reports** - "Active" quotations include Won/Lost items

## Business Questions to Answer

Before implementing, stakeholders must decide:

| Question | Options | Impact |
|----------|---------|--------|
| Should Won auto-convert? | Yes / No / Prompt | Automation level |
| Should Lost block conversion? | Yes / No / Warn | Data integrity |
| Should Lost/Won be terminal? | Yes / No | Workflow complexity |
| Affect expiration? | Yes / No | Edge case handling |

## Proposed Solutions

### Option A: Outcome as Metadata Only (Minimal Change)

Keep current behavior but **document it clearly**:

- Outcome is CRM/Sales tracking data
- Outcome does NOT affect document workflow
- Users must manually manage both outcome and conversion

**Pros**: No code changes, no migration risk
**Cons**: Contradictory states remain possible

### Option B: Outcome Guards Workflow (Recommended)

Add guards to prevent contradictory actions:

```php
// In QuotationStateMachine
public function canConvert(): bool
{
    if ($this->quotation->outcome === 'lost') {
        return false; // Cannot convert lost quotation
    }
    // ... existing checks
}

public function canExpire(): bool
{
    if ($this->quotation->outcome === 'won') {
        return false; // Won quotations don't expire
    }
    // ... existing checks
}
```

**Pros**: Prevents contradictions, logical behavior
**Cons**: Requires migration for existing data

### Option C: Outcome Triggers Transitions (Full Integration)

Outcome changes trigger automatic status transitions:

```php
// When marked as Won
public function markAsWon(Quotation $quotation, array $data): ServiceResult
{
    // ... existing logic

    // Auto-convert or prompt
    if (config('quotation.auto_convert_on_won')) {
        return $this->convertToInvoice($quotation);
    }

    return ServiceResult::success($quotation);
}

// When marked as Lost
public function markAsLost(Quotation $quotation, array $data): ServiceResult
{
    // ... existing logic

    // Transition to Cancelled or new "Lost" status
    $quotation->transitionTo(DocumentStatus::Cancelled);

    return ServiceResult::success($quotation);
}
```

**Pros**: Fully automated, clean workflow
**Cons**: Complex, may not fit all business processes

## Detailed Implementation (Option B)

### 1. State Machine Changes

```php
// app/Domain/Sales/Quotations/QuotationStateMachine.php

public function canConvert(): bool
{
    // Existing checks
    if ($this->currentStatus !== DocumentStatus::Approved) {
        return false;
    }

    if ($this->quotation->converted_to_invoice_id !== null) {
        return false;
    }

    // NEW: Cannot convert lost quotations
    if ($this->quotation->outcome === 'lost') {
        return false;
    }

    return true;
}

public function canExpire(): bool
{
    // NEW: Won quotations don't auto-expire
    if ($this->quotation->outcome === 'won') {
        return false;
    }

    return in_array($this->currentStatus, [
        DocumentStatus::Draft,
        DocumentStatus::Submitted,
        DocumentStatus::Approved,
    ]);
}

public function getConvertBlockReason(): ?string
{
    if ($this->quotation->outcome === 'lost') {
        return 'Quotation marked as lost cannot be converted to invoice.';
    }
    return null;
}
```

### 2. Service Layer Updates

```php
// app/Services/Sales/QuotationService.php

public function convertToInvoice(Quotation $quotation): ServiceResult
{
    $stateMachine = $quotation->stateMachine();

    if (!$stateMachine->canConvert()) {
        $reason = $stateMachine->getConvertBlockReason()
            ?? 'Quotation cannot be converted.';
        return ServiceResult::failure($reason);
    }

    // ... existing conversion logic
}
```

### 3. Controller Updates

```php
// Return helpful error message
public function convertToInvoice(Quotation $quotation)
{
    $result = $this->quotationService->convertToInvoice($quotation);

    if ($result->failed()) {
        return response()->json([
            'message' => $result->message,
            'suggestion' => $quotation->outcome === 'lost'
                ? 'Create a new quotation or revise this one if customer changed their mind.'
                : null,
        ], 422);
    }

    // ... success response
}
```

### 4. Migration for Existing Data

```php
// Check for contradictory states
Schema::table('quotations', function (Blueprint $table) {
    // Add index for faster queries
    $table->index(['status', 'outcome']);
});

// Report contradictions (don't auto-fix)
$contradictions = Quotation::where('outcome', 'lost')
    ->where('status', DocumentStatus::Converted)
    ->get();

if ($contradictions->isNotEmpty()) {
    Log::warning('Found quotations marked as Lost but Converted', [
        'count' => $contradictions->count(),
        'ids' => $contradictions->pluck('id'),
    ]);
}
```

## API Changes

### Convert Endpoint Response

```json
// POST /api/v1/quotations/{id}/convert-to-invoice
// When quotation is marked as Lost

{
    "message": "Quotation marked as lost cannot be converted to invoice.",
    "error_code": "QUOTATION_LOST",
    "suggestion": "Create a new quotation or revise this one if customer changed their mind.",
    "quotation": {
        "id": 123,
        "status": "approved",
        "outcome": "lost",
        "lost_reason": "kalah_kompetitor"
    }
}
```

## Testing Requirements

```php
// tests/Feature/Services/Sales/QuotationOutcomeIntegrationTest.php

it('cannot convert quotation marked as lost', function () {
    $quotation = Quotation::factory()
        ->approved()
        ->create(['outcome' => 'lost']);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->failed())->toBeTrue();
    expect($result->message)->toContain('lost');
});

it('won quotation does not auto-expire', function () {
    $quotation = Quotation::factory()
        ->approved()
        ->create([
            'outcome' => 'won',
            'valid_until' => now()->subDay(),
        ]);

    $this->quotationService->markExpired();

    expect($quotation->fresh()->status)->toBe(DocumentStatus::Approved);
});

it('can still convert won quotation', function () {
    $quotation = Quotation::factory()
        ->approved()
        ->has(QuotationItem::factory())
        ->create(['outcome' => 'won']);

    $result = $this->quotationService->convertToInvoice($quotation);

    expect($result->succeeded())->toBeTrue();
});
```

## UI/UX Considerations

### Warning Messages

When user tries to convert a Lost quotation:
```
⚠️ This quotation was marked as Lost
Reason: Lost to competitor (Competitor XYZ)

You cannot convert a lost quotation to an invoice.

Options:
[Revise Quotation] - Create a new version if customer reconsidered
[View Details] - See quotation details
```

### Status Badge Enhancement

```html
<!-- Show combined status + outcome -->
<span class="badge badge-warning">
    Approved • Lost
</span>
```

## Rollout Plan

1. **Phase 1**: Add guards (prevent new contradictions)
2. **Phase 2**: Report existing contradictions
3. **Phase 3**: Work with sales team to clean up data
4. **Phase 4**: Add UI warnings

## Decision Log

| Date | Decision | Rationale | Decided By |
|------|----------|-----------|------------|
| TBD | Option A/B/C | TBD | TBD |

---

## Related Documents

- `00-master-plan.md` - Overall quotation enhancement plan
- `app/Domain/Sales/Quotations/OutcomeManager.php` - Current outcome implementation
