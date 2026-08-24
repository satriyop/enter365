<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Services\Accounting\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(AccountBalanceService::class);
});

describe('AccountBalanceService - getBalance', function () {

    it('returns zero for account with no posted movements even if opening_balance is set', function () {
        $account = Account::where('code', '1-1001')->first();
        $account->forceFill(['opening_balance' => 500000])->save();

        $balance = $this->service->getBalance($account);

        expect($balance)->toBe(0);
    });

    it('calculates balance for debit-normal account with movements', function () {
        $account = Account::where('code', '1-1001')->first(); // Cash (debit normal)

        // Create posted journal entry
        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 500000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 0,
            'credit' => 200000,
        ]);

        $balance = $this->service->getBalance($account);

        expect($balance)->toBe(300000);
    });

    it('calculates balance for credit-normal account with movements', function () {
        $account = Account::where('code', '4-1001')->first(); // Revenue (credit normal)

        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $account->id,
            'debit' => 0,
            'credit' => 800000,
        ]);

        $balance = $this->service->getBalance($account);

        // For credit normal: Credit - Debit = 800,000 - 100,000 = 700,000
        expect($balance)->toBe(700000);
    });

    it('only includes posted journal entries', function () {
        $account = Account::where('code', '1-1001')->first();

        // Unposted entry (should be ignored)
        $unpostedEntry = JournalEntry::factory()->create(['is_posted' => false]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $unpostedEntry->id,
            'account_id' => $account->id,
            'debit' => 500000,
            'credit' => 0,
        ]);

        $balance = $this->service->getBalance($account);

        expect($balance)->toBe(0);
    });

    it('calculates balance as of specific date', function () {
        $account = Account::where('code', '1-1001')->first();

        // Entry on Jan 15
        $entry1 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-01-15',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry1->id,
            'account_id' => $account->id,
            'debit' => 300000,
            'credit' => 0,
        ]);

        // Entry on Feb 15
        $entry2 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-02-15',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry2->id,
            'account_id' => $account->id,
            'debit' => 200000,
            'credit' => 0,
        ]);

        $balance = $this->service->getBalance($account, '2024-01-31');

        // Should only include Jan entry
        expect($balance)->toBe(300000);
    });

});

describe('AccountBalanceService - getBalances', function () {

    it('returns balances for multiple accounts', function () {
        $cash = Account::where('code', '1-1001')->first();
        $revenue = Account::where('code', '4-1001')->first();

        $accounts = collect([$cash, $revenue]);
        $balances = $this->service->getBalances($accounts);

        expect($balances)->toBeArray()
            ->and($balances[$cash->id])->toBe(0)
            ->and($balances[$revenue->id])->toBe(0);
    });

});

describe('AccountBalanceService - getLedger', function () {

    it('returns ledger entries with running balance', function () {
        $account = Account::where('code', '1-1001')->first();

        // Create entries
        $entry1 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-01-15',
            'entry_number' => 'JE-001',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry1->id,
            'account_id' => $account->id,
            'debit' => 500000,
            'credit' => 0,
            'description' => 'Cash in',
        ]);

        $entry2 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-01-20',
            'entry_number' => 'JE-002',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry2->id,
            'account_id' => $account->id,
            'debit' => 0,
            'credit' => 200000,
            'description' => 'Cash out',
        ]);

        $ledger = $this->service->getLedger($account);

        expect($ledger)->toHaveCount(2);

        $first = $ledger->first();
        expect($first['debit'])->toBe(500000)
            ->and($first['credit'])->toBe(0)
            ->and($first['balance'])->toBe(500000);

        $second = $ledger->get(1);
        expect($second['debit'])->toBe(0)
            ->and($second['credit'])->toBe(200000)
            ->and($second['balance'])->toBe(300000);
    });

    it('filters ledger by date range', function () {
        $account = Account::where('code', '1-1001')->first();

        $entry1 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-01-15',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry1->id,
            'account_id' => $account->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        $entry2 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-02-15',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry2->id,
            'account_id' => $account->id,
            'debit' => 200000,
            'credit' => 0,
        ]);

        $entry3 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-03-15',
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry3->id,
            'account_id' => $account->id,
            'debit' => 300000,
            'credit' => 0,
        ]);

        $ledger = $this->service->getLedger($account, '2024-02-01', '2024-02-28');

        expect($ledger)->toHaveCount(1)
            ->and($ledger->first()['debit'])->toBe(200000);
    });

    it('excludes unposted entries from ledger', function () {
        $account = Account::where('code', '1-1001')->first();

        $postedEntry = JournalEntry::factory()->create(['is_posted' => true]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $postedEntry->id,
            'account_id' => $account->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        $unpostedEntry = JournalEntry::factory()->create(['is_posted' => false]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $unpostedEntry->id,
            'account_id' => $account->id,
            'debit' => 200000,
            'credit' => 0,
        ]);

        $ledger = $this->service->getLedger($account);

        expect($ledger)->toHaveCount(1);
    });

});

