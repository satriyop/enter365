<?php

declare(strict_types=1);

/**
 * Bank reconciliation workflow lives only on BankReconciliationServiceInterface.
 * Model must not expose match/reconcile mutators (shallow bypass doors).
 */

use App\Contracts\Accounting\BankReconciliationServiceInterface;
use App\Enums\BankTransactionStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
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
    $this->account = Account::where('code', '1-1010')->first()
        ?? Account::factory()->asset()->create(['code' => '1-1010']);
});

it('exposes only query helpers on the model, not workflow mutators', function () {
    $methods = get_class_methods(BankTransaction::class);

    expect($methods)->not->toContain('matchToPayment')
        ->and($methods)->not->toContain('matchToJournalLine')
        ->and($methods)->not->toContain('reconcile')
        ->and($methods)->not->toContain('unmatch')
        ->and($methods)->toContain('isReconciled')
        ->and($methods)->toContain('isMatched')
        ->and($methods)->toContain('isUnmatched');
});

it('enforces unmatched → matched → reconciled through the service seam', function () {
    $txn = BankTransaction::factory()->forAccount($this->account)->unmatched()->debit(1_000_000)->create();
    $payment = Payment::factory()->receive()->create([
        'cash_account_id' => $this->account->id,
        'amount' => 1_000_000,
    ]);

    expect($txn->isUnmatched())->toBeTrue();

    expect(fn () => $this->service->reconcile($txn))
        ->toThrow(BusinessRuleException::class);

    $this->service->matchToPayment($txn, $payment);
    $txn->refresh();
    expect($txn->status)->toBe(BankTransactionStatus::Matched)
        ->and($txn->isMatched())->toBeTrue()
        ->and($txn->matched_payment_id)->toBe($payment->id);

    $this->service->reconcile($txn);
    $txn->refresh();
    expect($txn->status)->toBe(BankTransactionStatus::Reconciled)
        ->and($txn->isReconciled())->toBeTrue()
        ->and($txn->reconciled_at)->not->toBeNull();
});

it('rejects unmatch when still unmatched', function () {
    $txn = BankTransaction::factory()->forAccount($this->account)->unmatched()->create();

    expect(fn () => $this->service->unmatch($txn))
        ->toThrow(BusinessRuleException::class);
});

it('API reconcile requires prior match', function () {
    authenticatedAdmin();

    $txn = BankTransaction::factory()->forAccount($this->account)->unmatched()->create();

    $this->postJson("/api/v1/bank-transactions/{$txn->id}/reconcile")
        ->assertUnprocessable();

    $payment = Payment::factory()->receive()->create([
        'cash_account_id' => $this->account->id,
        'amount' => $txn->debit ?: $txn->credit,
    ]);

    $this->postJson("/api/v1/bank-transactions/{$txn->id}/match-payment/{$payment->id}")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'matched');

    $this->postJson("/api/v1/bank-transactions/{$txn->id}/reconcile")
        ->assertOk()
        ->assertJsonPath('data.status.value', 'reconciled');
});
