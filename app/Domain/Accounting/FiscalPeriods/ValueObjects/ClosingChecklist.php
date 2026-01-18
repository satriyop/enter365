<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\ValueObjects;

/**
 * Immutable representation of fiscal period closing checklist.
 *
 * Contains validation results for pre-close checks, separating:
 * - Blocking errors (must be resolved before closing)
 * - Warnings (can proceed but should be reviewed)
 */
final readonly class ClosingChecklist
{
    /**
     * @param  array<string, ChecklistItem>  $items
     */
    public function __construct(
        public array $items
    ) {}

    /**
     * Create from an array of check results.
     *
     * @param  array<string, array{status: string, count: int, message: string}>  $checks
     */
    public static function fromArray(array $checks): self
    {
        $items = [];
        foreach ($checks as $key => $check) {
            $items[$key] = new ChecklistItem(
                key: $key,
                status: $check['status'],
                count: $check['count'],
                message: $check['message']
            );
        }

        return new self($items);
    }

    /**
     * Check if the period can be closed (no blocking errors).
     */
    public function canClose(): bool
    {
        foreach ($this->items as $item) {
            if ($item->isBlocking()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all blocking errors.
     *
     * @return array<string>
     */
    public function getBlockingErrors(): array
    {
        $errors = [];
        foreach ($this->items as $item) {
            if ($item->isBlocking()) {
                $errors[] = $item->message;
            }
        }

        return $errors;
    }

    /**
     * Get all warnings (non-blocking issues).
     *
     * @return array<string>
     */
    public function getWarnings(): array
    {
        $warnings = [];
        foreach ($this->items as $item) {
            if ($item->isWarning()) {
                $warnings[] = $item->message;
            }
        }

        return $warnings;
    }

    /**
     * Get count of OK items.
     */
    public function getOkCount(): int
    {
        return count(array_filter(
            $this->items,
            fn (ChecklistItem $item) => $item->isOk()
        ));
    }

    /**
     * Get count of warning items.
     */
    public function getWarningCount(): int
    {
        return count(array_filter(
            $this->items,
            fn (ChecklistItem $item) => $item->isWarning()
        ));
    }

    /**
     * Get count of error items.
     */
    public function getErrorCount(): int
    {
        return count(array_filter(
            $this->items,
            fn (ChecklistItem $item) => $item->isBlocking()
        ));
    }

    /**
     * Get total number of checklist items.
     */
    public function getTotalCount(): int
    {
        return count($this->items);
    }

    /**
     * Convert to array for JSON serialization.
     *
     * @return array<string, array{status: string, count: int, message: string}>
     */
    public function toArray(): array
    {
        $result = [];
        foreach ($this->items as $key => $item) {
            $result[$key] = $item->toArray();
        }

        return $result;
    }

    /**
     * Get summary of checklist status.
     *
     * @return array{can_close: bool, errors: array<string>, warnings: array<string>, summary: string}
     */
    public function getSummary(): array
    {
        $canClose = $this->canClose();
        $errors = $this->getBlockingErrors();
        $warnings = $this->getWarnings();

        $summary = match (true) {
            ! empty($errors) => sprintf(
                '%d masalah harus diselesaikan sebelum penutupan',
                count($errors)
            ),
            ! empty($warnings) => sprintf(
                'Dapat ditutup dengan %d peringatan',
                count($warnings)
            ),
            default => 'Siap untuk ditutup',
        };

        return [
            'can_close' => $canClose,
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => $summary,
        ];
    }
}

/**
 * Individual checklist item.
 */
final readonly class ChecklistItem
{
    public function __construct(
        public string $key,
        public string $status,
        public int $count,
        public string $message
    ) {}

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }

    public function isBlocking(): bool
    {
        return $this->status === 'error';
    }

    /**
     * @return array{status: string, count: int, message: string}
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'count' => $this->count,
            'message' => $this->message,
        ];
    }
}
