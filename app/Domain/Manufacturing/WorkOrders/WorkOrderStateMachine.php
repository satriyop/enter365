<?php

declare(strict_types=1);

namespace App\Domain\Manufacturing\WorkOrders;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Enums\DocumentStatus;
use App\Models\Manufacturing\WorkOrder;

class WorkOrderStateMachine extends AbstractStateMachine
{
    private WorkOrder $workOrder;

    public function __construct(
        DocumentStatus $initialStatus,
        WorkOrder $workOrder,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->workOrder = $workOrder;
        parent::__construct($initialStatus, $eventDispatcher);
    }

    public static function fromWorkOrder(
        WorkOrder $workOrder,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($workOrder->status, $workOrder, $eventDispatcher);
    }

    protected function registerGuards(): void
    {
        // Guard: Can only confirm if has items
        $this->addGuard(
            DocumentStatus::Draft->value,
            DocumentStatus::Confirmed->value,
            fn () => [
                'passes' => $this->workOrder->items()->exists(),
                'message' => 'Work order tidak memiliki item.',
            ]
        );

        // Guard: Can only complete if all sub-work orders are completed
        $this->addGuard(
            DocumentStatus::InProgress->value,
            DocumentStatus::Completed->value,
            fn () => [
                'passes' => $this->workOrder->subWorkOrders()
                    ->whereNotIn('status', [DocumentStatus::Completed, DocumentStatus::Cancelled])
                    ->count() === 0,
                'message' => 'Masih ada sub work order yang belum selesai.',
            ]
        );
    }

    protected function registerActions(): void
    {
        // Action: Update confirmed_by and confirmed_at when confirmed
        $this->addAction(
            DocumentStatus::Draft->value,
            DocumentStatus::Confirmed->value,
            fn () => $this->workOrder->update([
                'confirmed_by' => $this->getContextUserId(),
                'confirmed_at' => now(),
            ])
        );

        // Action: Update started_by and started_at when started
        $this->addAction(
            DocumentStatus::Confirmed->value,
            DocumentStatus::InProgress->value,
            fn () => $this->workOrder->update([
                'started_by' => $this->getContextUserId(),
                'started_at' => now(),
                'actual_start_date' => now(),
            ])
        );

        // Action: Update completed_by and completed_at when completed
        $this->addAction(
            DocumentStatus::InProgress->value,
            DocumentStatus::Completed->value,
            function () {
                if ($this->workOrder->quantity_completed <= 0) {
                    $this->workOrder->quantity_completed = $this->workOrder->quantity_ordered;
                }

                $this->workOrder->update([
                    'completed_by' => $this->getContextUserId(),
                    'completed_at' => now(),
                    'actual_end_date' => now(),
                ]);
            }
        );

        // Action: Update cancelled_by and cancelled_at when cancelled
        foreach ([DocumentStatus::Draft, DocumentStatus::Confirmed, DocumentStatus::InProgress] as $fromStatus) {
            $this->addAction(
                $fromStatus->value,
                DocumentStatus::Cancelled->value,
                fn () => $this->workOrder->update([
                    'cancelled_by' => $this->getContextUserId(),
                    'cancelled_at' => now(),
                    'cancellation_reason' => $this->transitionContext['cancellation_reason'] ?? null,
                ])
            );
        }
    }

    protected function recordHistory(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->workOrder->recordStatusChange(
            $from->value,
            $to->value,
            $this->getContextUserId(),
            $this->transitionContext
        );
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

    // Business rule helpers (delegate to canTransitionTo with guards)

    public function canConfirm(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Confirmed);
    }

    public function canStart(): bool
    {
        return $this->canTransitionTo(DocumentStatus::InProgress);
    }

    public function canComplete(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Completed);
    }

    public function canCancel(): bool
    {
        return $this->canTransitionTo(DocumentStatus::Cancelled);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    public function canDelete(): bool
    {
        return $this->canEdit();
    }

    /**
     * Get the work order model.
     */
    public function getWorkOrder(): WorkOrder
    {
        return $this->workOrder;
    }

    // Event dispatching hooks (fires domain events after status changes)

    protected function afterConfirmed(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\WorkOrderConfirmed(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\WorkOrderStarted(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\WorkOrderCompleted(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $this->getContextUserId(),
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $this->eventDispatcher->dispatch(new Events\WorkOrderCancelled(
            $this->workOrder->id,
            $this->workOrder->wo_number,
            $this->workOrder->project_id ?? 0,
            $this->getContextUserId(),
            now(),
            $this->transitionContext['cancellation_reason'] ?? null
        ));
    }
}
