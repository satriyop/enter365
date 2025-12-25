<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use App\Models\Accounting\Payment;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Payment API', function () {
    
    it('can list all payments', function () {
        $account = Account::where('code', '1-1010')->first(); // Bank BCA
        Payment::factory()->withCashAccount($account)->count(3)->create();

        $response = $this->getJson('/api/v1/payments');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter payments by type', function () {
        $account = Account::where('code', '1-1010')->first();
        Payment::factory()->receive()->withCashAccount($account)->count(2)->create();
        Payment::factory()->send()->withCashAccount($account)->count(3)->create();

        $response = $this->getJson('/api/v1/payments?type=receive');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a receive payment from customer', function () {
        $customer = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $customer->id,
            'payment_date' => '2024-12-25',
            'amount' => 1000000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'reference' => 'TRF-123456',
            'notes' => 'Payment received via transfer',
            'cash_account_id' => $bankAccount->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'receive')
            ->assertJsonPath('data.amount', 1000000)
            ->assertJsonPath('data.payment_method', 'transfer');
        
        // Verify journal entry was created
        $this->assertNotNull($response->json('data.journal_entry_id'));
    });

    it('can create a send payment to supplier', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $supplier->id,
            'payment_date' => '2024-12-25',
            'amount' => 500000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'send')
            ->assertJsonPath('data.amount', 500000);
    });

    it('can allocate payment to invoice', function () {
        $customer = Contact::factory()->customer()->create();
        $invoice = Invoice::factory()->forContact($customer)->create([
            'status' => Invoice::STATUS_SENT,
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->forInvoice($invoice)->create([
            'amount' => 1000000,
        ]);
        
        // Post invoice first
        app(JournalService::class)->postInvoice($invoice->fresh());
        
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $customer->id,
            'payment_date' => '2024-12-25',
            'amount' => 500000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
            'invoice_id' => $invoice->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payable_id', $invoice->id);
        
        // Verify invoice paid amount was updated
        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(500000);
        expect($invoice->status)->toBe(Invoice::STATUS_PARTIAL);
    });

    it('can allocate payment to bill', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
            'paid_amount' => 0,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'amount' => 1000000,
        ]);
        
        // Post bill first
        app(JournalService::class)->postBill($bill->fresh());
        
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_SEND,
            'contact_id' => $supplier->id,
            'payment_date' => '2024-12-25',
            'amount' => 1110000,
            'payment_method' => Payment::METHOD_TRANSFER,
            'cash_account_id' => $bankAccount->id,
            'bill_id' => $bill->id,
        ]);

        $response->assertCreated();
        
        // Verify bill is fully paid
        $bill->refresh();
        expect($bill->paid_amount)->toBe(1110000);
        expect($bill->status)->toBe(Bill::STATUS_PAID);
    });

    it('validates required fields', function () {
        $response = $this->postJson('/api/v1/payments', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'contact_id', 'payment_date', 'amount', 'cash_account_id']);
    });

    it('prevents payment exceeding invoice outstanding amount', function () {
        $customer = Contact::factory()->customer()->create();
        $invoice = Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 100000,
            'paid_amount' => 0,
        ]);
        
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_RECEIVE,
            'contact_id' => $customer->id,
            'payment_date' => '2024-12-25',
            'amount' => 200000, // More than outstanding
            'cash_account_id' => $bankAccount->id,
            'invoice_id' => $invoice->id,
        ]);

        $response->assertUnprocessable();
    });

    it('prevents receive payment allocated to bill', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->forContact($supplier)->received()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        $response = $this->postJson('/api/v1/payments', [
            'type' => Payment::TYPE_RECEIVE, // Wrong type
            'contact_id' => $supplier->id,
            'payment_date' => '2024-12-25',
            'amount' => 100000,
            'cash_account_id' => $bankAccount->id,
            'bill_id' => $bill->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bill_id']);
    });

    it('can show a payment', function () {
        $bankAccount = Account::where('code', '1-1010')->first();
        $payment = Payment::factory()->withCashAccount($bankAccount)->create();

        $response = $this->getJson("/api/v1/payments/{$payment->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $payment->id);
    });

    it('can void a payment', function () {
        $customer = Contact::factory()->customer()->create();
        $bankAccount = Account::where('code', '1-1010')->first();
        
        // Create a payment without invoice allocation for simpler test
        $payment = Payment::factory()
            ->receive()
            ->forContact($customer)
            ->withCashAccount($bankAccount)
            ->withAmount(500000)
            ->create();
        
        // Post payment to journal
        app(JournalService::class)->postPayment($payment);

        $response = $this->postJson("/api/v1/payments/{$payment->id}/void");

        $response->assertOk();
        
        // Verify payment is voided
        $payment->refresh();
        expect($payment->is_voided)->toBeTrue();
    });

    it('cannot void already voided payment', function () {
        $bankAccount = Account::where('code', '1-1010')->first();
        $payment = Payment::factory()->voided()->withCashAccount($bankAccount)->create();

        $response = $this->postJson("/api/v1/payments/{$payment->id}/void");

        $response->assertUnprocessable();
    });
});
