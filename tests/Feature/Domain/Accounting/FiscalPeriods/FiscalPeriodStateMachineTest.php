<?php

use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosed;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodClosing;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodLocked;
use App\Domain\Accounting\FiscalPeriods\Events\FiscalPeriodStatusChanged;
use App\Domain\Accounting\FiscalPeriods\Exceptions\FiscalPeriodException;
use App\Domain\Accounting\FiscalPeriods\FiscalPeriodStateMachine;
use App\Infrastructure\Events\NullEventDispatcher;
use App\Models\Accounting\FiscalPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('FiscalPeriodStateMachine', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    });

    describe('Valid Transitions', function () {

        it('can transition from Open to Locked', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Open,
                'is_closed' => false,
                'is_locked' => false,
            ]);

            $dispatcher = new NullEventDispatcher;
            $stateMachine = new FiscalPeriodStateMachine($period, $dispatcher);

            $stateMachine->lock();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Locked);
            expect($period->is_locked)->toBeTrue();
            expect($dispatcher->hasDispatched(FiscalPeriodLocked::class))->toBeTrue();
            expect($dispatcher->hasDispatched(FiscalPeriodStatusChanged::class))->toBeTrue();
        });

        it('can transition from Locked to Closing', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Locked,
                'is_closed' => false,
                'is_locked' => true,
            ]);

            $dispatcher = new NullEventDispatcher;
            $stateMachine = new FiscalPeriodStateMachine($period, $dispatcher);

            $stateMachine->startClosing();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Closing);
            expect($dispatcher->hasDispatched(FiscalPeriodClosing::class))->toBeTrue();
        });

        it('can transition from Closing to Closed', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Closing,
                'is_closed' => false,
                'is_locked' => true,
            ]);

            $dispatcher = new NullEventDispatcher;
            $stateMachine = new FiscalPeriodStateMachine($period, $dispatcher);

            $stateMachine->markClosed(['net_income' => 1000000]);

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Closed);
            expect($period->is_closed)->toBeTrue();
            expect($period->closed_at)->not->toBeNull();
            expect($dispatcher->hasDispatched(FiscalPeriodClosed::class))->toBeTrue();
        });

        it('can transition from Locked back to Open (unlock)', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Locked,
                'is_closed' => false,
                'is_locked' => true,
            ]);

            $dispatcher = new NullEventDispatcher;
            $stateMachine = new FiscalPeriodStateMachine($period, $dispatcher);

            $stateMachine->unlock();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Open);
            expect($period->is_locked)->toBeFalse();
        });

        it('can reopen a closed period', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Closed,
                'is_closed' => true,
                'is_locked' => true,
                'closed_at' => now(),
                'closed_by' => $this->user->id,
            ]);

            $dispatcher = new NullEventDispatcher;
            $stateMachine = new FiscalPeriodStateMachine($period, $dispatcher);

            $stateMachine->reopen();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Open);
            expect($period->is_closed)->toBeFalse();
            expect($period->is_locked)->toBeFalse();
            expect($period->closed_at)->toBeNull();
        });

    });

    describe('Invalid Transitions', function () {

        it('cannot transition from Open directly to Closing', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Open,
                'is_closed' => false,
                'is_locked' => false,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect(fn () => $stateMachine->startClosing())
                ->toThrow(FiscalPeriodException::class);
        });

        it('cannot transition from Open directly to Closed', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Open,
                'is_closed' => false,
                'is_locked' => false,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect(fn () => $stateMachine->markClosed())
                ->toThrow(FiscalPeriodException::class);
        });

        it('cannot transition from Closing back to Open', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Closing,
                'is_closed' => false,
                'is_locked' => true,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            // Closing can only go to Locked or Closed, not Open
            expect($stateMachine->canTransitionTo(FiscalPeriodStatus::Open))->toBeFalse();
        });

    });

    describe('Helper Methods', function () {

        it('reports correct next valid statuses from Open', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Open,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect($stateMachine->getNextValidStatuses())->toBe(['locked']);
        });

        it('reports correct next valid statuses from Locked', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Locked,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect($stateMachine->getNextValidStatuses())->toBe(['open', 'closing']);
        });

        it('reports correct next valid statuses from Closed', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Closed,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect($stateMachine->getNextValidStatuses())->toBe(['open']);
        });

        it('canTransitionTo returns true for same status', function () {
            $period = FiscalPeriod::factory()->create([
                'status' => FiscalPeriodStatus::Open,
            ]);

            $stateMachine = new FiscalPeriodStateMachine($period);

            expect($stateMachine->canTransitionTo(FiscalPeriodStatus::Open))->toBeTrue();
        });

    });

});
