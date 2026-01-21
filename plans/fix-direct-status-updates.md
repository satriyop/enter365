# Fix Direct Status Updates - Master Plan

> **Status**: ✅ COMPLETED
> **Created**: 2026-01-21
> **Last Updated**: 2026-01-21
> **Completed**: All 11 phases (0-10)

## Overview

This plan addresses all locations where document status is updated directly, bypassing state machines. Direct updates cause:
- No validation through guards
- No events dispatched
- No history recorded in `status_histories`
- Potential invalid state transitions

---

## Progress Summary

| Phase | Description | Status | Tests |
|-------|-------------|--------|-------|
| 0 | Foundation Fix (AbstractStateMachine) | ✅ DONE | ✅ Pass |
| 1 | Invoice Payment Status | ✅ DONE | ✅ 166 Pass |
| 2 | Bill Payment Status | ✅ DONE | ✅ 34 Pass |
| 3 | Overdue Marking (Command) | ✅ DONE | ✅ 14 Pass |
| 4 | Stock Opname Service | ✅ DONE | ✅ 35 Pass |
| 5 | Purchase Order Receiving | ✅ DONE | ✅ 39 Pass |
| 6 | Goods Receipt Note | ✅ DONE | ✅ 26 Pass |
| 7 | Solar Proposal | ✅ DONE | ✅ 46 Pass |
| 8 | Quotation/Reminder Bulk Updates | ✅ DONE | ✅ 180 Pass |
| 9 | MRP Run Status | ✅ DONE | ✅ 27 Pass |
| 10 | Cleanup & Verification | ✅ DONE | ✅ 1841 Pass |

---

## Phase 0: Foundation Fix ✅ DONE

### Completed Changes
- [x] `AbstractStateMachine.php:141-143` - Return `false` for same-status transitions
- [x] `JournalService.php:219-223` - Remove duplicate status in `postInvoice()`
- [x] `JournalService.php:293-297` - Remove duplicate status in `postBill()`
- [x] Updated related tests

### Files Modified
- `app/Domain/Core/AbstractStateMachine.php`
- `app/Services/Accounting/JournalService.php`
- `tests/Feature/Domain/Core/AbstractStateMachineTest.php`
- `tests/Feature/Api/V1/InvoiceApiTest.php`
- `tests/Feature/Services/Sales/InvoiceServiceTest.php`

---

## Phase 1: Invoice Payment Status ✅ DONE

### Problem
Multiple places update invoice payment status directly:
1. `UpdateInvoiceStatusOnPayment.php` - Listener bypasses state machine
2. `InvoicePaymentService.php` - Duplicates state machine logic
3. `Invoice.php` model methods - `updatePaymentStatus()`, `markAsOverdue()`

### Completed Changes
- [x] Created `InvoicePartiallyPaid` event
- [x] Added `markAsPaid()`, `markAsPartial()`, `markAsOverdue()`, `updatePaymentStatus()` to `InvoiceService`
- [x] Updated `InvoiceServiceInterface` with new methods
- [x] Updated `InvoicePaymentService` to delegate to `InvoiceService`
- [x] Updated `UpdateInvoiceStatusOnPayment` listener to use `InvoiceService`
- [x] Deprecated model helper methods with `trigger_error()` and state machine delegation

### Files Modified
- `app/Domain/Sales/Invoices/Events/InvoicePartiallyPaid.php` (new)
- `app/Contracts/Sales/InvoiceServiceInterface.php`
- `app/Services/Sales/InvoiceService.php`
- `app/Services/Sales/InvoicePaymentService.php`
- `app/Infrastructure/Listeners/Sales/UpdateInvoiceStatusOnPayment.php`
- `app/Models/Sales/Invoice.php`

### Tests
- All 166 invoice tests pass ✅

---

## Phase 2: Bill Payment Status ✅ DONE

### Problem
Same pattern as Invoice:
1. `Bill.php` model methods - `updatePaymentStatus()`, `markAsOverdue()`
2. Likely similar listener/service patterns

### Completed Changes
- [x] Created `BillPartiallyPaid` and `BillOverdue` events
- [x] Added `markAsPaid()`, `markAsPartial()`, `markAsOverdue()`, `updatePaymentStatus()` to `BillService`
- [x] Updated `BillServiceInterface` with new methods
- [x] Deprecated model helper methods with `trigger_error()` and state machine delegation

### Files Modified
- `app/Domain/Purchasing/Bills/Events/BillPartiallyPaid.php` (new)
- `app/Domain/Purchasing/Bills/Events/BillOverdue.php` (new)
- `app/Contracts/Purchasing/BillServiceInterface.php`
- `app/Services/Purchasing/BillService.php`
- `app/Models/Purchasing/Bill.php`

### Tests
- All 34 Bill tests pass ✅

---

## Phase 3: Overdue Marking Command ✅ DONE

