<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Enums;

/**
 * Steps in the year-end closing process.
 *
 * Each step represents a discrete action in the multi-step closing workflow.
 * Steps must be executed in order and some are reversible for rollback support.
 */
enum ClosingStep: string
{
    case LockPeriod = 'lock_period';
    case ValidateChecklist = 'validate_checklist';
    case CloseTemporaryAccounts = 'close_temporary_accounts';
    case CloseDividends = 'close_dividends';
    case MarkPeriodClosed = 'mark_period_closed';
    case CreateNextPeriod = 'create_next_period';
    case PopulateOpeningBalances = 'populate_opening_balances';

    /**
     * Get human-readable label (Indonesian).
     */
    public function label(): string
    {
        return match ($this) {
            self::LockPeriod => 'Kunci Periode',
            self::ValidateChecklist => 'Validasi Checklist',
            self::CloseTemporaryAccounts => 'Tutup Akun Sementara',
            self::CloseDividends => 'Tutup Dividen/Prive',
            self::MarkPeriodClosed => 'Tandai Periode Ditutup',
            self::CreateNextPeriod => 'Buat Periode Berikutnya',
            self::PopulateOpeningBalances => 'Isi Saldo Awal',
        };
    }

    /**
     * Get the execution order for this step (1-based).
     */
    public function order(): int
    {
        return match ($this) {
            self::LockPeriod => 1,
            self::ValidateChecklist => 2,
            self::CloseTemporaryAccounts => 3,
            self::CloseDividends => 4,
            self::MarkPeriodClosed => 5,
            self::CreateNextPeriod => 6,
            self::PopulateOpeningBalances => 7,
        };
    }

    /**
     * Check if this step creates reversible journal entries.
     *
     * Non-reversible steps should be executed with more care
     * as rollback will require manual intervention.
     */
    public function isReversible(): bool
    {
        return match ($this) {
            self::LockPeriod,
            self::ValidateChecklist => true,
            self::CloseTemporaryAccounts,
            self::CloseDividends,
            self::PopulateOpeningBalances => true, // Journal entries can be reversed
            self::MarkPeriodClosed,
            self::CreateNextPeriod => false, // Requires manual cleanup
        };
    }

    /**
     * Check if this step creates a journal entry.
     */
    public function createsJournalEntry(): bool
    {
        return in_array($this, [
            self::CloseTemporaryAccounts,
            self::CloseDividends,
            self::PopulateOpeningBalances,
        ], true);
    }

    /**
     * Get steps in execution order.
     *
     * @return array<self>
     */
    public static function inOrder(): array
    {
        $steps = self::cases();
        usort($steps, fn (self $a, self $b) => $a->order() <=> $b->order());

        return $steps;
    }

    /**
     * Get the next step after this one, or null if this is the last step.
     */
    public function next(): ?self
    {
        $steps = self::inOrder();
        $currentIndex = array_search($this, $steps, true);

        if ($currentIndex === false || $currentIndex === count($steps) - 1) {
            return null;
        }

        return $steps[$currentIndex + 1];
    }

    /**
     * Get total number of steps.
     */
    public static function totalSteps(): int
    {
        return count(self::cases());
    }
}
