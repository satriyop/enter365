<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Shared\Payment;
use App\Services\Shared\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(PaymentService::class);
    $this->cashAccount = Account::where('code', '1-1000')->first();
});

describe('PaymentService - create', function () {

    it('creates a payment record with journal entry', function () {
        $invoice = createSentInvoice();

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $payment = $this->service->create($data);

        expect($payment)
            ->toBeInstanceOf(Payment::class)
            ->and($payment->payment_number)->toContain('RCV-')
            ->and($payment->amount)->toBe(500000)
            ->and($payment->payable_type)->toBe(Invoice::class)
            ->and($payment->payable_id)->toBe($invoice->id)
            ->and($payment->journal_entry_id)->not->toBeNull()
            ->and($payment->journalEntry)->not->toBeNull();
    });

    it('updates invoice paid_amount when payment created', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $this->service->create($data);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(500000);
    });

    it('transitions invoice to partial status on partial payment', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $this->service->create($data);

        $invoice->refresh();
        expect($invoice->status->value)->toBe('partial');
    });

    it('transitions invoice to paid status on full payment', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $this->service->create($data);

        $invoice->refresh();
        expect($invoice->status->value)->toBe('paid');
    });

    it('creates payment for bill', function () {
        $bill = Bill::factory()->hasItems(1)->create(['status' => 'received']);

        $data = [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ];

        $payment = $this->service->create($data);

        expect($payment->payable_type)->toBe(Bill::class)
            ->and($payment->payable_id)->toBe($bill->id);

        $bill->refresh();
        expect($bill->paid_amount)->toBe(300000);
    });

    it('throws exception when payment exceeds invoice outstanding amount', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1500000, // Exceeds total
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $this->service->create($data);
    })->throws(BusinessRuleException::class);

    it('throws exception when invoice in wrong status', function () {
        $invoice = Invoice::factory()->draft()->create();

        $data = [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ];

        $this->service->create($data);
    })->throws(StateTransitionException::class);

});

describe('PaymentService - createForInvoice', function () {

    it('creates payment for specific invoice', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $data = [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
        ];

        $payment = $this->service->createForInvoice($invoice, $data);

        expect($payment->type)->toBe(Payment::TYPE_RECEIVE)
            ->and($payment->contact_id)->toBe($invoice->contact_id)
            ->and($payment->payable_id)->toBe($invoice->id);
    });

});

describe('PaymentService - createForBill', function () {

    it('creates payment for specific bill', function () {
        $bill = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 500000,
        ]);

        $data = [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ];

        $payment = $this->service->createForBill($bill, $data);

        expect($payment->type)->toBe(Payment::TYPE_SEND)
            ->and($payment->contact_id)->toBe($bill->contact_id)
            ->and($payment->payable_id)->toBe($bill->id);
    });

});

describe('PaymentService - void', function () {

    it('voids a payment and reverses journal entry', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $payment = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $originalJournalId = $payment->journal_entry_id;

        $voided = $this->service->void($payment, 'Test void reason');

        expect($voided->is_voided)->toBeTrue()
            ->and($voided->void_reason)->toBe('Test void reason')
            ->and($voided->voided_at)->not->toBeNull();

        // Verify reversal journal entry created
        $originalEntry = \App\Models\Accounting\JournalEntry::find($originalJournalId);
        expect($originalEntry->is_reversed)->toBeTrue();
    });

    it('decreases invoice paid_amount when payment voided', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $payment = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
        ]);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(500000);

        $this->service->void($payment);

        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(0);
    });

    it('reverts invoice status when payment voided', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        // Full payment
        $payment = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
        ]);

        $invoice->refresh();
        expect($invoice->status->value)->toBe('paid');

        // Void payment
        $this->service->void($payment);

        $invoice->refresh();
        expect($invoice->status->value)->toBe('sent');
    });

    it('throws exception when voiding already voided payment', function () {
        $invoice = createSentInvoice();

        $payment = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->service->void($payment);
        $this->service->void($payment);
    })->throws(BusinessRuleException::class, 'sudah dibatalkan');

});

describe('PaymentService - getForInvoice', function () {

    it('returns all non-voided payments for invoice', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000, 'paid_amount' => 0]);

        $payment1 = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
        ]);

        $payment2 = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ]);

        $payments = $this->service->getForInvoice($invoice);

        expect($payments)->toHaveCount(2)
            ->and($payments->pluck('id')->toArray())->toContain($payment1->id, $payment2->id);
    });

    it('excludes voided payments from results', function () {
        $invoice = createSentInvoice();
        $invoice->update(['total_amount' => 1000000]);

        $payment1 = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
        ]);

        $payment2 = $this->service->createForInvoice($invoice, [
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
        ]);

        $this->service->void($payment2);

        $payments = $this->service->getForInvoice($invoice);

        expect($payments)->toHaveCount(1)
            ->and($payments->first()->id)->toBe($payment1->id);
    });

});

describe('PaymentService - getOutstandingAmount', function () {

    it('returns outstanding amount for invoice', function () {
        $invoice = createSentInvoice();
        $invoice->total_amount = 1000000;
        $invoice->paid_amount = 400000;
        $invoice->saveQuietly();

        $outstanding = $this->service->getOutstandingAmount($invoice);

        expect($outstanding)->toBe(600000);
    });

    it('returns outstanding amount for bill', function () {
        $bill = Bill::factory()->hasItems(1)->create([
            'total_amount' => 800000,
            'paid_amount' => 300000,
        ]);

        $outstanding = $this->service->getOutstandingAmount($bill);

        expect($outstanding)->toBe(500000);
    });

});

describe('PaymentService - canReceivePayment', function () {

    it('returns true for sent invoice', function () {
        $invoice = createSentInvoice();

        expect($this->service->canReceivePayment($invoice))->toBeTrue();
    });

    it('returns true for partial invoice', function () {
        $invoice = Invoice::factory()->create(['status' => 'partial']);

        expect($this->service->canReceivePayment($invoice))->toBeTrue();
    });

    it('returns true for overdue invoice', function () {
        $invoice = Invoice::factory()->create(['status' => 'overdue']);

        expect($this->service->canReceivePayment($invoice))->toBeTrue();
    });

    it('returns false for draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create();

        expect($this->service->canReceivePayment($invoice))->toBeFalse();
    });

    it('returns false for paid invoice', function () {
        $invoice = Invoice::factory()->create(['status' => 'paid']);

        expect($this->service->canReceivePayment($invoice))->toBeFalse();
    });

    it('returns false for cancelled invoice', function () {
        $invoice = Invoice::factory()->create(['status' => 'cancelled']);

        expect($this->service->canReceivePayment($invoice))->toBeFalse();
    });

    it('returns true for received bill', function () {
        $bill = Bill::factory()->hasItems(1)->create(['status' => 'received']);

        expect($this->service->canReceivePayment($bill))->toBeTrue();
    });

    it('returns false for draft bill', function () {
        $bill = Bill::factory()->create(['status' => 'draft']);

        expect($this->service->canReceivePayment($bill))->toBeFalse();
    });

});