describe('AccountBalanceService - getTrialBalance', function () {

    it('returns trial balance for all active accounts', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        // Ensure these accounts are active
        $cashAccount->update(['is_active' => true]);
        $revenueAccount->update(['is_active' => true]);

        // Create some journal entries with explicit date
        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-06-15',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 1000000,
        ]);

        $trialBalance = $this->service->getTrialBalance('2024-06-30');

        // Trial balance should include accounts with movements
        expect($trialBalance->count())->toBeGreaterThan(0);

        $cashRow = $trialBalance->firstWhere('code', '1-1001');
        $revenueRow = $trialBalance->firstWhere('code', '4-1001');

        expect($cashRow)->not->toBeNull()
            ->and($cashRow['code'])->toBe('1-1001')
            ->and($cashRow['debit_balance'])->toBeGreaterThan(0);

        expect($revenueRow)->not->toBeNull()
            ->and($revenueRow['code'])->toBe('4-1001')
            ->and($revenueRow['credit_balance'])->toBeGreaterThan(0);
    });

    it('trial balance debits equal credits', function () {
        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => now()->toDateString(),
        ]);

        $cash = Account::where('code', '1-1001')->first();
        $revenue = Account::where('code', '4-1001')->first();

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 2000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 2000000,
        ]);

        $trialBalance = $this->service->getTrialBalance();

        $totalDebits = $trialBalance->sum('debit_balance');
        $totalCredits = $trialBalance->sum('credit_balance');

        expect($totalDebits)->toBe($totalCredits);
    });

    it('filters trial balance by date', function () {
        $entry1 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-01-15',
        ]);

        $cash = Account::where('code', '1-1001')->first();

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry1->id,
            'account_id' => $cash->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);

        $entry2 = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2024-02-15',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry2->id,
            'account_id' => $cash->id,
            'debit' => 500000,
            'credit' => 0,
        ]);

        $trialBalance = $this->service->getTrialBalance('2024-01-31');

        $cashRow = $trialBalance->firstWhere('code', '1-1001');

        // Should only include Jan entry
        expect($cashRow['debit_balance'])->toBe(1000000);
    });

    it('includes inactive accounts that still hold posted movements', function () {
        $inactiveAccount = Account::factory()->create([
            'code' => '9-9999',
            'name' => 'Inactive Test Account',
            'type' => Account::TYPE_ASSET,
            'is_active' => false,
        ]);

        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $inactiveAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        $trialBalance = $this->service->getTrialBalance();

        $inactiveRow = $trialBalance->firstWhere('code', '9-9999');
        expect($inactiveRow)->not->toBeNull()
            ->and($inactiveRow['is_active'])->toBeFalse()
            ->and($inactiveRow['debit_balance'])->toBe(100000);
    });

    it('does not let a writable opening_balance column unbalance the trial balance', function () {
        $cash = Account::where('code', '1-1001')->first();
        $revenue = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => now()->toDateString(),
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cash->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => 0,
            'credit' => 1000000,
        ]);

        $cash->forceFill(['opening_balance' => 500000000])->save();

        $trialBalance = $this->service->getTrialBalance();

        expect($trialBalance->sum('debit_balance'))->toBe($trialBalance->sum('credit_balance'))
            ->and($trialBalance->firstWhere('code', '1-1001')['debit_balance'])->toBe(1000000);
    });

    it('excludes accounts with zero balance and no activity', function () {
        $account = Account::factory()->create([
            'code' => '9-8888',
            'name' => 'Zero Balance Account',
            'type' => Account::TYPE_ASSET,
            'is_active' => true,
            'opening_balance' => 0,
        ]);

        // No journal entries for this account

        $trialBalance = $this->service->getTrialBalance();

        $zeroRow = $trialBalance->firstWhere('code', '9-8888');
        expect($zeroRow)->toBeNull();
    });

});
