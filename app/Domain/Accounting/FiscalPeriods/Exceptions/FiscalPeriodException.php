<?php

declare(strict_types=1);

namespace App\Domain\Accounting\FiscalPeriods\Exceptions;

use App\Domain\Accounting\FiscalPeriods\Enums\ClosingStep;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Exceptions\Domain\DomainException;

/**
 * Exception for fiscal period operations.
 *
 * Use when:
 * - Invalid status transition is attempted
 * - Period cannot be closed due to checklist errors
 * - Closing step fails during execution
 * - Period is already in a terminal state
 */
class FiscalPeriodException extends DomainException
{
    protected string $errorCode = 'FISCAL_PERIOD_ERROR';

    protected int $statusCode = 422;

    /**
     * Create exception for invalid status transition.
     */
    public static function invalidTransition(
        FiscalPeriodStatus $currentStatus,
        FiscalPeriodStatus $targetStatus
    ): self {
        return new self(
            "Tidak dapat mengubah status periode fiskal dari '{$currentStatus->label()}' ke '{$targetStatus->label()}'.",
            [
                'current_status' => $currentStatus->value,
                'target_status' => $targetStatus->value,
            ]
        );
    }

    /**
     * Create exception when period cannot be closed due to checklist errors.
     *
     * @param  array<string>  $errors
     */
    public static function cannotCloseWithErrors(array $errors): self
    {
        $exception = new self(
            'Periode fiskal tidak dapat ditutup karena terdapat masalah yang harus diselesaikan.',
            ['blocking_errors' => $errors]
        );
        $exception->errorCode = 'CLOSING_CHECKLIST_FAILED';

        return $exception;
    }

    /**
     * Create exception when period is already closed.
     */
    public static function alreadyClosed(int $periodId): self
    {
        $exception = new self(
            'Periode fiskal sudah ditutup.',
            ['period_id' => $periodId]
        );
        $exception->errorCode = 'PERIOD_ALREADY_CLOSED';

        return $exception;
    }

    /**
     * Create exception when a closing step fails.
     */
    public static function closingStepFailed(
        ClosingStep $step,
        string $reason,
        ?\Throwable $previous = null
    ): self {
        $exception = new self(
            "Langkah penutupan '{$step->label()}' gagal: {$reason}",
            [
                'step' => $step->value,
                'step_order' => $step->order(),
                'reason' => $reason,
            ],
            $previous
        );
        $exception->errorCode = 'CLOSING_STEP_FAILED';

        return $exception;
    }

    /**
     * Create exception when period cannot be reopened.
     */
    public static function cannotReopen(int $periodId, string $reason): self
    {
        $exception = new self(
            "Periode fiskal tidak dapat dibuka kembali: {$reason}",
            [
                'period_id' => $periodId,
                'reason' => $reason,
            ]
        );
        $exception->errorCode = 'CANNOT_REOPEN_PERIOD';

        return $exception;
    }

    /**
     * Create exception when period is locked and operation requires open status.
     */
    public static function periodLocked(int $periodId): self
    {
        $exception = new self(
            'Periode fiskal dikunci. Buka kunci periode terlebih dahulu untuk melakukan operasi ini.',
            ['period_id' => $periodId]
        );
        $exception->errorCode = 'PERIOD_LOCKED';

        return $exception;
    }

    /**
     * Create exception when closing process is already in progress.
     */
    public static function closingInProgress(int $periodId): self
    {
        $exception = new self(
            'Proses penutupan sedang berjalan. Tunggu hingga selesai atau batalkan proses yang sedang berjalan.',
            ['period_id' => $periodId]
        );
        $exception->errorCode = 'CLOSING_IN_PROGRESS';

        return $exception;
    }

    /**
     * Create exception when required account is missing.
     */
    public static function missingRequiredAccount(string $accountType, string $accountCode): self
    {
        $exception = new self(
            "Akun {$accountType} dengan kode {$accountCode} tidak ditemukan. Akun ini diperlukan untuk proses penutupan.",
            [
                'account_type' => $accountType,
                'account_code' => $accountCode,
            ]
        );
        $exception->errorCode = 'MISSING_CLOSING_ACCOUNT';

        return $exception;
    }
}
