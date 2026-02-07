<?php

declare(strict_types=1);

namespace App\Domain\Projects\Tasks;

use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Core\AbstractStateMachine;
use App\Domain\Projects\Tasks\Events\TaskCancelled;
use App\Domain\Projects\Tasks\Events\TaskCompleted;
use App\Domain\Projects\Tasks\Events\TaskStarted;
use App\Domain\Projects\Tasks\Events\TaskStatusChanged;
use App\Enums\DocumentStatus;
use App\Models\Projects\Task;

class TaskStateMachine extends AbstractStateMachine
{
    private Task $task;

    public function __construct(
        DocumentStatus $initialStatus,
        Task $task,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->task = $task;
        parent::__construct($initialStatus, $eventDispatcher);
    }

    public static function fromTask(
        Task $task,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($task->status, $task, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Todo->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::InProgress->value => [
                DocumentStatus::Done->value,
                DocumentStatus::Cancelled->value,
            ],
        ];
    }

    protected function registerGuards(): void
    {
        // Cannot start if blocking dependencies are incomplete
        $this->addGuard(DocumentStatus::Todo->value, DocumentStatus::InProgress->value, function () {
            if ($this->task->hasBlockingDependencies()) {
                return [
                    'passes' => false,
                    'message' => 'Tugas tidak dapat dimulai karena masih ada dependensi yang belum selesai.',
                ];
            }

            return true;
        });

        // Cannot complete if subtasks are still open
        $this->addGuard(DocumentStatus::InProgress->value, DocumentStatus::Done->value, function () {
            if ($this->task->hasUnfinishedSubtasks()) {
                return [
                    'passes' => false,
                    'message' => 'Tugas tidak dapat diselesaikan karena masih ada sub-tugas yang belum selesai.',
                ];
            }

            return true;
        });
    }

    protected function getContextData(): array
    {
        return [
            'task_id' => $this->task->id,
            'task_number' => $this->task->task_number,
            'project_id' => $this->task->project_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->task->status = $status;
        $this->task->save();
    }

    protected function getDocumentType(): string
    {
        return 'Task';
    }

    protected function getDocumentId(): int
    {
        return $this->task->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return TaskStatusChanged::class;
    }

    protected function afterInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        if ($from === DocumentStatus::Todo && ! $this->task->start_date) {
            $this->task->update(['start_date' => now()]);
        }

        $this->eventDispatcher->dispatch(new TaskStarted(
            $this->task->id,
            $this->task->task_number,
            $this->task->project_id,
            $userId,
            now()
        ));
    }

    protected function afterDone(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $this->task->update(['completed_at' => now()]);

        $this->eventDispatcher->dispatch(new TaskCompleted(
            $this->task->id,
            $this->task->task_number,
            $this->task->project_id,
            $userId,
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        $this->eventDispatcher->dispatch(new TaskCancelled(
            $this->task->id,
            $this->task->task_number,
            $this->task->project_id,
            $userId,
            now(),
            $reason
        ));
    }

    public function canStart(): bool
    {
        return $this->currentStatus === DocumentStatus::Todo;
    }

    public function canComplete(): bool
    {
        return $this->currentStatus === DocumentStatus::InProgress;
    }

    public function canCancel(): bool
    {
        return ! in_array($this->currentStatus, [
            DocumentStatus::Done,
            DocumentStatus::Cancelled,
        ], true);
    }

    public function canEdit(): bool
    {
        return $this->currentStatus === DocumentStatus::Todo;
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Todo;
    }
}
