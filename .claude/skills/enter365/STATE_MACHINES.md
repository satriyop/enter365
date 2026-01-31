# State Machines Reference

All 15 state machines in Enter365 with states, transitions, and usage.

---

## Golden Rule: Single Responsibility for Status Changes

**Status transitions must ONLY happen through state machines, never via direct database updates.**

### Why This Matters

| Direct Update (WRONG) | State Machine (CORRECT) |
|-----------------------|-------------------------|
| No validation | Validates transition is allowed |
| No events fired | Fires domain events |
| No history recorded | Records status history |
| No timestamp/user tracking | Sets `{action}_at` and `{action}_by` |
| Bypasses business rules | Enforces business rules |

### Anti-Pattern (Never Do This)

```php
// WRONG - Direct status update in service
public function approveInvoice(Invoice $invoice): void
{
    $invoice->status = DocumentStatus::Approved;  // ❌ NEVER
    $invoice->save();
}

// WRONG - Mass update bypassing state machine
Invoice::where('status', 'draft')->update(['status' => 'sent']);  // ❌ NEVER
```

### Correct Pattern (Always Do This)

```php
// CORRECT - Status change through state machine
public function send(Invoice $invoice): Invoice
{
    $stateMachine = InvoiceStateMachine::fromInvoice($invoice, $this->eventDispatcher);

    if (!$stateMachine->canSend()) {
        throw StateTransitionException::actionNotAvailable('kirim', $invoice->status->value);
    }

    $stateMachine->transitionTo(DocumentStatus::Sent);  // ✓ Validates, fires events, records history

    return $invoice->fresh();
}
```

### What State Machines Guarantee

1. **Validation** - `canTransitionTo()` checks valid paths
2. **Events** - `afterTransition()` fires domain events
3. **Timestamps** - Sets `submitted_at`, `approved_at`, etc.
4. **User tracking** - Sets `submitted_by`, `approved_by`, etc.
5. **History** - `HasStatusHistory` trait records all changes
6. **Atomicity** - Wrap in `DB::transaction()` for consistency

---

## Base Class

**File:** `app/Domain/Core/AbstractStateMachine.php`

```php
abstract class AbstractStateMachine
{
    abstract protected function getTransitions(): array;

    public function transitionTo(DocumentStatus $status, array $context = []): void;
    public function canTransitionTo(DocumentStatus $status): bool;
    public function getNextValidStatuses(): array;

    // Lifecycle hooks (override in subclass)
    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void { }
    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void { }
    protected function before{StatusName}(): void { }
    protected function after{StatusName}(): void { }
}
```

---

## State Machines Registry

### 1. InvoiceStateMachine

**File:** `app/Domain/Sales/Invoices/InvoiceStateMachine.php`

```
draft → [sent, cancelled]
sent → [partial, paid, cancelled]
partial → [paid, cancelled]
```

**Permission Methods:** `canSend()`, `canCancel()`, `canEdit()`, `canDelete()`

---

### 2. QuotationStateMachine

**File:** `app/Domain/Sales/Quotations/QuotationStateMachine.php`

```
draft → [submitted, expired]
submitted → [approved, rejected, expired]
approved → [converted, expired]
rejected → [draft]
expired → [draft]
```

**Permission Methods:** `canSubmit()`, `canApprove()`, `canReject()`, `canConvert()`, `canRevise()`

---

### 3. DeliveryOrderStateMachine

**File:** `app/Domain/Sales/DeliveryOrders/DeliveryOrderStateMachine.php`

```
draft → [confirmed, cancelled]
confirmed → [shipped, cancelled]
shipped → [delivered, cancelled]
```

**Permission Methods:** `canConfirm()`, `canShip()`, `canDeliver()`, `canCancel()`

---

### 4. SalesReturnStateMachine

**File:** `app/Domain/Sales/SalesReturns/SalesReturnStateMachine.php`

```
draft → [submitted, cancelled]
submitted → [approved, rejected, cancelled]
approved → [completed, cancelled]
```

**Permission Methods:** `canSubmit()`, `canApprove()`, `canReject()`, `canComplete()`

---

### 5. BillStateMachine

**File:** `app/Domain/Purchasing/Bills/BillStateMachine.php`

```
draft → [received, cancelled]
received → [partial, paid, overdue, cancelled]
partial → [paid, overdue, cancelled]
paid → [cancelled]
overdue → [partial, paid, cancelled]
```

**Permission Methods:** `canPost()`, `canMarkAsPartial()`, `canMarkAsPaid()`, `canMarkAsOverdue()`, `canCancel()`, `canEdit()`, `canDelete()`

**Guards:** Received: requires items; Paid: paid_amount ≥ total_amount; Partial: 0 < paid_amount < total_amount; Overdue: due_date passed

---

### 6. PurchaseOrderStateMachine

**File:** `app/Domain/Purchasing/PurchaseOrders/PurchaseOrderStateMachine.php`

