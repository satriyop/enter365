<?php

declare(strict_types=1);

use App\Contracts\Accounting\BankReconciliationServiceInterface;
use App\Enums\BankTransactionStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankReconciliationSession;
use App\Models\Accounting\BankTransaction;
use App\Models\Shared\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(BankReconciliationServiceInterface::class);

    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->asset()->create(['code' => '1110']);
});

describe('create', function () {
    it('creates bank transaction with unmatched status', function () {
        $transaction = $this->service->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Transfer dari PT ABC',
            'debit' => 5000000,
            'credit' => 0,
        ]);

        expect($transaction)
            ->toBeInstanceOf(BankTransaction::class)
            ->status->toBe(BankTransactionStatus::Unmatched)
            ->debit->toBe(5000000)
            ->credit->toBe(0)
            ->created_by->toBe($this->user->id);
    });

    it('creates credit transaction', function () {
        $transaction = $this->service->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Pembayaran vendor',
            'debit' => 0,
            'credit' => 3000000,
        ]);

        expect($transaction->credit)->toBe(3000000)
            ->and($transaction->debit)->toBe(0);
    });

    it('creates transaction with optional reference and import batch', function () {
        $transaction = $this->service->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Transfer masuk',
            'reference' => 'TRX-12345678',
            'debit' => 1000000,
            'credit' => 0,
            'import_batch' => 'BATCH-001',
        ]);

        expect($transaction->reference)->toBe('TRX-12345678')
            ->and($transaction->import_batch)->toBe('BATCH-001');
    });

    it('persists transaction to database', function () {
        $transaction = $this->service->create([
            'account_id' => $this->bankAccount->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Test persist',
            'debit' => 2000000,
            'credit' => 0,
        ]);

        $found = BankTransaction::find($transaction->id);

        expect($found)->not->toBeNull()
            ->and($found->description)->toBe('Test persist');
    });
});

describe('delete', function () {
    it('deletes unmatched transaction', function () {
        $transaction = BankTransaction::factory()
            ->unmatched()
            ->forAccount($this->bankAccount)
            ->create(['created_by' => $this->user->id]);

        $this->service->delete($transaction);

        expect(BankTransaction::find($transaction->id))->toBeNull();
    });

    it('throws exception when deleting reconciled transaction', function () {
        $transaction = BankTransaction::factory()
            ->reconciled()
            ->forAccount($this->bankAccount)
            ->create(['created_by' => $this->user->id]);

        expect(fn () => $this->service->delete($transaction))
            ->toThrow(\Exception::class, 'Tidak dapat menghapus transaksi yang sudah direkonsiliasi.');
    });

    it('allows deleting matched (non-reconciled) transaction', function () {
        $transaction = BankTransaction::factory()
            ->matched()
            ->forAccount($this->bankAccount)
            ->create(['created_by' => $this->user->id]);

        $this->service->delete($transaction);

        expect(BankTransaction::find($transaction->id))->toBeNull();
    });
});

describe('matching', function () {

    it('match to payment updates status and matched_payment_id', function () {
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->debit(1000000)->create();
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 1000000,
        ]);

        $this->service->matchToPayment($txn, $payment);

        $txn->refresh();
        expect($txn->status)->toBe(BankTransactionStatus::Matched)
            ->and($txn->matched_payment_id)->toBe($payment->id)
            ->and($txn->match_rule)->toBe('manual')
            ->and($txn->match_confidence)->toBe(100);
    });

    it('match to JE line updates status and matched_journal_line_id', function () {
        $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

        $journalService = app(\App\Services\Accounting\JournalService::class);
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = $journalService->createEntry([
            'entry_date' => now()->toDateString(),
            'description' => 'Test JE',
            'lines' => [
                ['account_id' => $this->bankAccount->id, 'debit' => 500000, 'credit' => 0],
                ['account_id' => $revenueAccount->id, 'debit' => 0, 'credit' => 500000],
            ],
        ], autoPost: true);

        $jeLine = $entry->lines->firstWhere('account_id', $this->bankAccount->id);
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->debit(500000)->create();

        $this->service->matchToJournalLine($txn, $jeLine);

        $txn->refresh();
        expect($txn->status)->toBe(BankTransactionStatus::Matched)
            ->and($txn->matched_journal_line_id)->toBe($jeLine->id)
            ->and($txn->matched_payment_id)->toBeNull();
    });

    it('unmatch restores unmatched status', function () {
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->debit(1000000)->create();
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 1000000,
        ]);

        $this->service->matchToPayment($txn, $payment);
        $this->service->unmatch($txn);
        $txn->refresh();

        expect($txn->status)->toBe(BankTransactionStatus::Unmatched)
            ->and($txn->matched_payment_id)->toBeNull()
            ->and($txn->match_confidence)->toBeNull()
            ->and($txn->match_rule)->toBeNull();
    });

    it('cannot match already-reconciled transaction', function () {
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->reconciled()->create();
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
        ]);

        expect(fn () => $this->service->matchToPayment($txn, $payment))
            ->toThrow(\App\Exceptions\Domain\BusinessRuleException::class);
    });
});

