<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\Account;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Shared\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(PaymentService::class);
    $this->cashAccount = Account::where('code', '1-1000')->first();
});

describe('PaymentService concurrency safety', function () {
    it('correctly accumulates paid_amount across sequential invoice payments', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        // First payment
        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(300000);

        // Second payment should see updated paid_amount
        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(500000);
    });

    it('prevents overpayment when paid_amount is correctly tracked', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 100000, 'paid_amount' => 0]);

        // Pay fully
        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(100000)
            ->and($invoice->status)->toBe(DocumentStatus::Paid);

        // Attempt second payment should fail (invoice is now Paid)
        expect(fn () => $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 50000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->fresh()->id,
        ]))->toThrow(StateTransitionException::class);
    });

    it('correctly reverts paid_amount when voiding payments sequentially', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 500000, 'paid_amount' => 0]);

        // Create two payments
        $payment1 = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $payment2 = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 150000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->fresh()->id,
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(350000);

        // Void first payment
        $this->service->void($payment1->fresh());

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(150000);

        // Void second payment
        $this->service->void($payment2->fresh());

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(0);
    });
});
