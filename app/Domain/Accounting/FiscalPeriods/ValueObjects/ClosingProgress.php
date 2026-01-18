<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\ValueObjects;

use App\Domain\Accounting\FiscalPeriods\Enums\ClosingStep;
use DateTimeImmutable;

/**
 * Tracks progress through the multi-step closing process.
 *
 * Provides:
 * - Current step tracking
 * - Completion percentage calculation
 * - Journal entry IDs for rollback support
 * - Timestamps for audit trail
 */
final readonly class ClosingProgress
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @param  array<string, StepProgress>  $steps  Progress for each step, keyed by step value
     */
    public function __construct(
        public array $steps,
        public string $status = self::STATUS_PENDING,
        public ?ClosingStep $currentStep = null,
        public ?string $failureReason = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $completedAt = null
    ) {}

    /**
     * Create initial progress with all steps pending.
     */
    public static function initial(): self
    {
        $steps = [];
        foreach (ClosingStep::cases() as $step) {
            $steps[$step->value] = StepProgress::pending($step);
        }

        return new self($steps);
    }

    /**
     * Create from stored array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $steps = [];
        foreach ($data['steps'] ?? [] as $stepValue => $stepData) {
            $step = ClosingStep::from($stepValue);
            $steps[$stepValue] = new StepProgress(
                step: $step,
                status: $stepData['status'],
                journalEntryId: $stepData['journal_entry_id'] ?? null,
                error: $stepData['error'] ?? null,
                startedAt: isset($stepData['started_at'])
                    ? new DateTimeImmutable($stepData['started_at'])
                    : null,
                completedAt: isset($stepData['completed_at'])
                    ? new DateTimeImmutable($stepData['completed_at'])
                    : null
            );
        }

        return new self(
            steps: $steps,
            status: $data['status'] ?? self::STATUS_PENDING,
            currentStep: isset($data['current_step'])
                ? ClosingStep::from($data['current_step'])
                : null,
            failureReason: $data['failure_reason'] ?? null,
            startedAt: isset($data['started_at'])
                ? new DateTimeImmutable($data['started_at'])
                : null,
            completedAt: isset($data['completed_at'])
                ? new DateTimeImmutable($data['completed_at'])
                : null
        );
    }

    /**
     * Start the closing process.
     */
    public function start(): self
    {
        $firstStep = ClosingStep::inOrder()[0];

        return new self(
            steps: $this->steps,
            status: self::STATUS_IN_PROGRESS,
            currentStep: $firstStep,
            startedAt: new DateTimeImmutable
        );
    }

    /**
     * Mark current step as in progress.
     */
    public function startStep(ClosingStep $step): self
    {
        $steps = $this->steps;
        $steps[$step->value] = $steps[$step->value]->start();

        return new self(
            steps: $steps,
            status: self::STATUS_IN_PROGRESS,
            currentStep: $step,
            startedAt: $this->startedAt ?? new DateTimeImmutable
        );
    }

    /**
     * Complete a step and advance to the next.
     */
    public function completeStep(ClosingStep $step, ?int $journalEntryId = null): self
    {
        $steps = $this->steps;
        $steps[$step->value] = $steps[$step->value]->complete($journalEntryId);

        $nextStep = $step->next();
        $isComplete = $nextStep === null;

        return new self(
            steps: $steps,
            status: $isComplete ? self::STATUS_COMPLETED : self::STATUS_IN_PROGRESS,
            currentStep: $nextStep,
            startedAt: $this->startedAt,
            completedAt: $isComplete ? new DateTimeImmutable : null
        );
    }

    /**
     * Mark the process as failed at a specific step.
     */
    public function fail(ClosingStep $step, string $reason): self
    {
        $steps = $this->steps;
        $steps[$step->value] = $steps[$step->value]->fail($reason);

        return new self(
            steps: $steps,
            status: self::STATUS_FAILED,
            currentStep: $step,
            failureReason: $reason,
            startedAt: $this->startedAt
        );
    }

    /**
     * Get completion percentage (0-100).
     */
    public function getPercentComplete(): int
    {
        $total = count($this->steps);
        if ($total === 0) {
            return 0;
        }

        $completed = count(array_filter(
            $this->steps,
            fn (StepProgress $step) => $step->isCompleted()
        ));

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Get all journal entry IDs created during closing (for rollback).
     *
     * @return array<int>
     */
    public function getJournalEntryIds(): array
    {
        $ids = [];
        foreach ($this->steps as $step) {
            if ($step->journalEntryId !== null) {
                $ids[] = $step->journalEntryId;
            }
        }

        return $ids;
    }

    /**
     * Check if process is complete.
     */
    public function isComplete(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if process failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if process is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Get completed steps.
     *
     * @return array<ClosingStep>
     */
    public function getCompletedSteps(): array
    {
        $completed = [];
        foreach ($this->steps as $stepProgress) {
            if ($stepProgress->isCompleted()) {
                $completed[] = $stepProgress->step;
            }
        }

        return $completed;
    }

    /**
     * Convert to array for storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $steps = [];
        foreach ($this->steps as $stepValue => $stepProgress) {
            $steps[$stepValue] = $stepProgress->toArray();
        }

        return [
            'steps' => $steps,
            'status' => $this->status,
            'current_step' => $this->currentStep?->value,
            'failure_reason' => $this->failureReason,
            'started_at' => $this->startedAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
            'percent_complete' => $this->getPercentComplete(),
        ];
    }
}

/**
 * Progress tracking for an individual step.
 */
final readonly class StepProgress
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public function __construct(
        public ClosingStep $step,
        public string $status = self::STATUS_PENDING,
        public ?int $journalEntryId = null,
        public ?string $error = null,
        public ?DateTimeImmutable $startedAt = null,
        public ?DateTimeImmutable $completedAt = null
    ) {}

    /**
     * Create a pending step.
     */
    public static function pending(ClosingStep $step): self
    {
        return new self($step, self::STATUS_PENDING);
    }

    /**
     * Mark step as started.
     */
    public function start(): self
    {
        return new self(
            step: $this->step,
            status: self::STATUS_IN_PROGRESS,
            startedAt: new DateTimeImmutable
        );
    }

    /**
     * Mark step as completed.
     */
    public function complete(?int $journalEntryId = null): self
    {
        return new self(
            step: $this->step,
            status: self::STATUS_COMPLETED,
            journalEntryId: $journalEntryId,
            startedAt: $this->startedAt,
            completedAt: new DateTimeImmutable
        );
    }

    /**
     * Mark step as failed.
     */
    public function fail(string $error): self
    {
        return new self(
            step: $this->step,
            status: self::STATUS_FAILED,
            error: $error,
            startedAt: $this->startedAt
        );
    }

    /**
     * Mark step as skipped.
     */
    public function skip(): self
    {
        return new self(
            step: $this->step,
            status: self::STATUS_SKIPPED
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'step' => $this->step->value,
            'label' => $this->step->label(),
            'order' => $this->step->order(),
            'status' => $this->status,
            'journal_entry_id' => $this->journalEntryId,
            'error' => $this->error,
            'started_at' => $this->startedAt?->format('c'),
            'completed_at' => $this->completedAt?->format('c'),
        ];
    }
}
