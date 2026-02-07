<?php

declare(strict_types=1);

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
    $this->fxGainAccount = Account::where('code', config('accounting.default_accounts.foreign_exchange_gain'))->first();
    $this->fxLossAccount = Account::where('code', config('accounting.default_accounts.foreign_exchange_loss'))->first();
});

describe('IS-H4: Realized FX Gain/Loss on Payment', function () {

    it('creates FX gain when payment rate > invoice rate for customer payment', function () {
        // Invoice booked at 15,000 IDR/USD
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000, // $1000 USD
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000]);

        // Payment at 15,500 IDR/USD (rate went up = gain for receiver)
        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'exchange_rate' => 15500,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // Cash: 1000 * 15500 = 15,500,000
        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->debit)->toBe(15_500_000);

        // AR: 1000 * 15000 = 15,000,000
        $arAccount = Account::where('code', '1-1100')->first();
        $arLine = $je->lines->firstWhere('account_id', $arAccount->id);
        expect($arLine->credit)->toBe(15_000_000);

        // FX Gain: 500,000 (credit)
        $fxLine = $je->lines->firstWhere('account_id', $this->fxGainAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->credit)->toBe(500_000)
            ->and($fxLine->debit)->toBe(0);

        expect($je->isBalanced())->toBeTrue();
    });

    it('creates FX loss when payment rate < invoice rate for customer payment', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000]);

        // Payment at 14,500 IDR/USD (rate went down = loss for receiver)
        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'exchange_rate' => 14500,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // Cash: 1000 * 14500 = 14,500,000
        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->debit)->toBe(14_500_000);

        // AR: 1000 * 15000 = 15,000,000
        $arAccount = Account::where('code', '1-1100')->first();
        $arLine = $je->lines->firstWhere('account_id', $arAccount->id);
        expect($arLine->credit)->toBe(15_000_000);

        // FX Loss: 500,000 (debit)
        $fxLine = $je->lines->firstWhere('account_id', $this->fxLossAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->debit)->toBe(500_000)
            ->and($fxLine->credit)->toBe(0);

        expect($je->isBalanced())->toBeTrue();
    });

    it('creates no FX lines when payment rate equals invoice rate', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'exchange_rate' => 15000, // same rate
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // Should only have 2 lines (Cash + AR), no FX
        expect($je->lines)->toHaveCount(2)
            ->and($je->isBalanced())->toBeTrue();
    });

    it('creates no FX lines for IDR invoice', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'IDR',
            'exchange_rate' => 1,
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // Only 2 lines, no FX
        expect($je->lines)->toHaveCount(2)
            ->and($je->isBalanced())->toBeTrue();
    });

    it('creates FX loss for bill payment when payment rate > invoice rate', function () {
        // Bill booked at 15,000 IDR/USD
        $bill = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);

        // Payment at 15,500 (paying MORE = loss for payer)
        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500,
            'exchange_rate' => 15500,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // AP: 500 * 15000 = 7,500,000 (debit at invoice rate)
        $apAccount = Account::where('code', '2-1100')->first();
        $apLine = $je->lines->firstWhere('account_id', $apAccount->id);
        expect($apLine->debit)->toBe(7_500_000);

        // Cash: 500 * 15500 = 7,750,000 (credit at payment rate)
        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->credit)->toBe(7_750_000);

        // FX Loss: 250,000 (debit)
        $fxLine = $je->lines->firstWhere('account_id', $this->fxLossAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->debit)->toBe(250_000);

        expect($je->isBalanced())->toBeTrue();
    });

    it('creates FX gain for bill payment when payment rate < invoice rate', function () {
        $bill = Bill::factory()->hasItems(1)->create([
            'status' => 'received',
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 500,
            'paid_amount' => 0,
        ]);

        // Payment at 14,500 (paying LESS = gain for payer)
        $payment = $this->service->create([
            'type' => Payment::TYPE_SEND,
            'contact_id' => $bill->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500,
            'exchange_rate' => 14500,
            'payment_date' => now()->toDateString(),
            'bill_id' => $bill->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // FX Gain: 250,000 (credit)
        $fxLine = $je->lines->firstWhere('account_id', $this->fxGainAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->credit)->toBe(250_000);

        expect($je->isBalanced())->toBeTrue();
    });

    it('voids payment and reverses FX lines automatically', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000]);

        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'exchange_rate' => 15500,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        // Void the payment
        $this->service->void($payment);

        // Original JE should be reversed
        $payment->refresh();
        $originalJe = $payment->journalEntry;
        expect($originalJe->is_reversed)->toBeTrue();

        // Reversal JE should have the FX gain line too (swapped)
        $reversalJe = $originalJe->reversedBy->load('lines');
        $fxLine = $reversalJe->lines->firstWhere('account_id', $this->fxGainAccount->id);
        expect($fxLine)->not->toBeNull()
            ->and($fxLine->debit)->toBe(500_000) // swapped from credit
            ->and($reversalJe->isBalanced())->toBeTrue();
    });

    it('calculates FX on partial payment amounts only', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 2000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 2000]);

        // Partial payment of $500 at different rate
        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 500,
            'exchange_rate' => 16000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // Cash: 500 * 16000 = 8,000,000
        $cashLine = $je->lines->firstWhere('account_id', $this->cashAccount->id);
        expect($cashLine->debit)->toBe(8_000_000);

        // AR: 500 * 15000 = 7,500,000 (only the partial amount)
        $arAccount = Account::where('code', '1-1100')->first();
        $arLine = $je->lines->firstWhere('account_id', $arAccount->id);
        expect($arLine->credit)->toBe(7_500_000);

        // FX Gain on partial: 500 * (16000 - 15000) = 500,000
        $fxLine = $je->lines->firstWhere('account_id', $this->fxGainAccount->id);
        expect($fxLine->credit)->toBe(500_000)
            ->and($je->isBalanced())->toBeTrue();
    });

    it('uses invoice rate as default when no exchange_rate provided', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'line_total' => 1000]);

        // No exchange_rate in data — should default to invoice rate
        $payment = $this->service->create([
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $invoice->contact_id,
            'cash_account_id' => $this->cashAccount->id,
            'amount' => 1000,
            'payment_date' => now()->toDateString(),
            'invoice_id' => $invoice->id,
        ]);

        $je = $payment->journalEntry->load('lines');

        // No FX lines — same rate
        expect($je->lines)->toHaveCount(2)
            ->and($je->isBalanced())->toBeTrue();

        // Payment stored with invoice rate
        expect((float) $payment->exchange_rate)->toBe(15000.0);
    });
});
