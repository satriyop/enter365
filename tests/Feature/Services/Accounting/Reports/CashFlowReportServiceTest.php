<?php

declare(strict_types=1);

use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Shared\Payment;
use App\Services\Accounting\Reports\CashFlowReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new CashFlowReportService;
});

describe('generateCashFlow', function () {
    test('returns correct structure with empty data', function () {
        $result = $this->service->generateCashFlow('2024-01-01', '2024-01-31');

        expect($result)->toHaveKeys([
            'period',
            'operating_activities',
            'investing_activities',
            'financing_activities',
            'net_cash_change',
            'opening_balance',
            'closing_balance',
        ]);

        expect($result['period'])->toBe(['start' => '2024-01-01', 'end' => '2024-01-31']);
        expect($result['operating_activities'])->toHaveKeys(['items', 'total']);
        expect($result['investing_activities'])->toHaveKeys(['items', 'total']);
        expect($result['financing_activities'])->toHaveKeys(['items', 'total']);
    });

    test('calculates net_cash_flow correctly', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'is_active' => true,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-15',
            'amount' => 5000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_SEND,
            'payment_date' => '2024-06-20',
            'amount' => 3000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        $result = $this->service->generateCashFlow('2024-06-01', '2024-06-30');

        // Operating: 5M receipts + (-3M) payments = 2M
        expect($result['operating_activities']['total'])->toEqual(2000000);
    });

    test('ending_cash equals beginning_cash plus net_cash_flow', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'is_active' => true,
        ]);

        // Opening balance journal entry before the period
        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => '2024-05-31',
            'description' => 'Opening Balance',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-15',
            'amount' => 4000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        $result = $this->service->generateCashFlow('2024-06-01', '2024-06-30');

        expect($result['closing_balance'])->toBe($result['opening_balance'] + $result['net_cash_change']);
    });
});

describe('getCashBalance', function () {
    test('returns zero when no entries exist', function () {
        $balance = $this->service->getCashBalance(new DateTime('2024-06-30'));

        expect($balance)->toBe(0);
    });

    test('sums debit minus credit for cash accounts', function () {
        $cashAccount1 = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'is_active' => true,
        ]);

        $cashAccount2 = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1002',
            'name' => 'Bank BCA',
            'is_active' => true,
        ]);

        $nonCashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-2001',
            'name' => 'Inventory',
            'is_active' => true,
        ]);

        $journalEntry = JournalEntry::factory()->create([
            'entry_date' => '2024-06-15',
            'description' => 'Cash transactions',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount1->id,
            'debit' => 2000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount2->id,
            'debit' => 3000000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $cashAccount2->id,
            'debit' => 0,
            'credit' => 500000,
        ]);

        // Non-cash account (should be ignored)
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $nonCashAccount->id,
            'debit' => 5000000,
            'credit' => 0,
        ]);

        $balance = $this->service->getCashBalance(new DateTime('2024-06-30'));

        expect($balance)->toBe(4500000); // 2M + 3M - 500K
    });
});

describe('getDailyCashMovement', function () {
    test('returns daily rows within date range', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'is_active' => true,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-15',
            'amount' => 2000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_SEND,
            'payment_date' => '2024-06-20',
            'amount' => 1000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        // Outside range
        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-07-01',
            'amount' => 5000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        $result = $this->service->getDailyCashMovement('2024-06-01', '2024-06-30');

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class);

        // 30 days in June
        expect($result)->toHaveCount(30);

        $june15 = $result->firstWhere('date', '2024-06-15');
        expect($june15)->not->toBeNull();
        expect($june15['receipts'])->toEqual(2000000);
        expect($june15['payments'])->toEqual(0);
        expect($june15['net'])->toEqual(2000000);

        $june20 = $result->firstWhere('date', '2024-06-20');
        expect($june20)->not->toBeNull();
        expect($june20['receipts'])->toEqual(0);
        expect($june20['payments'])->toEqual(1000000);
        expect($june20['net'])->toEqual(-1000000);

        // July transaction should not appear
        $july01 = $result->firstWhere('date', '2024-07-01');
        expect($july01)->toBeNull();
    });

    test('tracks running balance correctly', function () {
        $cashAccount = Account::factory()->create([
            'type' => Account::TYPE_ASSET,
            'code' => '1-1001',
            'name' => 'Kas Kecil',
            'is_active' => true,
        ]);

        // Opening balance entry before period
        $openingEntry = JournalEntry::factory()->create([
            'entry_date' => '2024-05-31',
            'description' => 'Opening Balance',
            'is_posted' => true,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $openingEntry->id,
            'account_id' => $cashAccount->id,
            'debit' => 1000000,
            'credit' => 0,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-10',
            'amount' => 3000000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_SEND,
            'payment_date' => '2024-06-15',
            'amount' => 1500000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        Payment::factory()->create([
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-20',
            'amount' => 500000,
            'is_voided' => false,
            'cash_account_id' => $cashAccount->id,
        ]);

        $result = $this->service->getDailyCashMovement('2024-06-01', '2024-06-30');

        $day1 = $result->firstWhere('date', '2024-06-10');
        expect($day1['balance'])->toEqual(4000000); // 1M + 3M

        $day2 = $result->firstWhere('date', '2024-06-15');
        expect($day2['balance'])->toEqual(2500000); // 4M - 1.5M

        $day3 = $result->firstWhere('date', '2024-06-20');
        expect($day3['balance'])->toEqual(3000000); // 2.5M + 500K
    });
});
