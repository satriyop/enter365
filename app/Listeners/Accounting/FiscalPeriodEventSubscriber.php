<?php

declare(strict_types=1);

namespace App\Listeners\Accounting;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosed;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosing;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodLocked;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodReopened;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodStatusChanged;
use Illuminate\Events\Dispatcher;

/**
 * Handles all fiscal period-related events.
 */
class FiscalPeriodEventSubscriber
{
    public function __construct(
        private ContextualLoggerInterface $logger
    ) {}

    public function handleStatusChanged(FiscalPeriodStatusChanged $event): void
    {
        $this->logger->logOperation('fiscal_period.status_changed', [
            'period_id' => $event->periodId,
            'from' => $event->fromStatus->value,
            'to' => $event->toStatus->value,
            'user_id' => $event->userId,
        ]);
    }

    public function handleClosing(FiscalPeriodClosing $event): void
    {
        $this->logger->logOperation('fiscal_period.closing', [
            'period_id' => $event->periodId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleClosed(FiscalPeriodClosed $event): void
    {
        $this->logger->logOperation('fiscal_period.closed', [
            'period_id' => $event->periodId,
            'net_income' => $event->netIncome,
            'closing_entry_id' => $event->closingEntryId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleLocked(FiscalPeriodLocked $event): void
    {
        $this->logger->logOperation('fiscal_period.locked', [
            'period_id' => $event->periodId,
            'user_id' => $event->userId,
        ]);
    }

    public function handleReopened(FiscalPeriodReopened $event): void
    {
        $this->logger->logOperation('fiscal_period.reopened', [
            'period_id' => $event->periodId,
            'user_id' => $event->userId,
        ]);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            FiscalPeriodStatusChanged::class => 'handleStatusChanged',
            FiscalPeriodClosing::class => 'handleClosing',
            FiscalPeriodClosed::class => 'handleClosed',
            FiscalPeriodLocked::class => 'handleLocked',
            FiscalPeriodReopened::class => 'handleReopened',
        ];
    }
}