describe('reconciliation', function () {

    it('reconcile sets reconciled status and timestamp', function () {
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->debit(1000000)->create();
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 1000000,
        ]);

        $this->service->matchToPayment($txn, $payment);
        $this->service->reconcile($txn);

        $txn->refresh();
        expect($txn->status)->toBe(BankTransactionStatus::Reconciled)
            ->and($txn->reconciled_at)->not->toBeNull()
            ->and($txn->reconciled_by)->not->toBeNull();
    });

    it('cannot reconcile unmatched transaction', function () {
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->unmatched()->create();

        expect(fn () => $this->service->reconcile($txn))
            ->toThrow(\App\Exceptions\Domain\BusinessRuleException::class);
    });

    it('batch reconcile multiple matched transactions', function () {
        $payment1 = Payment::factory()->receive()->create(['cash_account_id' => $this->bankAccount->id, 'amount' => 1000000]);
        $payment2 = Payment::factory()->receive()->create(['cash_account_id' => $this->bankAccount->id, 'amount' => 2000000]);

        $txn1 = BankTransaction::factory()->forAccount($this->bankAccount)->debit(1000000)->create();
        $txn2 = BankTransaction::factory()->forAccount($this->bankAccount)->debit(2000000)->create();
        $txn3 = BankTransaction::factory()->forAccount($this->bankAccount)->unmatched()->create();

        $this->service->matchToPayment($txn1, $payment1);
        $this->service->matchToPayment($txn2, $payment2);

        $count = $this->service->batchReconcile([$txn1->id, $txn2->id, $txn3->id]);

        expect($count)->toBe(2)
            ->and($txn1->refresh()->status)->toBe(BankTransactionStatus::Reconciled)
            ->and($txn2->refresh()->status)->toBe(BankTransactionStatus::Reconciled)
            ->and($txn3->refresh()->status)->toBe(BankTransactionStatus::Unmatched);
    });
});

describe('auto-suggestion', function () {

    it('auto-suggest finds exact amount match', function () {
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 3000000,
        ]);

        BankTransaction::factory()->forAccount($this->bankAccount)->debit(3000000)->create();

        $suggestions = $this->service->suggestMatches($this->bankAccount);

        expect($suggestions)->toHaveCount(1)
            ->and($suggestions->first()->match_type)->toBe('payment')
            ->and($suggestions->first()->match_id)->toBe($payment->id)
            ->and($suggestions->first()->confidence)->toBe(90)
            ->and($suggestions->first()->rule)->toBe('exact_amount');
    });

    it('auto-suggest ranks reference match higher', function () {
        Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 5000000,
            'payment_number' => 'RCV-SPECIAL-001',
        ]);

        BankTransaction::factory()->forAccount($this->bankAccount)->debit(5000000)->create([
            'reference' => 'RCV-SPECIAL-001',
        ]);

        $suggestions = $this->service->suggestMatches($this->bankAccount);

        expect($suggestions)->toHaveCount(1)
            ->and($suggestions->first()->confidence)->toBe(99)
            ->and($suggestions->first()->rule)->toBe('reference');
    });

    it('auto-suggest returns empty for no matches', function () {
        BankTransaction::factory()->forAccount($this->bankAccount)->debit(9999999)->create();

        $suggestions = $this->service->suggestMatches($this->bankAccount);

        expect($suggestions)->toBeEmpty();
    });
});

describe('session management', function () {

    it('session lifecycle: start, reconcile items, complete', function () {
        $session = $this->service->startSession($this->bankAccount, now()->toDateString(), 50000000);

        expect($session)->toBeInstanceOf(BankReconciliationSession::class)
            ->and($session->status)->toBe(BankReconciliationSession::STATUS_IN_PROGRESS)
            ->and($session->account_id)->toBe($this->bankAccount->id)
            ->and($session->statement_balance)->toBe(50000000);

        // Create and match a txn within the session
        $txn = BankTransaction::factory()->forAccount($this->bankAccount)->debit(1000000)->create([
            'session_id' => $session->id,
        ]);
        $payment = Payment::factory()->receive()->create([
            'cash_account_id' => $this->bankAccount->id,
            'amount' => 1000000,
        ]);

        $this->service->matchToPayment($txn, $payment);
        $this->service->reconcile($txn);

        $completed = $this->service->completeSession($session);

        expect($completed->status)->toBe(BankReconciliationSession::STATUS_COMPLETED)
            ->and($completed->reconciled_count)->toBe(1)
            ->and($completed->completed_at)->not->toBeNull();
    });

    it('cannot complete already completed session', function () {
        $session = $this->service->startSession($this->bankAccount, now()->toDateString(), 50000000);
        $this->service->completeSession($session);

        expect(fn () => $this->service->completeSession($session->refresh()))
            ->toThrow(\App\Exceptions\Domain\BusinessRuleException::class);
    });
});
