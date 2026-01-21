# Quotation Flow Enhancement - Master Plan

> **Status**: ✅ FIX PHASES COMPLETE
> **Created**: 2026-01-21
> **Last Updated**: 2026-01-21
> **Completed**: 2026-01-21
> **Total Tests**: 246 passed (590 assertions)

## Overview

This plan addresses gaps and ambiguities in the Quotation workflow identified during code review. Issues are categorized as:

- **FIX**: Quick fixes that can be implemented immediately
- **NEW FEATURE**: Requires new feature development, marked for future sprints

---

## Progress Summary

| Phase | Description | Type | Status | Tests |
|-------|-------------|------|--------|-------|
| 1 | Add Cancel Transition | FIX | ✅ Complete | 20 passed |
| 2 | Enforce Variant Selection | FIX | ✅ Complete | 20 passed |
| 3 | Clear Follow-Up on Conversion | FIX | ✅ Complete | 7 passed |
| 4 | Clarify Approved Expiration | FIX | ✅ Complete | 18 passed |
| 5 | Outcome-Workflow Integration | NEW FEATURE | 📋 Documented | - |
| 6 | Add "Sent to Customer" Status | NEW FEATURE | 📋 Documented | - |
| 7 | Add "Accepted" Status | NEW FEATURE | 📋 Documented | - |

---

## Phase 1: Add Cancel Transition ✅ COMPLETE

**Type**: FIX
**Priority**: High
**Effort**: Small
**Completed**: 2026-01-21
**Tests**: 20 passed (37 assertions)

### Problem
Users cannot cancel a Submitted or Approved quotation. They must wait for expiration or directly manipulate the database.

### Solution
Add `cancel()` method to QuotationService and `Cancelled` transition to QuotationStateMachine.

### Files Modified
- `database/migrations/2026_01_21_083317_add_cancellation_tracking_to_quotations.php` (NEW)
- `app/Models/Sales/Quotation.php`
- `app/Domain/Sales/Quotations/QuotationStateMachine.php`
- `app/Domain/Sales/Quotations/Events/QuotationCancelled.php` (NEW)
- `app/Services/Sales/QuotationService.php`
- `app/Http/Controllers/Api/V1/QuotationController.php`
- `app/Http/Requests/Api/V1/CancelQuotationRequest.php` (NEW)
- `app/Listeners/Sales/QuotationEventSubscriber.php`
- `routes/api.php`
- `tests/Feature/Services/Sales/QuotationCancellationTest.php` (NEW)

### Acceptance Criteria
- [x] Draft, Submitted, Approved quotations can be cancelled
- [x] Cancelled quotations cannot be edited, submitted, or converted
- [x] Cancelled quotations can be revised (creates new Draft)
- [x] `QuotationCancelled` event dispatched
- [x] `cancelled_at`, `cancelled_by`, `cancellation_reason` tracked
- [x] API endpoint: `POST /api/v1/quotations/{id}/cancel`

### Tests Passed
- [x] Can cancel Draft quotation
- [x] Can cancel Submitted quotation
- [x] Can cancel Approved quotation
- [x] Cannot cancel Converted quotation
- [x] Cannot cancel already Cancelled quotation
- [x] Cannot cancel Expired quotation
- [x] Can revise Cancelled quotation
- [x] Event dispatched on cancellation
- [x] Clears follow-up on cancellation
- [x] Can cancel without reason
- [x] State machine canCancel() for all statuses
- [x] API endpoint tests (with/without reason, validation)
- [x] Active scope excludes cancelled

---

## Phase 2: Enforce Variant Selection ✅ COMPLETE

**Type**: FIX
**Priority**: High
**Effort**: Small
**Completed**: 2026-01-21
**Tests**: 20 passed (45 assertions)

### Problem
Multi-option quotations can be converted to invoice without customer selecting a variant. This creates incomplete invoices.

### Solution
Add guard in `canConvert()` to require variant selection for multi-option quotations.

### Files Modified
- `app/Models/Sales/Quotation.php` - added `isMultiOption()`, `hasSelectedVariant()`
- `app/Domain/Sales/Quotations/QuotationStateMachine.php` - updated `canConvert()`, added `getConversionBlockReason()`
- `app/Http/Controllers/Api/V1/QuotationController.php` - enhanced error response
- `tests/Feature/Services/Sales/QuotationVariantSelectionTest.php` (NEW)

### Acceptance Criteria
- [x] Single quotations convert normally (no change)
- [x] Multi-option quotations require `selected_variant_id` before conversion
- [x] Clear error message when variant not selected
- [x] API returns 422 with explanation (error_code, available_variants, suggestion)

