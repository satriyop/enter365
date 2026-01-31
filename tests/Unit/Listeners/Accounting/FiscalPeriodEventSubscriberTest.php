<?php

declare(strict_types=1);

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosed;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosing;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodLocked;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodReopened;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodStatusChanged;
use App\Listeners\Accounting\FiscalPeriodEventSubscriber;
use Illuminate\Events\Dispatcher;

describe('FiscalPeriodEventSubscriber', function () {
    beforeEach(function () {
        $this->logger = Mockery::mock(ContextualLoggerInterface::class);
        $this->subscriber = new FiscalPeriodEventSubscriber($this->logger);
    });

    describe('subscribe', function () {
        it('subscribes to all fiscal period events', function () {
            $dispatcher = Mockery::mock(Dispatcher::class);

            $subscriptions = $this->subscriber->subscribe($dispatcher);

            expect($subscriptions)->toBeArray()
                ->and($subscriptions)->toHaveKey(FiscalPeriodStatusChanged::class)
                ->and($subscriptions)->toHaveKey(FiscalPeriodClosing::class)
                ->and($subscriptions)->toHaveKey(FiscalPeriodClosed::class)
                ->and($subscriptions)->toHaveKey(FiscalPeriodLocked::class)
                ->and($subscriptions)->toHaveKey(FiscalPeriodReopened::class);
        });
    });

    describe('handleStatusChanged', function () {
        it('logs status change with correct context', function () {
            $event = new FiscalPeriodStatusChanged(
                periodId: 1,
                fromStatus: FiscalPeriodStatus::Open,
                toStatus: FiscalPeriodStatus::Locked,
                userId: 10,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.status_changed', [
                    'period_id' => 1,
                    'from' => 'open',
                    'to' => 'locked',
                    'user_id' => 10,
                ]);

            $this->subscriber->handleStatusChanged($event);
        });
    });

    describe('handleClosing', function () {
        it('logs closing event with correct context', function () {
            $event = new FiscalPeriodClosing(
                periodId: 2,
                userId: 5,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.closing', [
                    'period_id' => 2,
                    'user_id' => 5,
                ]);

            $this->subscriber->handleClosing($event);
        });
    });

    describe('handleClosed', function () {
        it('logs closed event with net income and closing entry', function () {
            $event = new FiscalPeriodClosed(
                periodId: 3,
                netIncome: 15000000,
                closingEntryId: 42,
                userId: 8,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.closed', [
                    'period_id' => 3,
                    'net_income' => 15000000,
                    'closing_entry_id' => 42,
                    'user_id' => 8,
                ]);

            $this->subscriber->handleClosed($event);
        });

        it('logs closed event with negative net income (loss)', function () {
            $event = new FiscalPeriodClosed(
                periodId: 4,
                netIncome: -5000000,
                closingEntryId: 43,
                userId: 8,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.closed', [
                    'period_id' => 4,
                    'net_income' => -5000000,
                    'closing_entry_id' => 43,
                    'user_id' => 8,
                ]);

            $this->subscriber->handleClosed($event);
        });
    });

    describe('handleLocked', function () {
        it('logs locked event with correct context', function () {
            $event = new FiscalPeriodLocked(
                periodId: 5,
                userId: 12,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.locked', [
                    'period_id' => 5,
                    'user_id' => 12,
                ]);

            $this->subscriber->handleLocked($event);
        });
    });

    describe('handleReopened', function () {
        it('logs reopened event with correct context', function () {
            $event = new FiscalPeriodReopened(
                periodId: 6,
                userId: 3,
            );

            $this->logger->shouldReceive('logOperation')
                ->once()
                ->with('fiscal_period.reopened', [
                    'period_id' => 6,
                    'user_id' => 3,
                ]);

            $this->subscriber->handleReopened($event);
        });
    });
});
