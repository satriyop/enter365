# Invoice Flow Enhancement - Master Plan

> **Status**: 📋 ANALYSIS COMPLETE - Awaiting Decisions
> **Created**: 2026-01-21
> **Last Updated**: 2026-01-21

## Overview

This plan addresses gaps and ambiguities in the Invoice workflow identified during code review. Issues are categorized as:

- **FIX**: Quick fixes that can be implemented immediately
- **DECISION**: Requires business stakeholder input before implementation
- **NEW FEATURE**: Requires new feature development, marked for future sprints

---

## Progress Summary

| Phase | Description | Type | Status | Tests |
|-------|-------------|------|--------|-------|
| 1 | Prevent Voiding Paid Invoices | DECISION + FIX | 📋 Awaiting Decision | - |
| 2 | Add Cancellation Tracking Fields | FIX | 📋 Ready | - |
| 3 | Auto-Overdue Scheduler | FIX | 📋 Ready | - |
| 4 | Partial + Overdue Flag | FIX | 📋 Ready | - |
| 5 | Early Payment Discount Workflow | NEW FEATURE | 📋 Documented | - |
| 6 | Multi-Currency Payment Handling | NEW FEATURE | 📋 Documented | - |

---

## Current Workflow

```
┌─────────┐   POST   ┌─────────┐
│  Draft  │─────────►│  Sent   │
└────┬────┘          └────┬────┘
     │                    │
     │ DELETE             │ Payment
     ▼                    ▼
  (removed)         ┌──────────┐
                    │ Partial  │◄────┐
                    └────┬─────┘     │
                         │           │
                         │ Full      │ More
                         ▼           │ Payment
                    ┌─────────┐      │
                    │  Paid   │──────┘
                    └────┬────┘
                         │
                         │ VOID (⚠️ Problem)
                         ▼
                    ┌───────────┐
                    │ Cancelled │
                    └───────────┘

+ OVERDUE: Sent/Partial past due_date (manual trigger)
+ VOID: Available from Sent, Partial, Overdue, Paid (reverses journal)
```

---

## Phase 1: Prevent Voiding Paid Invoices 📋 DECISION REQUIRED

**Type**: DECISION + FIX
**Priority**: High
**Effort**: Medium
**Status**: Awaiting Decision

### Problem
Currently, paid invoices can be voided. This creates accounting issues:
- Payment already cleared AR to zero
- Voiding invoice reverses AR to NEGATIVE
- No refund mechanism exists

### Options

| Option | Description | Effort | Recommended |
|--------|-------------|--------|-------------|
| A | Block voiding paid invoices entirely | Small | ✅ Simple |
| B | Implement Credit Memo + Refund flow | Large | Enterprise |
| C | Keep current + add warning | Small | Not recommended |

### Business Questions
1. Do customers ever need refunds after paying?
2. If yes, is a separate Credit Memo flow acceptable?
3. Are we okay blocking void for Paid status?

### Detail
See: `plans/invoice/01-void-paid-invoices.md`

---

## Phase 2: Add Cancellation Tracking Fields 📋 READY

**Type**: FIX
**Priority**: Low
**Effort**: Small (1-2 hours)
**Status**: Ready to implement

### Problem
When invoice is voided:
- Status changes to Cancelled
- Reason only stored in `InvoiceVoided` event
- Cannot query "who cancelled and why?" from database

### Solution
Add tracking fields: `cancelled_at`, `cancelled_by`, `cancel_reason`

### Detail
See: `plans/invoice/02-cancellation-tracking.md`

---

## Phase 3: Auto-Overdue Scheduler 📋 READY

**Type**: FIX
**Priority**: Medium
**Effort**: Small (2-3 hours)
**Status**: Ready to implement

### Problem
Invoices past due_date remain in Sent/Partial status. No automatic detection.
Users must manually mark as overdue or call API.

### Solution
Create scheduled command that runs daily to auto-mark overdue invoices.

### Detail
See: `plans/invoice/03-auto-overdue-scheduler.md`

---

## Phase 4: Add is_overdue Flag 📋 READY

**Type**: FIX
**Priority**: Medium
**Effort**: Small (1-2 hours)
**Status**: Ready to implement

