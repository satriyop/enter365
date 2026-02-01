<?php

declare(strict_types=1);

use App\Contracts\Accounting\BankReconciliationServiceInterface;
use App\Enums\BankTransactionStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\BankTransaction;
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