### Problem
`MarkOverdueDocuments.php` directly sets status on invoices and bills.

### Completed Changes
- [x] Injected `InvoiceServiceInterface` and `BillServiceInterface` in constructor
- [x] Replaced direct status updates with `$this->invoiceService->markAsOverdue()`
- [x] Replaced direct status updates with `$this->billService->markAsOverdue()`
- [x] Added error handling for failed transitions
- [x] Removed unused `DB` facade import

### Files Modified
- `app/Console/Commands/MarkOverdueDocuments.php`

### Tests
- All 14 existing tests pass ✅

---

## Phase 4: Stock Opname Service ✅ DONE

### Problem
`StockOpnameService.php` has direct status updates despite having a state machine, plus same-status bug in StockOpnameStateMachine.

### Completed Changes
- [x] Fixed same-status bug in `StockOpnameStateMachine::canTransitionTo()` (returned `true`, now `false`)
- [x] Added `stateMachine()` and `transitionTo()` helper methods to `StockOpname` model
- [x] Updated `startCounting()` to use state machine
- [x] Updated `submitForReview()` to use state machine
- [x] Updated `approve()` to use state machine (REVIEWED → APPROVED → COMPLETED)
- [x] Updated `reject()` to use state machine
- [x] Updated `cancel()` to use state machine
- [x] Fixed inconsistent use of `DocumentStatus` enum - now uses `StockOpname::STATUS_*` constants

### Files Modified
- `app/Domain/Inventory/StockOpname/StockOpnameStateMachine.php`
- `app/Models/Inventory/StockOpname.php`
- `app/Services/Inventory/StockOpnameService.php`

### Tests
- All 35 Stock Opname tests pass ✅

---

## Phase 5: Purchase Order Receiving ✅ DONE

### Problem
`PurchaseOrderReceivingService.php` directly sets PO status, and `PurchaseOrder.php` model has duplicate method.

### Completed Changes
- [x] Updated `PurchaseOrderReceivingService::updateReceivingStatus()` to use state machine
- [x] Use `$po->stateMachine()->canReceive()` instead of manual status check
- [x] Use `$po->transitionTo()` for Partial and Received transitions
- [x] Deprecated `PurchaseOrder::updateReceivingStatus()` model method with trigger_error
- [x] Model method now delegates to state machine

### Files Modified
- `app/Services/Purchasing/PurchaseOrderReceivingService.php`
- `app/Models/Purchasing/PurchaseOrder.php`

### Tests
- All 39 Purchase Order tests pass ✅

---

## Phase 6: Goods Receipt Note ✅ DONE

### Problem
`GoodsReceiptNoteService.php` has direct status updates, and GRN had no state machine.

### Completed Changes
- [x] Created `GoodsReceiptNoteStateMachine` extending AbstractStateMachine
- [x] Created `GoodsReceiptNoteStatusChanged` event
- [x] Added `stateMachine()` and `transitionTo()` helper methods to model
- [x] Updated `startReceiving()` to use state machine
- [x] Updated `complete()` to use state machine
- [x] Updated `cancel()` to use state machine

### Files Created
- `app/Domain/Purchasing/GoodsReceiptNotes/GoodsReceiptNoteStateMachine.php`
- `app/Domain/Purchasing/GoodsReceiptNotes/Events/GoodsReceiptNoteStatusChanged.php`

### Files Modified
- `app/Models/Purchasing/GoodsReceiptNote.php`
- `app/Services/Purchasing/GoodsReceiptNoteService.php`

### Tests
- All 26 GRN tests pass ✅

---

## Phase 7: Solar Proposal ✅ DONE

### Problem
`SolarProposalService.php` and `PublicSolarProposalController.php` had direct status updates.

### Completed Changes
- [x] Created `SolarProposalStateMachine` extending AbstractStateMachine
- [x] Created `SolarProposalStatusChanged` event
- [x] Added `stateMachine()` and `transitionTo()` helper methods to model
- [x] Updated `send()`, `accept()`, `reject()` methods in service to use state machine
- [x] Updated `PublicSolarProposalController` to use service methods instead of direct updates
- [x] Controller now properly injects SolarProposalService

### Files Created
- `app/Domain/Solar/Proposals/SolarProposalStateMachine.php`
- `app/Domain/Solar/Proposals/Events/SolarProposalStatusChanged.php`

### Files Modified
- `app/Models/Solar/SolarProposal.php`
- `app/Services/Solar/SolarProposalService.php`
- `app/Http/Controllers/Api/PublicSolarProposalController.php`

### Tests
- All 46 Solar Proposal tests pass ✅ (1 skipped for unrelated reason)

---

## Phase 8: Quotation/Reminder Bulk Updates ✅ DONE

### Problem
- `QuotationService.php:307` - Bulk update to Expired
- `ReminderService.php:175` - Bulk cancel (was using wrong constant)

