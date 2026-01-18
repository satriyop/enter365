<?php

declare(strict_types=1);

namespace App\Domain\Purchasing\PurchaseReturns;

use App\Enums\DocumentStatus;
use App\Models\Purchasing\PurchaseReturn;
use Illuminate\Support\Facades\Event;

class PurchaseReturnStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private PurchaseReturn $pr;

    public function __construct(DocumentStatus $initialStatus, PurchaseReturn $pr)
    {
        parent::__construct($initialStatus);
        $this->pr = $pr;
    }

    public static function fromPurchaseReturn(PurchaseReturn $pr): self
    {
        return new self($pr->status, $pr);
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
            'purchase_return_id' => $this->pr->id,
            'return_number' => $this->pr->return_number,
            'contact_id' => $this->pr->contact_id,
            'bill_id' => $this->pr->bill_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->pr->status = $status;
        $this->pr->save();
    }

    protected function getDocumentType(): string
    {
        return 'Purchase Return';
    }

    protected function getDocumentId(): int
    {
        return $this->pr->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Purchasing\PurchaseReturns\Events\PurchaseReturnStatusChanged::class;
    }

    public function canSubmit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->pr->items()->exists();
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
        if (! $this->pr->items()->exists()) {
            throw new \InvalidArgumentException('Purchase return tidak dapat diajukan karena tidak memiliki item.');
        }
    }

    protected function beforeApproved(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeRejected(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterSubmitted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->pr->update([
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ]);

        Event::dispatch(new Events\PurchaseReturnSubmitted(
            $this->pr->id,
            $this->pr->return_number,
            $this->pr->contact_id,
            $this->pr->bill_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->pr->update([
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        Event::dispatch(new Events\PurchaseReturnApproved(
            $this->pr->id,
            $this->pr->return_number,
            $this->pr->contact_id,
            $this->pr->bill_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterRejected(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['rejection_reason'] ?? null;

        $this->pr->update([
            'rejected_by' => $userId,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ]);

        Event::dispatch(new Events\PurchaseReturnRejected(
            $this->pr->id,
            $this->pr->return_number,
            $this->pr->contact_id,
            $this->pr->bill_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->pr->update([
            'completed_by' => $userId,
            'completed_at' => now(),
        ]);

        Event::dispatch(new Events\PurchaseReturnCompleted(
            $this->pr->id,
            $this->pr->return_number,
            $this->pr->contact_id,
            $this->pr->bill_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        Event::dispatch(new Events\PurchaseReturnCancelled(
            $this->pr->id,
            $this->pr->return_number,
            $this->pr->contact_id,
            $this->pr->bill_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }
}