### Tests Passed
- [x] Single quotation converts without variant
- [x] Multi-option quotation without selection returns error
- [x] Multi-option quotation with selection converts successfully

---

## Phase 3: Clear Follow-Up on Conversion ✅ COMPLETE

**Type**: FIX
**Priority**: Medium
**Effort**: Small
**Completed**: 2026-01-21
**Tests**: 7 passed (19 assertions)

### Problem
When quotation converts to invoice, `next_follow_up_at` isn't cleared. Sales reps continue receiving follow-up reminders for already-converted quotations.

### Solution
Clear follow-up data when quotation is converted to invoice. Auto-mark as "Won" with reason.

### Files Modified
- `app/Services/Sales/QuotationConversionService.php` - clears follow-up, auto-marks Won
- `app/Domain/Sales/Quotations/Enums/QuotationOutcome.php` - added `converted_to_invoice` to WON_REASONS
- `tests/Feature/Services/Sales/QuotationConversionFollowUpTest.php` (NEW)

### Acceptance Criteria
- [x] `next_follow_up_at` cleared on conversion
- [x] Auto-mark as "Won" with reason `converted_to_invoice`

### Tests Passed
- [x] Quotation with follow-up scheduled converts and clears follow-up
- [x] Quotation without follow-up converts normally
- [x] Conversion auto-marks as Won with reason

---

## Phase 4: Clarify Approved Expiration Behavior ✅ COMPLETE

**Type**: FIX (Documentation + Code Change)
**Priority**: Medium
**Effort**: Small
**Completed**: 2026-01-21
**Decision**: Option C (sent_at tracking)
**Tests**: 18 passed (33 assertions)

### Problem
Approved quotations can expire silently. Business intent unclear:
- Should Approved quotations expire? (internal approval, not sent yet)
- Or should they be protected? (customer accepted, waiting for PO)

### Solution
Implemented Option C: Add `sent_at` timestamp to distinguish "approved but not sent" vs "sent to customer". Unsent approved quotations expire; sent ones are protected.

### Files Modified
- `database/migrations/2026_01_21_xxx_add_sent_tracking_to_quotations.php` (NEW)
- `app/Models/Sales/Quotation.php` - added `sent_at`, `sent_by`, `sent_to_email`, `sent_via`, `isSent()`, `sender()`
- `app/Services/Sales/QuotationService.php` - added `markAsSent()`, updated `markExpired()`
- `app/Http/Controllers/Api/V1/QuotationController.php` - added `markAsSent` endpoint
- `app/Http/Requests/Api/V1/MarkQuotationSentRequest.php` (NEW)
- `routes/api.php` - added `POST /quotations/{id}/mark-sent`
- `tests/Feature/Services/Sales/QuotationExpirationBehaviorTest.php` (NEW)

### Acceptance Criteria
- [x] Business decision documented (Option C)
- [x] Behavior matches decision (sent quotations protected from expiration)
- [x] Tests verify expected behavior

### Tests Passed
- [x] Draft quotations expire past valid_until
- [x] Submitted quotations expire past valid_until
- [x] Unsent approved quotations expire
- [x] Sent approved quotations do NOT expire
- [x] markAsSent() works correctly
- [x] API endpoint validates email/via
- [x] Integration: mark sent then try expire

---

## Phase 5: Outcome-Workflow Integration 📋 NEW FEATURE

**Type**: NEW FEATURE
**Priority**: Medium
**Effort**: Medium
**Sprint**: Future

### Problem
Outcome tracking (Won/Lost) is disconnected from the document workflow:
- Won quotation can still expire
- Lost quotation can still be converted to invoice
- No automation when outcome is set

### Proposed Solution

#### Option A: Outcome as Metadata (Current)
Keep outcome separate from status. Document that it's CRM data, not workflow control.

#### Option B: Outcome Affects Workflow
- Won → Prompt/auto-convert to Invoice
- Lost → Lock quotation (cannot convert)
- Lost → Optional: transition to new "Lost" status

#### Option C: Outcome Triggers Transitions
- Won → Automatically transition to Converted (with confirmation)
- Lost → Automatically transition to Cancelled

### Business Questions
1. Should Won automatically create an Invoice?
2. Should Lost prevent conversion entirely?
3. Should Lost quotations be visible in "Active" lists?

### Detailed Spec
See: `plans/quotation/05-outcome-integration.md`

---

## Phase 6: Add "Sent to Customer" Status 📋 NEW FEATURE

**Type**: NEW FEATURE
**Priority**: Low
**Effort**: Large
**Sprint**: Future

