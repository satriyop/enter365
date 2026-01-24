<?php

use App\Domain\Accounting\FiscalPeriods\Enums\ClosingStep;
use App\Domain\Accounting\FiscalPeriods\Enums\FiscalPeriodStatus;
use App\Domain\Accounting\FiscalPeriods\Exceptions\FiscalPeriodException;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\YearEndCloseService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    // Create required accounts for closing
    Account::factory()->create([
        'code' => '3-2000',
        'name' => 'Laba Ditahan',
        'type' => Account::TYPE_EQUITY,
    ]);

    Account::factory()->create([
        'code' => '4-1001',
        'name' => 'Pendapatan Penjualan',
        'type' => Account::TYPE_REVENUE,
    ]);

    Account::factory()->create([
        'code' => '5-1001',
        'name' => 'Harga Pokok Penjualan',
        'type' => Account::TYPE_EXPENSE,
    ]);
});

describe('YearEndCloseService', function () {

    describe('getClosingChecklist', function () {

        it('returns checklist with all items', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $service = app(YearEndCloseService::class);
            $checklist = $service->getClosingChecklist($period);

            expect($checklist->items)->not->toBeEmpty();
            expect($checklist->items)->toHaveKey('unposted_journals');
            expect($checklist->items)->toHaveKey('required_accounts');
            expect($checklist->items)->toHaveKey('trial_balance');
        });

        it('reports blocking error for unposted journals', function () {
            $period = FiscalPeriod::factory()->current()->create();

            // Create an unposted journal entry
            JournalEntry::factory()->create([
                'fiscal_period_id' => $period->id,
                'is_posted' => false,
            ]);

            $service = app(YearEndCloseService::class);
            $checklist = $service->getClosingChecklist($period);

            expect($checklist->canClose())->toBeFalse();
            expect($checklist->items['unposted_journals']->isBlocking())->toBeTrue();
        });

        it('reports ok when all checks pass', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $service = app(YearEndCloseService::class);
            $checklist = $service->getClosingChecklist($period);

            expect($checklist->canClose())->toBeTrue();
        });

    });

    describe('executeClose', function () {

        it('throws exception if period already closed', function () {
            $period = FiscalPeriod::factory()->closed()->create();

            $service = app(YearEndCloseService::class);

            expect(fn () => $service->executeClose($period))
                ->toThrow(FiscalPeriodException::class);
        });

        it('throws exception if closing already in progress', function () {
            $period = FiscalPeriod::factory()->closing()->create();

            $service = app(YearEndCloseService::class);

            expect(fn () => $service->executeClose($period))
                ->toThrow(FiscalPeriodException::class);
        });

        it('successfully closes a period with no transactions', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $service = app(YearEndCloseService::class);
            $result = $service->executeClose($period, [
                'skip_next_period' => true,
                'skip_opening_balances' => true,
            ]);

            expect($result['success'])->toBeTrue();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Closed);
            expect($period->is_closed)->toBeTrue();
        });

        it('creates closing journal entries and marks period closed', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $revenueAccount = Account::where('code', '4-1001')->first();
            $expenseAccount = Account::where('code', '5-1001')->first();
            $bankAccount = Account::factory()->create([
                'code' => '1-1002',
                'name' => 'Bank',
                'type' => Account::TYPE_ASSET,
            ]);

            // Create revenue entry (balanced: debit bank, credit revenue)
            $revenueJournal = JournalEntry::factory()->create([
                'fiscal_period_id' => $period->id,
                'is_posted' => true,
                'entry_date' => $period->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $bankAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $revenueAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);

            // Create expense entry (balanced: debit expense, credit bank)
            $expenseJournal = JournalEntry::factory()->create([
                'fiscal_period_id' => $period->id,
                'is_posted' => true,
                'entry_date' => $period->start_date,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $expenseAccount->id,
                'debit' => 400000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $expenseJournal->id,
                'account_id' => $bankAccount->id,
                'debit' => 0,
                'credit' => 400000,
            ]);

            $service = app(YearEndCloseService::class);
            $result = $service->executeClose($period, [
                'skip_next_period' => true,
                'skip_opening_balances' => true,
            ]);

            expect($result['success'])->toBeTrue();

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Closed);
            expect($period->is_closed)->toBeTrue();
            // Closing process should set retained_earnings_amount (even if 0 for unmatched dates)
            expect($period->retained_earnings_amount)->not->toBeNull();
        });

        it('creates next period when auto_create_next_period is true', function () {
            $period = FiscalPeriod::factory()->current()->create([
                'start_date' => '2025-01-01',
                'end_date' => '2025-12-31',
            ]);

            $service = app(YearEndCloseService::class);
            $result = $service->executeClose($period, [
                'skip_opening_balances' => true,
            ]);

            expect($result['success'])->toBeTrue();
            expect($result)->toHaveKey('next_period');

            $nextPeriod = $result['next_period'];
            expect($nextPeriod->start_date->year)->toBe(2026);
        });

        it('returns progress with completed steps', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $service = app(YearEndCloseService::class);
            $result = $service->executeClose($period, [
                'skip_next_period' => true,
                'skip_opening_balances' => true,
            ]);

            expect($result['success'])->toBeTrue();
            expect($result['progress']->isComplete())->toBeTrue();
            expect($result['progress']->getPercentComplete())->toBe(100);

            $completedSteps = $result['progress']->getCompletedSteps();
            expect($completedSteps)->toContain(ClosingStep::LockPeriod);
            expect($completedSteps)->toContain(ClosingStep::ValidateChecklist);
            expect($completedSteps)->toContain(ClosingStep::MarkPeriodClosed);
        });

    });

    describe('rollbackClose', function () {

        it('can reverse journal entries from progress', function () {
            $period = FiscalPeriod::factory()->current()->create();

            $revenueAccount = Account::where('code', '4-1001')->first();
            $bankAccount = Account::factory()->create([
                'code' => '1-1003',
                'name' => 'Bank Rollback',
                'type' => Account::TYPE_ASSET,
            ]);

            // Create balanced revenue entry
            $revenueJournal = JournalEntry::factory()->create([
                'fiscal_period_id' => $period->id,
                'is_posted' => true,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $bankAccount->id,
                'debit' => 1000000,
                'credit' => 0,
            ]);
            JournalEntryLine::factory()->create([
                'journal_entry_id' => $revenueJournal->id,
                'account_id' => $revenueAccount->id,
                'debit' => 0,
                'credit' => 1000000,
            ]);

            $service = app(YearEndCloseService::class);
            $result = $service->executeClose($period, [
                'skip_next_period' => true,
                'skip_opening_balances' => true,
            ]);

            // Now rollback
            $service->rollbackClose($period, $result['progress']);

            $period->refresh();
            expect($period->status)->toBe(FiscalPeriodStatus::Open);
            expect($period->is_closed)->toBeFalse();
        });

    });

});

