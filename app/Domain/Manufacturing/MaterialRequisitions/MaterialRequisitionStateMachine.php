<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\MaterialRequisitions;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\MaterialRequisition;

class MaterialRequisitionStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private MaterialRequisition $mr;

    public function __construct(
        DocumentStatus $initialStatus,
        MaterialRequisition $mr,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->mr = $mr;
    }

    public static function fromMaterialRequisition(
        MaterialRequisition $mr,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($mr->status, $mr, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Approved->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Approved->value => [
                DocumentStatus::Issued->value,
                DocumentStatus::Partial->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Partial->value => [
                DocumentStatus::Issued->value,
                DocumentStatus::Cancelled->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'material_requisition_id' => $this->mr->id,
            'requisition_number' => $this->mr->requisition_number,
            'work_order_id' => $this->mr->work_order_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->mr->status = $status;
        $this->mr->save();
    }

    protected function getDocumentType(): string
    {
        return 'Material Requisition';
    }

    protected function getDocumentId(): int
    {
        return $this->mr->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Manufacturing\MaterialRequisitions\Events\MaterialRequisitionStatusChanged::class;
    }

    public function canApprove(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->mr->items()->exists();
    }

    public function canIssue(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Approved,
            DocumentStatus::Partial,
        ], true);
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Approved,
            DocumentStatus::Partial,
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

    protected function beforeApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->mr->items()->exists()) {
            throw new \InvalidArgumentException('Material requisition tidak dapat disetujui karena tidak memiliki item.');
        }
    }

    protected function beforeIssued(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforePartial(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterApproved(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->mr->update([
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->eventDispatcher->dispatch(new Events\MaterialRequisitionApproved(
            $this->mr->id,
            $this->mr->requisition_number,
            $this->mr->work_order_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterIssued(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->mr->update([
            'issued_by' => $userId,
            'issued_at' => now(),
        ]);

        $this->eventDispatcher->dispatch(new Events\MaterialRequisitionIssued(
            $this->mr->id,
            $this->mr->requisition_number,
            $this->mr->work_order_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterPartial(DocumentStatus $from, DocumentStatus $to): void
    {
        //
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        $this->eventDispatcher->dispatch(new Events\MaterialRequisitionCancelled(
            $this->mr->id,
            $this->mr->requisition_number,
            $this->mr->work_order_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }
}