### Completed Changes
- [x] Fixed `ReminderService::cancelReminders()` - was using `DocumentStatus::Cancelled` but PaymentReminder uses `PaymentReminder::STATUS_CANCELLED` constant
- [x] Updated `QuotationService::markExpired()` to use chunked iteration with state machine
- [x] Added `recordHistory()` override to `QuotationStateMachine` for status history recording
- [x] Created comprehensive tests for quotation expiration

### Files Modified
- `app/Services/Sales/ReminderService.php` - Bug fix: wrong constant
- `app/Services/Sales/QuotationService.php` - Chunked state machine iteration
- `app/Domain/Sales/Quotations/QuotationStateMachine.php` - Added recordHistory()

### Tests
- All 180 Quotation tests pass ✅

---

## Phase 9: MRP Run Status ✅ DONE

### Problem
`MrpService.php` directly sets run status at lines 36, 54, 73, 79.

### Decision
MRP Run is an internal batch process with simple lifecycle (Draft → Processing → Completed → Applied).
Full state machine is overkill. Instead, created lightweight domain events for observability.

### Completed Changes
- [x] Created `MrpRunStarted` event - dispatched when MRP run begins execution
- [x] Created `MrpRunCompleted` event - dispatched when MRP run finishes successfully
- [x] Created `MrpRunFailed` event - dispatched when MRP run encounters an error
- [x] Created `MrpRunStatusChanged` base event for consistency
- [x] Updated `MrpService::execute()` to dispatch events at key transitions

### Files Created
- `app/Domain/Manufacturing/MrpRuns/Events/MrpRunStatusChanged.php`
- `app/Domain/Manufacturing/MrpRuns/Events/MrpRunStarted.php`
- `app/Domain/Manufacturing/MrpRuns/Events/MrpRunCompleted.php`
- `app/Domain/Manufacturing/MrpRuns/Events/MrpRunFailed.php`

### Files Modified
- `app/Services/Manufacturing/MrpService.php` - Added event dispatch

### Tests
- All 27 MRP tests pass ✅

---

## Phase 10: Cleanup & Verification ✅ DONE

### Tasks Completed
- [x] Run full test suite - **1841 tests pass** (1 risky, 1 skipped - pre-existing)
- [x] Searched for remaining direct status updates
- [x] Identified lower-priority items for future work

### Remaining Direct Status Updates (Lower Priority)
These are acceptable or deferred for future work:

**Initial Creation (Acceptable)**
- WorkOrderService, BomVariantGroupService, SubcontractorService, DeliveryOrderService, BomService, CostOptimizationService, BrandSwapService - All setting initial Draft status on new documents

**Master Data (Deferred)**
- BomService - Active/Inactive transitions (simpler lifecycle, lower risk)
- SubcontractorInvoice - Internal document approval/rejection

**Already Handled**
- MrpService - Events dispatched for status changes

### Verification Commands
```bash
# Search for remaining direct updates
grep -r "status.*=.*DocumentStatus::" app/ --include="*.php"
grep -r "->status\s*=" app/ --include="*.php"

# Run all tests
php artisan test

# Run specific test suites
php artisan test --filter=StateMachine
php artisan test --filter=Invoice
php artisan test --filter=Bill
```

---

## Implementation Notes

### State Machine Transition Methods to Add

For documents with payment tracking (Invoice, Bill):
```php
// In respective Service classes
public function markAsPaid(Model $document): ServiceResult;
public function markAsPartial(Model $document): ServiceResult;
public function markAsOverdue(Model $document): ServiceResult;
```

### Guard Conditions for Payment Transitions

```php
// Sent/Received -> Paid: paid_amount >= total_amount
// Sent/Received -> Partial: paid_amount > 0 && paid_amount < total_amount
// Sent/Received/Partial -> Overdue: due_date < today
```

### Event Dispatch Pattern

Each status change should dispatch appropriate event:
- `InvoicePaid`, `InvoicePartiallyPaid`, `InvoiceOverdue`
- `BillPaid`, `BillPartiallyPaid`, `BillOverdue`

---

## Risk Assessment

| Phase | Risk | Mitigation |
|-------|------|------------|
| 1-2 | Payment flow disruption | Comprehensive tests, staged rollout |
| 3 | Bulk operation performance | Batch processing, chunking |
| 4-6 | Inventory tracking errors | Full integration tests |
| 7 | Customer-facing issues | Test public endpoints thoroughly |
| 8 | Scheduled job failures | Monitor after deployment |

---

## Rollback Plan

Each phase should be independently deployable. If issues arise:
1. Revert specific phase commits
2. Model helper methods remain functional as fallback
3. Direct DB fix scripts available if needed

---

## Next Action

Start **Phase 1: Invoice Payment Status** - this is the highest risk area and will establish the pattern for subsequent phases.