```
draft → [submitted, cancelled]
submitted → [approved, rejected, cancelled]
approved → [partial, received, cancelled]
partial → [received, cancelled]
rejected → [draft]
```

**Permission Methods:** `canSubmit()`, `canApprove()`, `canReceive()`, `canRevise()`

---

### 7. PurchaseReturnStateMachine

**File:** `app/Domain/Purchasing/PurchaseReturns/PurchaseReturnStateMachine.php`

```
draft → [submitted, cancelled]
submitted → [approved, rejected, cancelled]
approved → [completed, cancelled]
```

---

### 8. GoodsReceiptNoteStateMachine

**File:** `app/Domain/Purchasing/GoodsReceiptNotes/GoodsReceiptNoteStateMachine.php`

```
draft → [receiving, completed, cancelled]
receiving → [completed, cancelled]
```

**Permission Methods:** `canStartReceiving()`, `canComplete()`, `canCancel()`, `canEdit()`, `canDelete()`

**Guards:** Receiving: requires items; Completed: requires items with quantity_received > 0

---

### 9. ProjectStateMachine

**File:** `app/Domain/Projects/ProjectStateMachine.php`

```
draft → [planning, in_progress, cancelled]
planning → [in_progress, cancelled]
in_progress → [on_hold, completed, cancelled]
on_hold → [in_progress, completed, cancelled]
```

**Permission Methods:** `canStart()`, `canPutOnHold()`, `canResume()`, `canComplete()`

---

### 10. FiscalPeriodStateMachine

**File:** `app/Domain/Accounting/FiscalPeriods/FiscalPeriodStateMachine.php`

**Uses custom enum:** `FiscalPeriodStatus` (not DocumentStatus)

```
open → [locked]
locked → [open, closing]
closing → [locked, closed]
closed → [open]
```

---

### 11. WorkOrderStateMachine

**File:** `app/Domain/Manufacturing/WorkOrders/WorkOrderStateMachine.php`

```
draft → [confirmed, cancelled]
confirmed → [in_progress, cancelled]
in_progress → [completed, cancelled]
```

**Permission Methods:** `canConfirm()`, `canStart()`, `canComplete()`, `canCancel()`, `canEdit()`, `canDelete()`

**Guards:** Draft→Confirmed: has items; InProgress→Completed: all sub-work orders completed

**Timestamps:** confirmed_by/at, started_by/at + actual_start_date, completed_by/at + actual_end_date + quantity_completed, cancelled_by/at + reason

---

### 12. MaterialRequisitionStateMachine

**File:** `app/Domain/Manufacturing/MaterialRequisitions/MaterialRequisitionStateMachine.php`

```
draft → [approved, cancelled]
approved → [issued, partial, cancelled]
partial → [issued, cancelled]
```

**Permission Methods:** `canApprove()`, `canIssue()`, `canCancel()`, `canEdit()`, `canDelete()`

**Guards:** Approve: requires items

**Events:** `MaterialRequisitionApproved`, `MaterialRequisitionIssued`, `MaterialRequisitionCancelled`

---

### 13. SubcontractorWorkOrderStateMachine

**File:** `app/Domain/Manufacturing/SubcontractorWorkOrders/SubcontractorWorkOrderStateMachine.php`

```
draft → [assigned, cancelled]
assigned → [in_progress, cancelled]
in_progress → [completed, cancelled]
```

**Permission Methods:** `canAssign()`, `canStart()`, `canComplete()`, `canCancel()`, `canEdit()`, `canDelete()`

**Timestamps:** assigned_at/by, actual_start_date + started_at/by, actual_end_date + completed_at/by + completion_percentage=100, cancelled_at/by + reason

**Events:** `SubcontractorWorkOrderAssigned`, `SubcontractorWorkOrderStarted`, `SubcontractorWorkOrderCompleted`, `SubcontractorWorkOrderCancelled`

---

### 14. StockOpnameStateMachine

**File:** `app/Domain/Inventory/StockOpname/StockOpnameStateMachine.php`

```
draft → [counting, cancelled]
counting → [reviewed, cancelled]
reviewed → [approved, counting, cancelled]
approved → [completed]
```

**Permission Methods:** `canStartCounting()`, `canSubmitForReview()`, `canApprove()`, `canReject()`, `canComplete()`, `canCancel()`, `canEdit()`

**Guards:** StartCounting: requires items; SubmitForReview: all items must be counted

**Timestamps:** counting_started_at + counted_by, reviewed_at + reviewed_by, approved_at + approved_by, completed_at, cancelled_at

---

### 15. SolarProposalStateMachine

**File:** `app/Domain/Solar/Proposals/SolarProposalStateMachine.php`

```
draft → [sent]
sent → [accepted, rejected, expired]
```

**Permission Methods:** `canSend()`, `canAccept()`, `canReject()`, `canEdit()`, `canDelete()`

**Guards:** Send: variant_group_id && financial_analysis not null; Accept/Reject: not expired

