<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;
use Illuminate\Support\Facades\Event;

class WorkOrderStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private WorkOrder $workOrder;

    public function __construct(DocumentStatus $initialStatus, WorkOrder $workOrder)
    {
        parent::__construct($initialStatus);
        $this->workOrder = $workOrder;
    }

    public static function fromWorkOrder(WorkOrder $workOrder): self
    {
        return new self($workOrder->status, $workOrder);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Confirmed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Confirmed->value => [
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
            'work_order_id' => $this->workOrder->id,
            'work_order_number' => $this->workOrder->wo_number,
            'project_id' => $this->workOrder->project_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->workOrder->status = $status;
        $this->workOrder->save();
    }

    protected function getDocumentType(): string
    {
        return 'Work Order';
    }

    protected function getDocumentId(): int
    {
        return $this->workOrder->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Manufacturing\WorkOrders\Events\WorkOrderStatusChanged::class;
    }

    public function canConfirm(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft
            && $this->workOrder->items()->exists();
    }

    public function canStart(): bool
    {
        return $this->currentStatus === DocumentStatus::Confirmed;
    }

    public function canComplete(): bool
    {
        if ($this->currentStatus !== DocumentStatus::InProgress) {
            return false;
        }

        $incompleteSubWos = $this->workOrder->subWorkOrders()
            ->whereNotIn('status', [DocumentStatus::Completed, DocumentStatus::Cancelled])
            ->count();

        return $incompleteSubWos === 0;
    }

    public function canCancel(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Confirmed,
            DocumentStatus::InProgress,
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

    protected function beforeConfirmed(DocumentStatus $from, DocumentStatus $to): void
    {
        if (! $this->workOrder->items()->exists()) {
            throw new \InvalidArgumentException('Work order tidak dapat dikonfirmasi karena tidak memiliki item.');
        }
    }

    protected function beforeInProgress(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterConfirmed(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->workOrder->update([
            'confirmed_by' => $userId,
            'confirmed_at' => now(),
        ]);

        Event::dispatch(new Events\WorkOrderConfirmed(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->workOrder->update([
            'started_by' => $userId,
            'started_at' => now(),
            'actual_start_date' => now(),
        ]);

        Event::dispatch(new Events\WorkOrderStarted(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        if ($this->workOrder->quantity_completed <= 0) {
            $this->workOrder->quantity_completed = $this->workOrder->quantity_ordered;
        }

        $this->workOrder->update([
            'completed_by' => $userId,
            'completed_at' => now(),
            'actual_end_date' => now(),
        ]);

        Event::dispatch(new Events\WorkOrderCompleted(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $userId,
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        $this->workOrder->update([
            'cancelled_by' => $userId,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);

        Event::dispatch(new Events\WorkOrderCancelled(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $userId,
            now(),
            $reason
        ));
    }
}
