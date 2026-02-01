<?php

declare(strict_types=1);

use App\Enums\BankTransactionStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\Shared\Payment;
use App\Services\Accounting\Reports\BankReconciliationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new BankReconciliationReportService;
    $this->bankAccount = Account::factory()->create([
        'type' => Account::TYPE_ASSET,
        'subtype' => Account::SUBTYPE_CURRENT_ASSET,
        'code' => '1-1002',
        'name' => 'Bank BCA',
        'is_active' => true,
        'opening_balance' => 0,
    ]);
});

describe('getReconciliationReport', function () {
    test('returns correct structure', function () {
        $result = $this->service->getReconciliationReport($this->bankAccount, '2024-06-30');

        expect($result)->toHaveKeys([
            'account',
            'as_of_date',
            'book_balance',
            'bank_balance',
            'adjustments_to_book',
            'adjustments_to_bank',
            'adjusted_book_balance',
            'adjusted_bank_balance',
            'difference',
            'is_reconciled',
            'reconciliation_summary',
        ]);

        expect($result['account'])->toBe([
            'id' => $this->bankAccount->id,
            'code' => '1-1002',
            'name' => 'Bank BCA',
        ]);
        expect($result['as_of_date'])->toBe('2024-06-30');
        expect($result['adjustments_to_book'])->toHaveKeys(['items', 'total']);
        expect($result['adjustments_to_bank'])->toHaveKeys(['items', 'total']);
        expect($result['reconciliation_summary'])->toHaveKeys([
            'total', 'reconciled', 'matched', 'unmatched',
        ]);
    });

    test('is_reconciled when difference is zero', function () {
        // No bank transactions and no book entries → both balances are 0 → reconciled
        $result = $this->service->getReconciliationReport($this->bankAccount, '2024-06-30');

        expect($result['is_reconciled'])->toBeTrue()
            ->and($result['difference'])->toBe(0);
    });

    test('is not reconciled when difference exists', function () {
        // Book shows 3M from a journal entry
        $journalEntry = \App\Models\Accounting\JournalEntry::factory()->create([
            'entry_date' => '2024-06-10',
            'is_posted' => true,
        ]);
        \App\Models\Accounting\JournalEntryLine::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'account_id' => $this->bankAccount->id,
            'debit' => 3000000,
            'credit' => 0,
        ]);

        // Bank shows 5M (unmatched deposit) — adjustments_to_book adds 5M to book
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-15',
            'status' => BankTransactionStatus::Unmatched,
            'debit' => 5000000,
            'credit' => 0,
            'description' => 'Unmatched bank deposit',
            'balance' => 5000000,
        ]);

        $result = $this->service->getReconciliationReport($this->bankAccount, '2024-06-30');

        // adjustedBook = 3M (book) + 5M (unmatched bank txn) = 8M
        // adjustedBank = 5M + 0 = 5M
        // difference = 3M → not reconciled
        expect($result['is_reconciled'])->toBeFalse()
            ->and($result['difference'])->not->toBe(0);
    });
});

describe('getOutstandingItems', function () {
    test('returns correct structure', function () {
        $result = $this->service->getOutstandingItems($this->bankAccount, '2024-06-30');

        expect($result)->toHaveKeys([
            'outstanding_deposits',
            'outstanding_checks',
            'unmatched_bank_transactions',
            'unmatched_book_entries',
        ]);
    });

    test('finds outstanding deposits', function () {
        // Payment not matched to bank transaction
        Payment::factory()->create([
            'cash_account_id' => $this->bankAccount->id,
            'type' => Payment::TYPE_RECEIVE,
            'payment_date' => '2024-06-25',
            'amount' => 2500000,
            'is_voided' => false,
            'payment_number' => 'RCV-001',
            'notes' => 'Customer payment - not yet in bank',
        ]);

        $result = $this->service->getOutstandingItems($this->bankAccount, '2024-06-30');

        expect($result['outstanding_deposits'])->toHaveCount(1);

        $deposit = $result['outstanding_deposits']->first();
        expect($deposit)->toHaveKeys(['id', 'date', 'number', 'description', 'amount'])
            ->and($deposit['amount'])->toEqual(2500000)
            ->and($deposit['number'])->toBe('RCV-001');
    });

    test('finds unmatched bank transactions', function () {
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-20',
            'status' => BankTransactionStatus::Unmatched,
            'debit' => 0,
            'credit' => 750000,
            'description' => 'Bank fee - not in books',
            'reference' => 'BANK-FEE-001',
            'balance' => -750000,
        ]);

        $result = $this->service->getOutstandingItems($this->bankAccount, '2024-06-30');

        expect($result['unmatched_bank_transactions'])->toHaveCount(1);

        $unmatched = $result['unmatched_bank_transactions']->first();
        // Items are arrays from map()
        expect($unmatched)->toHaveKeys(['id', 'date', 'description', 'reference', 'debit', 'credit', 'net_amount'])
            ->and($unmatched['credit'])->toEqual(750000)
            ->and($unmatched['description'])->toBe('Bank fee - not in books')
            ->and($unmatched['net_amount'])->toEqual(-750000); // debit(0) - credit(750K)
    });
});

describe('getReconciliationHistory', function () {
    test('returns reconciled transaction history grouped by date', function () {
        // Two reconciled transactions on June 15
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-15',
            'status' => BankTransactionStatus::Reconciled,
            'debit' => 3000000,
            'credit' => 0,
            'description' => 'Customer payment',
            'balance' => 3000000,
            'reconciled_at' => '2024-06-15 10:00:00',
        ]);

        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-15',
            'status' => BankTransactionStatus::Reconciled,
            'debit' => 2000000,
            'credit' => 0,
            'description' => 'Another payment',
            'balance' => 5000000,
            'reconciled_at' => '2024-06-15 14:00:00',
        ]);

        // Credit transaction on June 20
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-20',
            'status' => BankTransactionStatus::Reconciled,
            'debit' => 0,
            'credit' => 1500000,
            'description' => 'Supplier payment',
            'balance' => 3500000,
            'reconciled_at' => '2024-06-20 09:00:00',
        ]);

        // Unmatched — excluded
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-06-25',
            'status' => BankTransactionStatus::Unmatched,
            'debit' => 1000000,
            'credit' => 0,
            'description' => 'Not reconciled yet',
            'balance' => 4500000,
        ]);

        // Outside date range — excluded
        BankTransaction::factory()->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => '2024-07-05',
            'status' => BankTransactionStatus::Reconciled,
            'debit' => 500000,
            'credit' => 0,
            'description' => 'July transaction',
            'balance' => 5000000,
            'reconciled_at' => '2024-07-05 10:00:00',
        ]);

        $result = $this->service->getReconciliationHistory($this->bankAccount, '2024-06-01', '2024-06-30');

        expect($result)->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->and($result)->toHaveCount(2); // Two distinct dates

        // June 15: 2 debit transactions, SUM(debit) - SUM(credit) = 5M - 0 = 5M
        $june15 = $result->firstWhere('date', '2024-06-15');
        expect($june15)->not->toBeNull()
            ->and($june15['count'])->toBe(2)
            ->and($june15['total_amount'])->toBe(5000000);

        // June 20: 1 credit transaction, SUM(debit) - SUM(credit) = 0 - 1.5M = -1.5M
        $june20 = $result->firstWhere('date', '2024-06-20');
        expect($june20)->not->toBeNull()
            ->and($june20['count'])->toBe(1)
            ->and($june20['total_amount'])->toBe(-1500000);
    });
});
