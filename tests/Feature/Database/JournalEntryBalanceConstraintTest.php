<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(JournalService::class);
    $this->cashAccount = Account::where('code', '1-1000')->first();
    $this->revenueAccount = Account::where('code', '4-1001')->first();
});

describe('Balanced JE Constraint', function () {

    it('allows balanced entry to commit', function () {
        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Balanced entry',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ], autoPost: true);

        expect($entry)->toBeInstanceOf(JournalEntry::class)
            ->and($entry->isBalanced())->toBeTrue()
            ->and($entry->is_posted)->toBeTrue();
    });

    it('rejects unbalanced entry at post time', function () {
        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Unbalanced entry',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 300000],
            ],
        ]);

        expect(fn () => $this->service->postEntry($entry))
            ->toThrow(BusinessRuleException::class);
    });

    it('allows lines inserted one-by-one within a transaction', function () {
        // This simulates the createEntry() pattern of inserting lines one-by-one
        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Sequential line inserts',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 100000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 100000],
            ],
        ], autoPost: true);

        $entry->refresh();
        expect($entry->lines)->toHaveCount(2)
            ->and($entry->getTotalDebit())->toBe($entry->getTotalCredit());
    });

    it('detects unbalance when a line is deleted', function () {
        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Entry to unbalance via delete',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 200000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 200000],
            ],
        ]);

        // Delete one line → entry becomes unbalanced
        $entry->lines->first()->delete();
        $entry->refresh();

        expect($entry->isBalanced())->toBeFalse()
            ->and(fn () => $this->service->postEntry($entry))
            ->toThrow(BusinessRuleException::class);
    });

    it('detects unbalance when a line amount is updated', function () {
        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Entry to unbalance via update',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 300000, 'credit' => 0],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 300000],
            ],
        ]);

        // Update debit line to different amount
        $debitLine = $entry->lines->where('debit', '>', 0)->first();
        $debitLine->update(['debit' => 999999]);
        $entry->refresh();

        expect($entry->isBalanced())->toBeFalse()
            ->and(fn () => $this->service->postEntry($entry))
            ->toThrow(BusinessRuleException::class);
    });

    it('allows multi-line balanced entries', function () {
        $expenseAccount = Account::where('code', '5-1002')->first();

        $entry = $this->service->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Multi-line balanced',
            'lines' => [
                ['account_id' => $this->cashAccount->id, 'debit' => 0, 'credit' => 750000],
                ['account_id' => $this->revenueAccount->id, 'debit' => 0, 'credit' => 250000],
                ['account_id' => $expenseAccount->id, 'debit' => 1000000, 'credit' => 0],
            ],
        ], autoPost: true);

        expect($entry->isBalanced())->toBeTrue()
            ->and($entry->lines)->toHaveCount(3);
    });
});