### Problem
No tracking of when quotation was actually sent to customer. Current `submitted_at` means "submitted for internal approval".

### Proposed Solution
Add `Sent` status between Approved and Converted:

```
Draft → Submitted → Approved → SENT → Converted
                                 ↓
                              Declined
```

### Features Required
- New `Sent` status in DocumentStatus enum
- State machine transition: Approved → Sent
- `send()` method in QuotationService
- `sent_at`, `sent_by`, `sent_via` (email/print/portal) fields
- Email integration for sending quotation PDF
- Customer portal for viewing (optional)
- "Declined" status for customer rejection

### Business Questions
1. How are quotations sent? (Email, Portal, Manual)
2. Should we track customer views/opens?
3. Do customers respond through system or offline?

### Detailed Spec
See: `plans/quotation/06-sent-status.md`

---

## Phase 7: Add "Accepted" Status 📋 NEW FEATURE

**Type**: NEW FEATURE
**Priority**: Low
**Effort**: Large
**Sprint**: Future

### Problem
No distinction between "quotation sent" and "customer accepted". Currently jumps from Approved to Converted.

### Proposed Solution
Add `Accepted` status for explicit customer acceptance:

```
Draft → Submitted → Approved → Sent → ACCEPTED → Converted
                                         ↓
                                       Lost
```

### Features Required
- New `Accepted` status
- Customer acceptance mechanism (portal, email link, manual)
- PO number capture on acceptance
- Terms acceptance tracking
- Digital signature (optional)

### Business Questions
1. How do customers accept quotations?
2. Is PO required before conversion?
3. Do we need digital signatures?

### Detailed Spec
See: `plans/quotation/07-accepted-status.md`

---

## Implementation Order

### Immediate (This Sprint)
1. **Phase 1**: Cancel Transition - High value, low effort
2. **Phase 2**: Variant Selection Guard - Prevents data issues
3. **Phase 3**: Clear Follow-Up - Quick fix
4. **Phase 4**: Expiration Clarification - Document + optional change

### Future Sprints
5. **Phase 5**: Outcome Integration - After business decision
6. **Phase 6**: Sent Status - Requires email integration
7. **Phase 7**: Accepted Status - Full customer portal needed

---

## Current Workflow vs Target Workflow

### Current (After Phases 1-4)
```
┌────────┐   ┌───────────┐   ┌──────────┐   ┌───────────┐
│ DRAFT  │──▶│ SUBMITTED │──▶│ APPROVED │──▶│ CONVERTED │
└────────┘   └───────────┘   └──────────┘   └───────────┘
    │             │               │
    ▼             ▼               ▼
CANCELLED    REJECTED        CANCELLED
    │             │               │
    └─────────────┴───────────────┴──▶ REVISE → Draft

+ EXPIRED (from Draft, Submitted, unsent Approved only)
+ SENT tracking: Approved quotations marked as sent are protected from expiration
```

### Target (After Phases 5-7) - Future
```
┌────────┐   ┌───────────┐   ┌──────────┐   ┌──────┐   ┌──────────┐   ┌───────────┐
│ DRAFT  │──▶│ SUBMITTED │──▶│ APPROVED │──▶│ SENT │──▶│ ACCEPTED │──▶│ CONVERTED │
└────────┘   └───────────┘   └──────────┘   └──────┘   └──────────┘   └───────────┘
    │             │               │             │            │
    ▼             ▼               ▼             ▼            ▼
CANCELLED    REJECTED        CANCELLED     DECLINED       LOST
```

---

## Risk Assessment

| Phase | Risk | Mitigation |
|-------|------|------------|
| 1 | Cancel may break reports counting active quotations | Update reports to exclude Cancelled |
| 2 | Existing multi-option quotations without selection | Migration to set default or warn |
| 3 | Low risk | Simple field clear |
| 4 | Business decision needed | Document current behavior as default |
| 5-7 | Large scope, UI changes needed | Defer to dedicated sprint |

---

## Verification Commands

```bash
# Run quotation tests
php artisan test --filter=Quotation

# Run state machine tests
php artisan test --filter=QuotationStateMachine

# Check for direct status updates (ensure we use state machine)
grep -r "status.*=.*DocumentStatus::" app/Services/Sales/Quotation*.php
```

---

## Next Action

**All FIX phases complete!** 🎉

Phases 5-7 are NEW FEATURES that require separate sprint planning:
- Phase 5: Outcome-Workflow Integration
- Phase 6: Add "Sent to Customer" Status
- Phase 7: Add "Accepted" Status

See individual plan files for detailed specifications.
