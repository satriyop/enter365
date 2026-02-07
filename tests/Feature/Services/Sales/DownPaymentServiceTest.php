<?php

declare(strict_types=1);

use App\Contracts\Sales\DownPaymentServiceInterface;
use App\Enums\DocumentStatus;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Exceptions\Domain\StateTransitionException;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\BillItem;
use App\Models\Sales\DownPayment;
use App\Models\Sales\DownPaymentApplication;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\User;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->service = app(DownPaymentServiceInterface::class);

    $this->customer = Contact::factory()->customer()->create();
    $this->bankAccount = Account::where('code', '1110')->first()
        ?? Account::factory()->create(['code' => '1110']);
});

describe('create', function () {
    it('creates receivable down payment', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        expect($dp)
            ->toBeInstanceOf(DownPayment::class)
            ->dp_number->toStartWith('DPR-')
            ->type->toBe(DownPayment::TYPE_RECEIVABLE)
            ->amount->toBe(10000000)
            ->applied_amount->toBe(0)
            ->journal_entry_id->not->toBeNull();
    });

    it('creates payable down payment', function () {
        $vendor = Contact::factory()->vendor()->create();

        $dp = $this->service->create([
            'type' => DownPayment::TYPE_PAYABLE,
            'contact_id' => $vendor->id,
            'dp_date' => now()->toDateString(),
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        expect($dp)
            ->toBeInstanceOf(DownPayment::class)
            ->dp_number->toStartWith('DPP-')
            ->type->toBe(DownPayment::TYPE_PAYABLE);
    });
});

describe('update', function () {
    it('updates active down payment with no applications', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $updated = $this->service->update($dp, [
            'description' => 'Updated description',
        ]);

        expect($updated->description)->toBe('Updated description');
    });

    it('throws exception when updating down payment with applications', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->withAmount(10000000)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Create application directly in DB
        DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create(['contact_id' => $this->customer->id])->id,
            'amount' => 5000000,
        ]);

        expect(fn () => $this->service->update($dp, ['description' => 'test']))
            ->toThrow(DocumentLockedException::class);
    });

    it('throws exception when updating non-active down payment', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->cancelled()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        expect(fn () => $this->service->update($dp, ['description' => 'test']))
            ->toThrow(StateTransitionException::class);
    });
});

describe('delete', function () {
    it('deletes down payment with no applications', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $result = $this->service->delete($dp);

        expect($result)->toBeTrue()
            ->and(DownPayment::find($dp->id))->toBeNull();
    });

    it('throws exception when deleting down payment with applications', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        DownPaymentApplication::factory()->create([
            'down_payment_id' => $dp->id,
            'applicable_type' => Invoice::class,
            'applicable_id' => Invoice::factory()->create(['contact_id' => $this->customer->id])->id,
            'amount' => 5000000,
        ]);

        expect(fn () => $this->service->delete($dp))
            ->toThrow(DocumentLockedException::class);
    });
});

describe('cancel', function () {
    it('cancels active down payment', function () {
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 10000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $this->bankAccount->id,
        ]);

        $result = $this->service->cancel($dp, 'Dibatalkan karena perubahan kontrak');

        expect($result->status)->toBe(DocumentStatus::Cancelled);
    });

    it('throws exception when cancelling non-active down payment', function () {
        $dp = DownPayment::factory()
            ->receivable()
            ->cancelled()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        expect(fn () => $this->service->cancel($dp))
            ->toThrow(StateTransitionException::class);
    });
});

describe('getAvailableForContact', function () {
    it('returns available down payments for contact', function () {
        // Active with remaining balance
        DownPayment::factory()
            ->receivable()
            ->forContact($this->customer)
            ->withAmount(10000000)
            ->count(2)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Fully applied - should not be returned
        DownPayment::factory()
            ->receivable()
            ->fullyApplied()
            ->forContact($this->customer)
            ->create(['cash_account_id' => $this->bankAccount->id]);

        // Different contact - should not be returned
        DownPayment::factory()->receivable()->create(['cash_account_id' => $this->bankAccount->id]);

        $available = $this->service->getAvailableForContact(
            $this->customer->id,
            DownPayment::TYPE_RECEIVABLE
        );

        expect($available)->toHaveCount(2);
    });

    it('returns empty collection when no available down payments', function () {
        $available = $this->service->getAvailableForContact(
            $this->customer->id,
            DownPayment::TYPE_RECEIVABLE
        );

        expect($available)->toHaveCount(0);
    });
});

