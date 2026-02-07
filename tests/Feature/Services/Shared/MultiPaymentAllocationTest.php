<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Accounting\Account;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
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

describe('single allocation (backward compat)', function () {

    it('creates allocation record for legacy invoice_id payment', function () {
        $invoice = createSentInvoice();

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        // Should create allocation record
        expect($payment->allocations)->toHaveCount(1);

        $alloc = $payment->allocations->first();
        expect($alloc->allocatable_type)->toBe('invoice')
            ->and($alloc->allocatable_id)->toBe($invoice->id)
            ->and($alloc->amount)->toBe(500000);

        // Legacy payable_type/payable_id still set
        expect($payment->payable_type)->toBe(Invoice::class)
            ->and($payment->payable_id)->toBe($invoice->id);
    });

    it('creates allocation record for legacy bill_id payment', function () {
        $bill = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 500000,
        ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 300000,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        expect($payment->allocations)->toHaveCount(1);

        $alloc = $payment->allocations->first();
        expect($alloc->allocatable_type)->toBe('bill')
            ->and($alloc->allocatable_id)->toBe($bill->id)
            ->and($alloc->amount)->toBe(300000);
    });

});

describe('multi-allocation across invoices', function () {

    it('allocates one payment across 2 invoices', function () {
        $invoice1 = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 1000000]);

        $invoice2 = Invoice::factory()->sent()->create([
            'total_amount' => 500000,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 500000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1500000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 1000000],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 500000],
            ],
        ]);

        expect($payment->allocations)->toHaveCount(2);
        expect($payment->amount)->toBe(1500000);

        // Both invoices should be fully paid
        $invoice1->refresh();
        $invoice2->refresh();
        expect($invoice1->paid_amount)->toBe(1000000)
            ->and($invoice1->status->value)->toBe('paid')
            ->and($invoice2->paid_amount)->toBe(500000)
            ->and($invoice2->status->value)->toBe('paid');

        // JE should be balanced
        $je = $payment->journalEntry->load('lines');
        expect($je->isBalanced())->toBeTrue();
    });

    it('creates partial payment on first invoice and full on second', function () {
        $invoice1 = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 1000000]);

        $invoice2 = Invoice::factory()->sent()->create([
            'total_amount' => 500000,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 500000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 800000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 300000],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 500000],
            ],
        ]);

        $invoice1->refresh();
        $invoice2->refresh();
        expect($invoice1->paid_amount)->toBe(300000)
            ->and($invoice1->status->value)->toBe('partial')
            ->and($invoice2->paid_amount)->toBe(500000)
            ->and($invoice2->status->value)->toBe('paid');
    });

});

describe('multi-allocation across bills', function () {

    it('allocates one payment across 3 bills', function () {
        $contact = \App\Models\Contacts\Contact::factory()->supplier()->create();

        $bill1 = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 200000,
            'paid_amount' => 0,
            'contact_id' => $contact->id,
        ]);
        $bill2 = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 300000,
            'paid_amount' => 0,
            'contact_id' => $contact->id,
        ]);
        $bill3 = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 500000,
            'paid_amount' => 0,
            'contact_id' => $contact->id,
        ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'bill', 'allocatable_id' => $bill1->id, 'amount' => 200000],
                ['allocatable_type' => 'bill', 'allocatable_id' => $bill2->id, 'amount' => 300000],
                ['allocatable_type' => 'bill', 'allocatable_id' => $bill3->id, 'amount' => 500000],
            ],
        ]);

        expect($payment->allocations)->toHaveCount(3);

        $bill1->refresh();
        $bill2->refresh();
        $bill3->refresh();
        expect($bill1->paid_amount)->toBe(200000)
            ->and($bill1->status->value)->toBe('paid')
            ->and($bill2->paid_amount)->toBe(300000)
            ->and($bill2->status->value)->toBe('paid')
            ->and($bill3->paid_amount)->toBe(500000)
            ->and($bill3->status->value)->toBe('paid');

        $je = $payment->journalEntry->load('lines');
        expect($je->isBalanced())->toBeTrue();
    });

});

describe('validation', function () {

    it('rejects when allocation total exceeds payment amount', function () {
        $invoice1 = Invoice::factory()->sent()->create(['total_amount' => 1000000, 'paid_amount' => 0]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 1000000]);

        $invoice2 = Invoice::factory()->sent()->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 1000000]);

        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000, // total only 500k
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 400000],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 300000], // 700k > 500k
            ],
        ]);
    })->throws(BusinessRuleException::class);

    it('rejects allocation to already-paid invoice', function () {
        $invoice = Invoice::factory()->create([
            'status' => 'paid',
            'total_amount' => 500000,
            'paid_amount' => 500000,
        ]);

        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 100000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice->id, 'amount' => 100000],
            ],
        ]);
    })->throws(\Exception::class);

    it('rejects allocation exceeding individual invoice outstanding', function () {
        $invoice = Invoice::factory()->sent()->create(['total_amount' => 500000, 'paid_amount' => 400000]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 500000]);

        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 200000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice->id, 'amount' => 200000],
            ],
        ]);
    })->throws(BusinessRuleException::class);

});