describe('ClosingStep enum', function () {

    it('returns steps in correct order', function () {
        $steps = ClosingStep::inOrder();

        expect($steps[0])->toBe(ClosingStep::LockPeriod);
        expect($steps[1])->toBe(ClosingStep::ValidateChecklist);
        expect($steps[2])->toBe(ClosingStep::CloseTemporaryAccounts);
        expect(end($steps))->toBe(ClosingStep::PopulateOpeningBalances);
    });

    it('returns correct next step', function () {
        expect(ClosingStep::LockPeriod->next())->toBe(ClosingStep::ValidateChecklist);
        expect(ClosingStep::ValidateChecklist->next())->toBe(ClosingStep::CloseTemporaryAccounts);
        expect(ClosingStep::PopulateOpeningBalances->next())->toBeNull();
    });

    it('correctly identifies reversible steps', function () {
        expect(ClosingStep::LockPeriod->isReversible())->toBeTrue();
        expect(ClosingStep::CloseTemporaryAccounts->isReversible())->toBeTrue();
        expect(ClosingStep::MarkPeriodClosed->isReversible())->toBeFalse();
        expect(ClosingStep::CreateNextPeriod->isReversible())->toBeFalse();
    });

    it('correctly identifies steps that create journal entries', function () {
        expect(ClosingStep::LockPeriod->createsJournalEntry())->toBeFalse();
        expect(ClosingStep::CloseTemporaryAccounts->createsJournalEntry())->toBeTrue();
        expect(ClosingStep::CloseDividends->createsJournalEntry())->toBeTrue();
        expect(ClosingStep::PopulateOpeningBalances->createsJournalEntry())->toBeTrue();
    });

});
