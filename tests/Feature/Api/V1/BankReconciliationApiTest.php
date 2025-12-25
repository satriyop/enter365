<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Bank Reconciliation API', function () {

    it('can list bank transactions', function () {
        $account = Account::factory()->asset()->create();
        BankTransaction::factory()->forAccount($account)->count(5)->create();

        $response = $this->getJson('/api/v1/bank-transactions');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter transactions by account', function () {
        $account1 = Account::factory()->asset()->create();
        $account2 = Account::factory()->asset()->create();

        BankTransaction::factory()->forAccount($account1)->count(3)->create();
        BankTransaction::factory()->forAccount($account2)->count(2)->create();

        $response = $this->getJson("/api/v1/bank-transactions?account_id={$account1->id}");

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter transactions by status', function () {
        $account = Account::factory()->asset()->create();

        BankTransaction::factory()->forAccount($account)->unmatched()->count(3)->create();
        BankTransaction::factory()->forAccount($account)->reconciled()->count(2)->create();

        $response = $this->getJson('/api/v1/bank-transactions?status=unmatched');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create bank transaction', function () {
        $account = Account::factory()->asset()->create();

        $response = $this->postJson('/api/v1/bank-transactions', [
            'account_id' => $account->id,
            'transaction_date' => '2024-12-25',
            'description' => 'Bank transfer in',
            'debit' => 1000000,
            'credit' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'unmatched')
            ->assertJsonPath('data.debit', 1000000);
    });

    it('validates required fields when creating transaction', function () {
        $response = $this->postJson('/api/v1/bank-transactions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'transaction_date', 'description']);
    });

    it('can show a bank transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->create();

        $response = $this->getJson("/api/v1/bank-transactions/{$transaction->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $transaction->id);
    });

    it('can delete unmatched transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->unmatched()->create();

        $response = $this->deleteJson("/api/v1/bank-transactions/{$transaction->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('bank_transactions', ['id' => $transaction->id]);
    });

    it('cannot delete reconciled transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->reconciled()->create();

        $response = $this->deleteJson("/api/v1/bank-transactions/{$transaction->id}");

        $response->assertUnprocessable();
    });

    it('can reconcile a transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->unmatched()->create();

        $response = $this->postJson("/api/v1/bank-transactions/{$transaction->id}/reconcile");

        $response->assertOk()
            ->assertJsonPath('data.status', 'reconciled')
            ->assertJsonPath('data.is_reconciled', true);
    });

    it('cannot reconcile already reconciled transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->reconciled()->create();

        $response = $this->postJson("/api/v1/bank-transactions/{$transaction->id}/reconcile");

        $response->assertUnprocessable();
    });

    it('can bulk reconcile transactions', function () {
        $account = Account::factory()->asset()->create();
        $transactions = BankTransaction::factory()->forAccount($account)->unmatched()->count(3)->create();

        $response = $this->postJson('/api/v1/bank-transactions/bulk-reconcile', [
            'transaction_ids' => $transactions->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertJsonPath('reconciled_count', 3);

        foreach ($transactions as $transaction) {
            $this->assertDatabaseHas('bank_transactions', [
                'id' => $transaction->id,
                'status' => 'reconciled',
            ]);
        }
    });

    it('can get reconciliation summary', function () {
        $account = Account::factory()->asset()->create();

        BankTransaction::factory()->forAccount($account)->unmatched()->count(5)->create();
        BankTransaction::factory()->forAccount($account)->matched()->count(3)->create();
        BankTransaction::factory()->forAccount($account)->reconciled()->count(2)->create();

        $response = $this->getJson("/api/v1/bank-transactions/summary?account_id={$account->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'total_transactions',
                'unmatched',
                'matched',
                'reconciled',
                'total_debits',
                'total_credits',
                'account',
            ])
            ->assertJsonPath('total_transactions', 10)
            ->assertJsonPath('unmatched', 5)
            ->assertJsonPath('matched', 3)
            ->assertJsonPath('reconciled', 2);
    });

    it('can unmatch a matched transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->matched()->create();

        $response = $this->postJson("/api/v1/bank-transactions/{$transaction->id}/unmatch");

        $response->assertOk()
            ->assertJsonPath('data.status', 'unmatched');
    });

    it('cannot unmatch reconciled transaction', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->reconciled()->create();

        $response = $this->postJson("/api/v1/bank-transactions/{$transaction->id}/unmatch");

        $response->assertUnprocessable();
    });

    it('can get match suggestions', function () {
        $account = Account::factory()->asset()->create();
        $transaction = BankTransaction::factory()->forAccount($account)->unmatched()->debit(1000000)->create();

        $response = $this->getJson("/api/v1/bank-transactions/{$transaction->id}/suggest-matches");

        $response->assertOk()
            ->assertJsonStructure([
                'transaction',
                'suggestions',
            ]);
    });
});