describe('void multi-allocated payment', function () {

    it('reverses all payables when multi-allocated payment voided', function () {
        $invoice1 = Invoice::factory()->sent()->create(['total_amount' => 600000, 'paid_amount' => 0]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 600000]);

        $invoice2 = Invoice::factory()->sent()->create([
            'total_amount' => 400000,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 400000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 600000],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 400000],
            ],
        ]);

        // Both should be paid
        $invoice1->refresh();
        $invoice2->refresh();
        expect($invoice1->status->value)->toBe('paid')
            ->and($invoice2->status->value)->toBe('paid');

        // Void the payment
        $this->service->void($payment);

        // Both should revert to sent status with 0 paid
        $invoice1->refresh();
        $invoice2->refresh();
        expect($invoice1->paid_amount)->toBe(0)
            ->and($invoice1->status->value)->toBe('sent')
            ->and($invoice2->paid_amount)->toBe(0)
            ->and($invoice2->status->value)->toBe('sent');
    });

});

describe('JE lines for multi-allocation', function () {

    it('creates correct multi-line AR entries in JE', function () {
        $invoice1 = Invoice::factory()->sent()->create(['total_amount' => 300000, 'paid_amount' => 0]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 300000]);

        $invoice2 = Invoice::factory()->sent()->create([
            'total_amount' => 700000,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 700000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 300000],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 700000],
            ],
        ]);

        $je = $payment->journalEntry->load('lines');

        // Should have 3 lines: 1 Cash debit, 2 AR credits
        expect($je->lines)->toHaveCount(3);

        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->debit)->toBe(1000000);

        $arAccount = Account::where('code', '1-1100')->first();
        $arLines = $je->lines->where('account_id', $arAccount->id);
        expect($arLines)->toHaveCount(2);

        $arCredits = $arLines->pluck('credit')->sort()->values();
        expect($arCredits[0])->toBe(300000)
            ->and($arCredits[1])->toBe(700000);

        expect($je->isBalanced())->toBeTrue();
    });

    it('creates correct multi-line AP entries for bill payment', function () {
        $contact = \App\Models\Contacts\Contact::factory()->supplier()->create();

        $bill1 = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 400000,
            'paid_amount' => 0,
            'contact_id' => $contact->id,
        ]);
        $bill2 = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'total_amount' => 600000,
            'paid_amount' => 0,
            'contact_id' => $contact->id,
        ]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $contact->id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'bill', 'allocatable_id' => $bill1->id, 'amount' => 400000],
                ['allocatable_type' => 'bill', 'allocatable_id' => $bill2->id, 'amount' => 600000],
            ],
        ]);

        $je = $payment->journalEntry->load('lines');

        $apAccount = Account::where('code', '2-1100')->first();
        $apLines = $je->lines->where('account_id', $apAccount->id);
        expect($apLines)->toHaveCount(2);

        $apDebits = $apLines->pluck('debit')->sort()->values();
        expect($apDebits[0])->toBe(400000)
            ->and($apDebits[1])->toBe(600000);

        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->credit)->toBe(1000000);

        expect($je->isBalanced())->toBeTrue();
    });

});

describe('FX multi-allocation', function () {

    it('handles FX gain across multi-allocation invoices', function () {
        // Two USD invoices at same invoice rate
        $invoice1 = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice1->id, 'line_total' => 500]);

        $invoice2 = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 500,
            'paid_amount' => 0,
            'contact_id' => $invoice1->contact_id,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'line_total' => 500]);

        // Payment at 15,500 IDR/USD (gain)
        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice1->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'exchange_rate' => 15500,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice1->id, 'amount' => 500],
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice2->id, 'amount' => 500],
            ],
        ]);

        $je = $payment->journalEntry->load('lines');

        // Cash: 1000 * 15500 = 15,500,000
        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->debit)->toBe(15_500_000);

        // AR lines: 500 * 15000 = 7,500,000 each
        $arAccount = Account::where('code', '1-1100')->first();
        $arLines = $je->lines->where('account_id', $arAccount->id);
        expect($arLines)->toHaveCount(2);
        foreach ($arLines as $arLine) {
            expect($arLine->credit)->toBe(7_500_000);
        }

        // FX Gain: (15500-15000) * 500 * 2 = 500,000
        $fxGainAccount = Account::where('code', config('accounting.default_accounts.foreign_exchange_gain'))->first();
        $fxLine = $je->lines->firstWhere('account_id', $fxGainAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->credit)->toBe(500_000);

        expect($je->isBalanced())->toBeTrue();
    });

});

describe('allocation total mismatch', function () {

    it('rejects payment when allocations sum less than total', function () {
        $invoice = Invoice::factory()->sent()->create(['total_amount' => 300000, 'paid_amount' => 0]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 300000]);

        // Payment of 500k but only 300k allocated
        $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500000,
            'payment_date' => now()->toDateString(),
            'allocations' => [
                ['allocatable_type' => 'invoice', 'allocatable_id' => $invoice->id, 'amount' => 300000],
            ],
        ]);
    })->throws(BusinessRuleException::class, 'Total alokasi');

});
