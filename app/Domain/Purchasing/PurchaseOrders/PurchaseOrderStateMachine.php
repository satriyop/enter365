<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseOrders;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseOrder;
use Illuminate\Support\Facades\Event;

class PurchaseOrderStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private PurchaseOrder $purchaseOrder;

    public function __construct(DocumentStatus $initialStatus, PurchaseOrder $purchaseOrder)
    {
        parent::__construct($initialStatus);
        $this->purchaseOrder = $purchaseOrder;
    }

    public static function fromPurchaseOrder(PurchaseOrder $purchaseOrder): self
    {
        return new self($purchaseOrder->status, $purchaseOrder);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Submitted->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Submitted->value => [
                DocumentStatus::Approved->value,
                DocumentStatus::Rejected->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Partial->value,
                DocumentStatus::Received->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Partial->value => [
                DocumentStatus::Received->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Rejected->value => [
                DocumentStatus::Draft->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'purchase_order_id' => $this->purchaseOrder->id,
            'po_number' => $this->purchaseOrder->po_number,
            'vendor_id' => $this->purchaseOrder->contact_id,
            'total_amount' => $this->purchaseOrder->total,
            'currency' => $this->purchaseOrder->currency,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->purchaseOrder->status = $status;
        $this->purchaseOrder->save();
    }

    protected function getDocumentType(): string
    {
        return 'Purchase Order';
    }

    protected function getDocumentId(): int
    {
        return $this->purchaseOrder->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return Events\PurchaseOrderStatusChanged::class;
    }

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->purchaseOrder->items()->exists();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ], true);
    }

    public function canReceive(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ], true);
    }

    public function canRevise(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Approved,
            DocumentStatus::Rejected,
            DocumentStatus::Cancelled,
        ], true);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->canEdit();
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        if ($to === DocumentStatus::Submitted && ! $this->purchaseOrder->items()->exists()) {
            throw new \InvalidArgumentException('Purchase Order tidak dapat diajukan karena tidak memiliki item.');
        }
    }

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->updateTimestamps($from, $to);
    }

    private function updateTimestamps(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $updates = [];

        switch ($to) {
            case DocumentStatus::Submitted:
                $updates['submitted_at'] = now();
                $updates['submitted_by'] = $userId;
                break;
            case DocumentStatus::Approved:
                $updates['approved_at'] = now();
                $updates['approved_by'] = $userId;
                break;
            case DocumentStatus::Rejected:
                $updates['rejected_at'] = now();
                $updates['rejected_by'] = $userId;
                $updates['rejection_reason'] = $this->context['rejection_reason'] ?? '';
                break;
            case DocumentStatus::Cancelled:
                $updates['cancelled_at'] = now();
                $updates['cancelled_by'] = $userId;
                $updates['cancellation_reason'] = $this->context['cancellation_reason'] ?? '';
                break;
            case DocumentStatus::Partial:
                $updates['first_received_at'] = $this->purchaseOrder->first_received_at ?? now();
                break;
            case DocumentStatus::Received:
                $updates['fully_received_at'] = now();
                break;
        }

        if (! empty($updates)) {
            $this->purchaseOrder->update($updates);
        }
    }

    protected function beforeSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderSubmitted(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderApproved(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        $reason = $this->context['rejection_reason'] ?? '';
        Event::dispatch(new Events\PurchaseOrderRejected(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->getContextUserId(),
            now(),
            $reason
        ));
    }

    protected function afterRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $reason = $this->context['cancellation_reason'] ?? '';
        Event::dispatch(new Events\PurchaseOrderCancelled(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->getContextUserId(),
            now(),
            $reason
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforePartial(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderPartial(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->purchaseOrder->getReceivingProgress(),
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterPartial(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }

    protected function beforeReceived(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderReceived(
            $this->purchaseOrder->id,
            $this->purchaseOrder->po_number,
            $this->purchaseOrder->contact_id,
            $this->purchaseOrder->total,
            $this->purchaseOrder->currency,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterReceived(DocumentStatus $from, DocumentStatus $to): void
    {
        Event::dispatch(new Events\PurchaseOrderStatusChanged(
            $this->purchaseOrder->id,
            $from,
            $to,
            $this->getContextUserId()
        ));
    }
}