describe('applyToInvoice', function () {
    it('creates journal entry when applying DP to invoice', function () {
        $bankAccount = Account::where('code', '1-1010')->first();

        // Create DP via service (creates initial JE: Dr Cash, Cr DP Liability)
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
        ]);

        // Create and post invoice
        $invoice = Invoice::factory()->forContact($this->customer)->create([
            'status' => DocumentStatus::Sent,
            'subtotal' => 10000000,
            'tax_amount' => 0,
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);
        InvoiceItem::factory()->forInvoice($invoice)->create([
            'line_total' => 10000000,
        ]);
        app(JournalService::class)->postInvoice($invoice->fresh());

        // Apply DP to invoice
        $application = $this->service->applyToInvoice($dp->fresh(), $invoice->fresh(), [
            'amount' => 5000000,
            'applied_date' => now()->toDateString(),
        ]);

        // Verify application JE was created
        expect($application->journal_entry_id)->not->toBeNull();

        // Verify JE lines: Dr DP Liability (2-1700), Cr AR (1-1100)
        $dpAccount = Account::where('code', '2-1700')->first();
        $arAccount = Account::where('code', '1-1100')->first();

        $debitLine = JournalEntryLine::where('journal_entry_id', $application->journal_entry_id)
            ->where('debit', '>', 0)
            ->first();
        $creditLine = JournalEntryLine::where('journal_entry_id', $application->journal_entry_id)
            ->where('credit', '>', 0)
            ->first();

        expect($debitLine->account_id)->toBe($dpAccount->id)
            ->and($debitLine->debit)->toBe(5000000)
            ->and($creditLine->account_id)->toBe($arAccount->id)
            ->and($creditLine->credit)->toBe(5000000);

        // Verify DP applied_amount updated
        $dp->refresh();
        expect($dp->applied_amount)->toBe(5000000);

        // Verify invoice paid_amount updated
        $invoice->refresh();
        expect($invoice->paid_amount)->toBe(5000000);
    });

    it('prevents applying more than DP remaining amount', function () {
        $bankAccount = Account::where('code', '1-1010')->first();

        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 3000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->forContact($this->customer)->create([
            'status' => DocumentStatus::Sent,
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);

        expect(fn () => $this->service->applyToInvoice($dp->fresh(), $invoice, [
            'amount' => 5000000,
        ]))->toThrow(BusinessRuleException::class);
    });

    it('prevents applying DP to wrong contact invoice', function () {
        $bankAccount = Account::where('code', '1-1010')->first();
        $otherCustomer = Contact::factory()->customer()->create();

        $dp = $this->service->create([
            'type' => DownPayment::TYPE_RECEIVABLE,
            'contact_id' => $this->customer->id,
            'dp_date' => now()->toDateString(),
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
        ]);

        $invoice = Invoice::factory()->forContact($otherCustomer)->create([
            'status' => DocumentStatus::Sent,
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);

        expect(fn () => $this->service->applyToInvoice($dp->fresh(), $invoice, [
            'amount' => 5000000,
        ]))->toThrow(BusinessRuleException::class);
    });
});

describe('applyToBill', function () {
    it('creates journal entry when applying DP to bill', function () {
        $vendor = Contact::factory()->supplier()->create();
        $bankAccount = Account::where('code', '1-1010')->first();

        // Create payable DP via service (creates initial JE: Dr DP Asset, Cr Cash)
        $dp = $this->service->create([
            'type' => DownPayment::TYPE_PAYABLE,
            'contact_id' => $vendor->id,
            'dp_date' => now()->toDateString(),
            'amount' => 5000000,
            'payment_method' => 'bank_transfer',
            'cash_account_id' => $bankAccount->id,
        ]);

        // Create and post bill
        $bill = Bill::factory()->forContact($vendor)->create([
            'status' => DocumentStatus::Received,
            'subtotal' => 10000000,
            'tax_amount' => 0,
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'line_total' => 10000000,
        ]);
        app(JournalService::class)->postBill($bill->fresh());

        // Apply DP to bill
        $application = $this->service->applyToBill($dp->fresh(), $bill->fresh(), [
            'amount' => 5000000,
            'applied_date' => now()->toDateString(),
        ]);

        // Verify application JE was created
        expect($application->journal_entry_id)->not->toBeNull();

        // Verify JE lines: Dr AP (2-1100), Cr DP Asset (1-1700)
        $apAccount = Account::where('code', '2-1100')->first();
        $dpAccount = Account::where('code', '1-1700')->first();

        $debitLine = JournalEntryLine::where('journal_entry_id', $application->journal_entry_id)
            ->where('debit', '>', 0)
            ->first();
        $creditLine = JournalEntryLine::where('journal_entry_id', $application->journal_entry_id)
            ->where('credit', '>', 0)
            ->first();

        expect($debitLine->account_id)->toBe($apAccount->id)
            ->and($debitLine->debit)->toBe(5000000)
            ->and($creditLine->account_id)->toBe($dpAccount->id)
            ->and($creditLine->credit)->toBe(5000000);

        // Verify DP applied_amount updated
        $dp->refresh();
        expect($dp->applied_amount)->toBe(5000000);

        // Verify bill paid_amount updated
        $bill->refresh();
        expect($bill->paid_amount)->toBe(5000000);
    });
});
