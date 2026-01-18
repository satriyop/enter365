<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\SubcontractorWorkOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\SubcontractorWorkOrder;

class SubcontractorWorkOrderStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private SubcontractorWorkOrder $scWo;

    public function __construct(
        DocumentStatus $initialStatus,
        SubcontractorWorkOrder $scWo,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->scWo = $scWo;
    }

    public static function fromSubcontractorWorkOrder(
        SubcontractorWorkOrder $scWo,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($scWo->status, $scWo, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Assigned->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Assigned->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::InProgress->value => [
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'subcontractor_work_order_id' => $this->scWo->id,
            'sc_wo_number' => $this->scWo->sc_wo_number,
            'subcontractor_id' => $this->scWo->subcontractor_id,
            'project_id' => $this->scWo->project_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->scWo->status = $status;
        $this->scWo->save();
    }

    protected function getDocumentType(): string
    {
        return 'Subcontractor Work Order';
    }

    protected function getDocumentId(): int
    {
        return $this->scWo->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Manufacturing\SubcontractorWorkOrders\Events\SubcontractorWorkOrderStatusChanged::class;
    }

    public function canAssign(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canStart(): bool
    {
        return $this->currentStatus === DocumentStatus::Assigned;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === DocumentStatus::InProgress;
    }

    public function canCancel(): bool
    {
        return ! in_array($this->currentStatus, [
            DocumentStatus::Completed,
            DocumentStatus::Cancelled,
        ], true);
    }

    public function canEdit(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Assigned,
        ], true);
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    protected function beforeAssigned(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeInProgress(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterAssigned(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $this->scWo->update([
            'assigned_at' => now(),
            'assigned_by' => $userId,
        ]);

        $this->eventDispatcher->dispatch(Events\SubcontractorWorkOrderAssigned::fromSubcontractorWorkOrder($this->scWo, $userId));
    }

    protected function afterInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $this->scWo->update([
            'actual_start_date' => now(),
            'started_at' => now(),
            'started_by' => $userId,
        ]);

        $this->eventDispatcher->dispatch(Events\SubcontractorWorkOrderStarted::fromSubcontractorWorkOrder($this->scWo, $userId));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $this->scWo->update([
            'actual_end_date' => now(),
            'completed_at' => now(),
            'completed_by' => $userId,
            'completion_percentage' => 100,
        ]);

        $this->eventDispatcher->dispatch(Events\SubcontractorWorkOrderCompleted::fromSubcontractorWorkOrder($this->scWo, $userId));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        $this->scWo->update([
            'cancelled_at' => now(),
            'cancelled_by' => $userId,
            'cancellation_reason' => $reason,
        ]);

        $this->eventDispatcher->dispatch(Events\SubcontractorWorkOrderCancelled::fromSubcontractorWorkOrder($this->scWo, $userId, $reason));
    }
}
