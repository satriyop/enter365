<?php

declare(strict_types=1);

namespace App\Contracts\Accounting;

use App\Domain\Accounting\FiscalPeriods\Enums\ClosingStep;
use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingChecklist;
use App\Domain\Accounting\FiscalPeriods\ValueObjects\ClosingProgress;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;

/**
 * Interface for Year-End Closing operations.
 */
interface YearEndCloseServiceInterface
{
    /**
     * Execute the full closing process for a fiscal period.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function executeClose(FiscalPeriod $period, array $options = []): array;

    /**
     * Execute a single closing step.
     */
    public function executeStep(FiscalPeriod $period, ClosingStep $step, ClosingProgress $progress): ClosingProgress;

    /**
     * Get the closing checklist for a period.
     */
    public function getClosingChecklist(FiscalPeriod $period): ClosingChecklist;

    /**
     * Rollback a partially completed closing.
     */
    public function rollbackClose(FiscalPeriod $period, ClosingProgress $progress): void;

    /**
     * Create opening balance entries for the next period.
     */
    public function createOpeningBalanceEntry(FiscalPeriod $closedPeriod, FiscalPeriod $newPeriod): ?JournalEntry;
}
