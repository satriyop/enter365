<?php

declare(strict_types=1);

namespace App\Domain\Sales\SalesReturns;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Sales\SalesReturn;

class SalesReturnStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private SalesReturn $sr;

    public function __construct(
        DocumentStatus $initialStatus,
        SalesReturn $sr,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->sr = $sr;
    }

    public static function fromSalesReturn(
        SalesReturn $sr,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($sr->status, $sr, $eventDispatcher);
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
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'sales_return_id' => $this->sr->id,
            'return_number' => $this->sr->return_number,
            'contact_id' => $this->sr->contact_id,
            'invoice_id' => $this->sr->invoice_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->sr->status = $status;
        $this->sr->save();
    }

    protected function getDocumentType(): string
    {
        return 'Sales Return';
    }

    protected function getDocumentId(): int
    {
        return $this->sr->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Sales\SalesReturns\Events\SalesReturnStatusChanged::class;
    }

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->sr->items()->exists();
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canReject(): bool
    {
        return $this->currentStatus === DocumentStatus::Submitted;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === DocumentStatus::Approved;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Submitted,
            DocumentStatus::Approved,
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

    protected function beforeSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->sr->items()->exists()) {
            throw new \InvalidArgumentException('Sales return tidak dapat diajukan karena tidak memiliki item.');
        }
    }

    protected function beforeApproved(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeRejected(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->sr->update([
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        $this->eventDispatcher->dispatch(new Events\SalesReturnSubmitted(
            $this->sr->id,
            $this->sr->return_number,
            $this->sr->contact_id,
            $this->sr->invoice_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->sr->update([
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->eventDispatcher->dispatch(new Events\SalesReturnApproved(
            $this->sr->id,
            $this->sr->return_number,
            $this->sr->contact_id,
            $this->sr->invoice_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['rejection_reason'] ?? null;

        $this->sr->update([
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        $this->eventDispatcher->dispatch(new Events\SalesReturnRejected(
            $this->sr->id,
            $this->sr->return_number,
            $this->sr->contact_id,
            $this->sr->invoice_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->sr->update([
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);

        $this->eventDispatcher->dispatch(new Events\SalesReturnCompleted(
            $this->sr->id,
            $this->sr->return_number,
            $this->sr->contact_id,
            $this->sr->invoice_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['reason'] ?? null;

        $this->eventDispatcher->dispatch(new Events\SalesReturnCancelled(
            $this->sr->id,
            $this->sr->return_number,
            $this->sr->contact_id,
            $this->sr->invoice_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }
}