### Problem
Invoice can be both:
- Partially paid (paid_amount > 0)
- Past due (due_date in past)

Current status system only allows ONE status. Partial wins silently.

### Solution
Add `is_overdue` boolean flag separate from status.
This allows "Partial" status with `is_overdue = true`.

### Detail
See: `plans/invoice/04-overdue-flag.md`

---

## Phase 5: Early Payment Discount Workflow 📋 NEW FEATURE

**Type**: NEW FEATURE
**Priority**: Medium
**Effort**: Medium
**Status**: Documented

### Problem
Invoice has discount fields but workflow not implemented:
- `early_discount_percent`
- `early_discount_days`
- `early_discount_deadline`

Discount is calculated but never applied to payments.

### Business Questions
1. Auto-apply discount if paid within deadline?
2. Manual approval required?
3. How to journal the discount (contra-revenue)?

### Detail
See: `plans/invoice/05-early-payment-discount.md`

---

## Phase 6: Multi-Currency Payment Handling 📋 NEW FEATURE

**Type**: NEW FEATURE
**Priority**: Medium
**Effort**: Large
**Status**: Documented

### Problem
Invoice supports multi-currency (USD, EUR) with exchange rates.
Payment validation compares amounts but currency handling unclear.

### Business Questions
1. Can payments be made in different currency than invoice?
2. How to handle exchange rate differences?
3. How to recognize FX gain/loss?

### Detail
See: `plans/invoice/06-multi-currency-payments.md`

---

## Implementation Order

### Immediate (After Decisions)
1. **Phase 1**: Void Paid Invoices - High priority, needs decision
2. **Phase 2**: Cancellation Tracking - Low effort, quick win
3. **Phase 3**: Auto-Overdue - Improves accuracy
4. **Phase 4**: is_overdue Flag - Better reporting

### Future Sprints
5. **Phase 5**: Early Discount - Business workflow needed
6. **Phase 6**: Multi-Currency - Large scope

---

## What's Working Well

The invoice flow has solid foundations:

| Area | Status | Notes |
|------|--------|-------|
| State Machine | ✅ Good | All transitions through guards |
| Journal Integration | ✅ Good | AR/Revenue posting correct |
| Payment Flow | ✅ Good | Status auto-updates on payment |
| Event-Driven | ✅ Good | All changes dispatch events |
| Test Coverage | ✅ Good | Service + API tests exist |
| Soft Deletes | ✅ Good | Audit trail maintained |

---

## Risk Assessment

| Phase | Risk | Mitigation |
|-------|------|------------|
| 1 | Breaks existing void workflow | Migration check for paid+voided invoices |
| 2 | Low risk | New nullable fields |
| 3 | Scheduler must be reliable | Add monitoring, error handling |
| 4 | Reports may need updating | Update dashboard queries |
| 5-6 | Large scope | Defer to dedicated sprint |

---

## Verification Commands

```bash
# Run invoice tests
php artisan test --filter=Invoice

# Run state machine tests
php artisan test --filter=InvoiceStateMachine

# Check current paid invoices
php artisan tinker --execute="
    App\Models\Sales\Invoice::where('status', 'paid')->count()
"

# Check voided paid invoices (problematic)
php artisan tinker --execute="
    App\Models\Sales\Invoice::where('status', 'cancelled')
        ->where('paid_amount', '>', 0)
        ->count()
"
```

---

## Files Reference

### Core Files
- `app/Domain/Sales/Invoices/InvoiceStateMachine.php`
- `app/Services/Sales/InvoiceService.php`
- `app/Services/Sales/InvoicePaymentService.php`
- `app/Services/Shared/PaymentService.php`
- `app/Services/Accounting/JournalService.php`
- `app/Models/Sales/Invoice.php`

### Tests
- `tests/Feature/Services/Sales/InvoiceServiceTest.php`
- `tests/Feature/Domain/Sales/Invoices/InvoiceStateMachineTest.php`
- `tests/Feature/Api/V1/InvoiceApiTest.php`

---

## Next Action

**Awaiting business decisions for Phase 1 (Void Paid Invoices).**

Once decided:
1. Implement Phase 1 based on chosen option
2. Implement Phases 2-4 in parallel (low risk)
3. Plan Phases 5-6 for future sprint
