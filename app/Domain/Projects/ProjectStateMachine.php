<?php

declare(strict_types=1);

namespace App\Domain\Projects;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Models\Projects\Project;

class ProjectStateMachine extends \App\Domain\Core\AbstractStateMachine
{
    private Project $project;

    public function __construct(
        DocumentStatus $initialStatus,
        Project $project,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        parent::__construct($initialStatus, $eventDispatcher);
        $this->project = $project;
    }

    public static function fromProject(
        Project $project,
        ?EventDispatcherInterface $eventDispatcher = null
    ): self {
        return new self($project->status, $project, $eventDispatcher);
    }

    protected function getTransitions(): array
    {
        return [
            DocumentStatus::Draft->value => [
                DocumentStatus::Planning->value,
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::Planning->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::InProgress->value => [
                DocumentStatus::OnHold->value,
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
            DocumentStatus::OnHold->value => [
                DocumentStatus::InProgress->value,
                DocumentStatus::Completed->value,
                DocumentStatus::Cancelled->value,
            ],
        ];
    }

    protected function getContextData(): array
    {
        return [
            'project_id' => $this->project->id,
            'project_number' => $this->project->project_number,
            'contact_id' => $this->project->contact_id,
        ];
    }

    protected function updateDocumentStatus(DocumentStatus $status): void
    {
        $this->project->status = $status;
        $this->project->save();
    }

    protected function getDocumentType(): string
    {
        return 'Project';
    }

    protected function getDocumentId(): int
    {
        return $this->project->id;
    }

    protected function getStatusChangedEvent(): string
    {
        return \App\Domain\Projects\Events\ProjectStatusChanged::class;
    }

    public function canStart(): bool
    {
        return in_array($this->currentStatus, [
            DocumentStatus::Draft,
            DocumentStatus::Planning,
        ], true);
    }

    public function canPutOnHold(): bool
    {
        return $this->currentStatus === DocumentStatus::InProgress;
    }

    public function canResume(): bool
    {
        return $this->currentStatus === DocumentStatus::OnHold;
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
            DocumentStatus::Planning,
        ], true);
    }

    public function canDelete(): bool
    {
        return $this->currentStatus === DocumentStatus::Draft;
    }

    protected function beforePlanning(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeInProgress(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeOnHold(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCompleted(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeCancelled(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterPlanning(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        $this->eventDispatcher->dispatch(new Events\ProjectStatusChanged(
            $this->project->id,
            $from,
            $to,
            $userId
        ));
    }

    protected function afterInProgress(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();

        if ($from === DocumentStatus::Draft || $from === DocumentStatus::Planning) {
            $this->project->update([
                'actual_start_date' => now(),
            ]);
        }

        $this->eventDispatcher->dispatch(new Events\ProjectStarted(
            $this->project->id,
            $this->project->project_number,
            $this->project->contact_id,
            $userId,
            now()
        ));
    }

    protected function afterOnHold(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['hold_reason'] ?? null;

        $this->eventDispatcher->dispatch(new Events\ProjectOnHold(
            $this->project->id,
            $this->project->project_number,
            $this->project->contact_id,
            $userId,
            now(),
            $reason
        ));
    }

    protected function afterCompleted(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $this->project->update([
            'actual_end_date' => now(),
            'progress_percentage' => 100,
        ]);

        $this->eventDispatcher->dispatch(new Events\ProjectCompleted(
            $this->project->id,
            $this->project->project_number,
            $this->project->contact_id,
            $userId,
            now()
        ));
    }

    protected function afterCancelled(DocumentStatus $from, DocumentStatus $to): void
    {
        $userId = $this->getContextUserId();
        $reason = $this->context['cancellation_reason'] ?? null;

        $this->eventDispatcher->dispatch(new Events\ProjectCancelled(
            $this->project->id,
            $this->project->project_number,
            $this->project->contact_id,
            $userId,
            now(),
            $reason
        ));
    }
}
