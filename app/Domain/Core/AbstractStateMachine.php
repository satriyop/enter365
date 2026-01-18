<?php

declare(strict_types=1);

namespace App\Domain\Core;

use App\Contracts\Events\EventDispatcherInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use Illuminate\Support\Str;

abstract class AbstractStateMachine
{
    protected DocumentStatus $currentStatus;

    protected array $context = [];

    protected EventDispatcherInterface $eventDispatcher;

    public function __construct(
        DocumentStatus $initialStatus,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->currentStatus = $initialStatus;
        $this->eventDispatcher = $eventDispatcher ?? app(EventDispatcherInterface::class);
    }

    abstract protected function getTransitions(): array;

    abstract protected function getContextData(): array;

    public function getCurrentStatus(): DocumentStatus
    {
        return $this->currentStatus;
    }

    public function canTransitionTo(DocumentStatus $target): bool
    {
        if ($this->currentStatus === $target) {
            return true;
        }

        $validTransitions = $this->getTransitions()[$this->currentStatus->value] ?? [];

        return in_array($target->value, $validTransitions, true);
    }

    public function getNextValidStatuses(): array
    {
        return $this->getTransitions()[$this->currentStatus->value] ?? [];
    }

    public function transitionTo(DocumentStatus $target, array $context = []): void
    {
        $from = $this->currentStatus;

        if (! $this->canTransitionTo($target)) {
            throw StateTransitionException::invalidTransition(
                $this->getDocumentType(),
                $from->label(),
                $target->label()
            );
        }

        $this->context = array_merge($this->getContextData(), $context);

        $this->beforeTransition($from, $target);
        $this->{'before'.$this->getHookName($target)}($from, $target);

        $this->performTransition($from, $target);

        $this->afterTransition($from, $target);
        $this->{'after'.$this->getHookName($target)}($from, $target);

        $this->eventDispatcher->dispatch(new ($this->getStatusChangedEvent())(
            $this->getDocumentId(),
            $from,
            $target,
            $this->getContextUserId()
        ));
    }

    protected function performTransition(DocumentStatus $from, DocumentStatus $target): void
    {
        $this->updateDocumentStatus($target);
        $this->currentStatus = $target;
    }

    abstract protected function updateDocumentStatus(DocumentStatus $status): void;

    abstract protected function getDocumentType(): string;

    abstract protected function getDocumentId(): int;

    abstract protected function getStatusChangedEvent(): string;

    protected function getContextUserId(): ?int
    {
        return $this->context['user_id'] ?? null;
    }

    protected function getHookName(DocumentStatus $status): string
    {
        return Str::studly($status->value);
    }

    protected function beforeTransition(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterTransition(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeSend(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterSend(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforePartial(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterPartial(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforePaid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterPaid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeOverdue(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterOverdue(DocumentStatus $from, DocumentStatus $to): void {}

    protected function beforeVoid(DocumentStatus $from, DocumentStatus $to): void {}

    protected function afterVoid(DocumentStatus $from, DocumentStatus $to): void {}
}