**Timestamps:** sent_at + public_token (UUID) + public_token_expires_at, accepted_at, rejected_at + reason

---

## Creating a New State Machine

### Step 1: Create the Class

```php
<?php

namespace App\Domain\YourModule\YourFeature;

use App\Domain\Core\AbstractStateMachine;
use App\Enums\DocumentStatus;
use App\Models\YourModule\YourModel;

class YourModelStateMachine extends AbstractStateMachine
{
    public function __construct(
        DocumentStatus $initialStatus,
        private YourModel $model
    ) {
        parent::__construct($initialStatus);
    }

    public static function fromModel(YourModel $model): self
    {
        return new self($model->status, $model);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Submitted,
                DocumentStatus::Cancelled,
            ],
            DocumentStatus::Submitted->value => [
                DocumentStatus::Approved,
                DocumentStatus::Rejected,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Completed,
            ],
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->model->status = $status;
        $this->model->save();
    }

    // Permission methods
    public function canSubmit(): bool
    {
        return $this->model->status === DocumentStatus::Draft
            && $this->model->items()->exists();
    }

    public function canApprove(): bool
    {
        return $this->model->status === DocumentStatus::Submitted;
    }

    // Lifecycle hooks
    protected function afterSubmitted(): void
    {
        $this->model->submitted_at = now();
        $this->model->submitted_by = auth()->id();
        $this->model->save();

        event(new YourModelSubmitted($this->model));
    }

    protected function afterApproved(): void
    {
        $this->model->approved_at = now();
        $this->model->approved_by = auth()->id();
        $this->model->save();

        event(new YourModelApproved($this->model));
    }
}
```

### Step 2: Create Service Wrapper Methods

**Every status transition gets a semantic service method.** This provides:
- Clear API for controllers
- Business rule enforcement
- Consistent error handling
- Single point of entry for each transition

```php
class YourModelService
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher
    ) {}

    // Semantic wrapper methods - one per transition
    public function submit(YourModel $model): YourModel
    {
        return $this->executeTransition($model, DocumentStatus::Submitted, 'submit');
    }

    public function approve(YourModel $model): YourModel
    {
        return $this->executeTransition($model, DocumentStatus::Approved, 'approve');
    }

    public function reject(YourModel $model, ?string $reason = null): YourModel
    {
        return $this->executeTransition($model, DocumentStatus::Rejected, 'reject', [
            'reason' => $reason,
        ]);
    }

    public function markAsPaid(YourModel $model): YourModel
    {
        return $this->executeTransition($model, DocumentStatus::Paid, 'mark as paid');
    }

    public function markAsOverdue(YourModel $model): YourModel
    {
        return $this->executeTransition($model, DocumentStatus::Overdue, 'mark as overdue');
    }

    // Shared transition logic
    private function executeTransition(
        YourModel $model,
        DocumentStatus $targetStatus,
        string $actionName,
        array $context = []
    ): YourModel {
        $stateMachine = YourModelStateMachine::fromModel($model, $this->eventDispatcher);

        if (!$stateMachine->canTransitionTo($targetStatus)) {
            throw StateTransitionException::actionNotAvailable(
                $actionName,
                $model->status->value
            );
        }

        $stateMachine->transitionTo($targetStatus, $context);

        return $model->fresh();
    }
}
```

### Service Method Naming Conventions

| Transition Type | Method Name | Example |
|-----------------|-------------|---------|
| Submit for approval | `submit()` | `$service->submit($quotation)` |
| Approve | `approve()` | `$service->approve($po)` |
| Reject | `reject()` | `$service->reject($po, 'reason')` |
| Send/Post | `send()` | `$service->send($invoice)` |
| Cancel | `cancel()` | `$service->cancel($order)` |
| Complete | `complete()` | `$service->complete($workOrder)` |
| Mark as status | `markAs{Status}()` | `$service->markAsPaid($invoice)` |
| Void/Reverse | `void()` | `$service->void($invoice)` |

**Why wrapper methods matter:**
- Controllers call `$service->markAsPaid($invoice)` not `$sm->transitionTo()`
- Business rules centralized in service, not scattered
- Easy to add logging, authorization, notifications
- Testable via service interface

---

## Common Timestamp Fields

| Transition | Timestamp Field | User Field |
|------------|-----------------|------------|
| submitted | `submitted_at` | `submitted_by` |
| approved | `approved_at` | `approved_by` |
| rejected | `rejected_at` | `rejected_by` |
| cancelled | `cancelled_at` | `cancelled_by` |
| completed | `completed_at` | `completed_by` |
| shipped | `shipped_at` | `shipped_by` |
| delivered | `delivered_at` | `delivered_by` |

---

## State Machine Validation Pattern

```php
protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
{
    // Validate items exist before submission
    if ($to === DocumentStatus::Submitted && !$this->model->items()->exists()) {
        throw new StateTransitionException::actionNotAvailable(
            action: 'submit',
            currentState: $from->value,
            reason: 'Document must have at least one item.'
        );
    }
}
```
