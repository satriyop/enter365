<?php

declare(strict_types=1);

use App\Contracts\Sales\WriteOffServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Sales\Invoice;
use App\Models\Sales\WriteOff;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(WriteOffServiceInterface::class);
    $this->badDebtAccount = Account::where('code', '5-3003')->first();
    $this->arAccount = Account::where('code', '1-1100')->first();
});

describe('WriteOffService', function () {

    it('full write-off creates correct JE (Dr 5-3003 / Cr 1-1100)', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $writeOff = $this->service->writeOff($invoice, [
            'amount' => 1000000,
            'reason' => 'Piutang tidak tertagih',
        ]);

        expect($writeOff)->toBeInstanceOf(WriteOff::class)
            ->and($writeOff->amount)->toBe(1000000)
            ->and($writeOff->base_amount)->toBe(1000000)
            ->and($writeOff->journal_entry_id)->not->toBeNull();

        // Verify JE lines
        $je = $writeOff->journalEntry;
        expect($je->is_posted)->toBeTrue()
            ->and($je->lines)->toHaveCount(2);

        $debitLine = $je->lines->firstWhere('debit', '>', 0);
        $creditLine = $je->lines->firstWhere('credit', '>', 0);

        expect($debitLine->account_id)->toBe($this->badDebtAccount->id)
            ->and($debitLine->debit)->toBe(1000000)
            ->and($creditLine->account_id)->toBe($this->arAccount->id)
            ->and($creditLine->credit)->toBe(1000000);
    });

    it('full write-off transitions invoice to Paid', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 500000,
            'paid_amount' => 0,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $this->service->writeOff($invoice, [
            'amount' => 500000,
            'reason' => 'Piutang macet',
        ]);

        $invoice->refresh();

        expect($invoice->paid_amount)->toBe(500000)
            ->and($invoice->status)->toBe(DocumentStatus::Paid);
    });

    it('partial write-off keeps invoice in Partial', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $this->service->writeOff($invoice, [
            'amount' => 600000,
            'reason' => 'Partial write-off',
        ]);

        $invoice->refresh();

        expect($invoice->paid_amount)->toBe(600000)
            ->and($invoice->status)->toBe(DocumentStatus::Partial);
    });

    it('cannot write off more than outstanding amount', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 700000,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        expect(fn () => $this->service->writeOff($invoice, [
            'amount' => 500000,
            'reason' => 'Exceeds outstanding',
        ]))->toThrow(BusinessRuleException::class);
    });

    it('cannot write off a Draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create([
            'total_amount' => 1000000,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        expect(fn () => $this->service->writeOff($invoice, [
            'amount' => 1000000,
            'reason' => 'Draft invoice',
        ]))->toThrow(BusinessRuleException::class);
    });

    it('cannot write off a Cancelled invoice', function () {
        $invoice = Invoice::factory()->cancelled()->create([
            'total_amount' => 1000000,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        expect(fn () => $this->service->writeOff($invoice, [
            'amount' => 1000000,
            'reason' => 'Cancelled invoice',
        ]))->toThrow(BusinessRuleException::class);
    });

    it('reverse write-off restores invoice status and paid_amount', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $writeOff = $this->service->writeOff($invoice, [
            'amount' => 1000000,
            'reason' => 'Piutang macet',
        ]);

        $invoice->refresh();
        expect($invoice->status)->toBe(DocumentStatus::Paid)
            ->and($invoice->paid_amount)->toBe(1000000);

        // Reverse the write-off
        $this->service->reverse($writeOff);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(0)
            ->and($invoice->status)->toBe(DocumentStatus::Sent);

        // Write-off should be soft-deleted
        expect(WriteOff::find($writeOff->id))->toBeNull();
        expect(WriteOff::withTrashed()->find($writeOff->id))->not->toBeNull();
    });

    it('FX invoice write-off uses base currency in JE', function () {
        $invoice = Invoice::factory()->sent()->create([
            'total_amount' => 1000, // 1000 USD
            'paid_amount' => 0,
            'currency' => 'USD',
            'exchange_rate' => 15500.0000,
            'base_currency_total' => 15500000,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $writeOff = $this->service->writeOff($invoice, [
            'amount' => 1000,
            'reason' => 'FX write-off',
        ]);

        expect($writeOff->amount)->toBe(1000)
            ->and($writeOff->base_amount)->toBe(15500000);

        // JE should be in base currency (IDR)
        $je = $writeOff->journalEntry;
        $debitLine = $je->lines->firstWhere('debit', '>', 0);
        $creditLine = $je->lines->firstWhere('credit', '>', 0);

        expect($debitLine->debit)->toBe(15500000)
            ->and($creditLine->credit)->toBe(15500000)
            ->and($debitLine->currency_code)->toBe('USD')
            ->and($debitLine->amount_currency)->toBe(1000);
    });

    it('can write off overdue invoice', function () {
        $invoice = Invoice::factory()->overdue()->create([
            'total_amount' => 2000000,
            'paid_amount' => 0,
            'receivable_account_id' => $this->arAccount->id,
        ]);

        $writeOff = $this->service->writeOff($invoice, [
            'amount' => 2000000,
            'reason' => 'Piutang macet lama',
        ]);

        $invoice->refresh();
        expect($writeOff)->toBeInstanceOf(WriteOff::class)
            ->and($invoice->status)->toBe(DocumentStatus::Paid);
    });
});
