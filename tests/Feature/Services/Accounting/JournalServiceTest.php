<?php

declare(strict_types=1);

use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\DocumentLockedException;
use App\Models\Accounting\Account;
use App\Models\Accounting\FiscalPeriod;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Services\Accounting\JournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();

    // Seed required data
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $this->service = app(JournalService::class);
});

describe('JournalService - createEntry', function () {

    it('creates a balanced journal entry with manual lines', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $data = [
            'entry_date' => now()->toDateString(),
            'description' => 'Test manual entry',
            'reference' => 'TEST-001',
            'lines' => [
                [
                    'account_id' => $cashAccount->id,
                    'description' => 'Cash received',
                    'debit' => 1000000,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenueAccount->id,
                    'description' => 'Revenue earned',
                    'debit' => 0,
                    'credit' => 1000000,
                ],
            ],
        ];

        $entry = $this->service->createEntry($data);

        expect($entry)
            ->toBeInstanceOf(JournalEntry::class)
            ->and($entry->description)->toBe('Test manual entry')
            ->and($entry->reference)->toBe('TEST-001')
            ->and($entry->is_posted)->toBeFalse()
            ->and($entry->lines)->toHaveCount(2)
            ->and($entry->isBalanced())->toBeTrue();
    });

    it('creates entry with account_code instead of account_id', function () {
        $data = [
            'entry_date' => now()->toDateString(),
            'description' => 'Entry using account codes',
            'lines' => [
                [
                    'account_code' => '1-1000',
                    'description' => 'Debit cash',
                    'debit' => 500000,
                    'credit' => 0,
                ],
                [
                    'account_code' => '4-1001',
                    'description' => 'Credit revenue',
                    'debit' => 0,
                    'credit' => 500000,
                ],
            ],
        ];

        $entry = $this->service->createEntry($data);

        expect($entry->isBalanced())->toBeTrue()
            ->and($entry->lines)->toHaveCount(2);
    });

    it('auto-posts entry when autoPost is true', function () {
        $data = [
            'entry_date' => now()->toDateString(),
            'description' => 'Auto-posted entry',
            'lines' => [
                ['account_code' => '1-1000', 'debit' => 100000, 'credit' => 0],
                ['account_code' => '4-1001', 'debit' => 0, 'credit' => 100000],
            ],
        ];

        $entry = $this->service->createEntry($data, autoPost: true);

        expect($entry->is_posted)->toBeTrue();
    });

    it('generates entry_number automatically', function () {
        $data = [
            'entry_date' => now()->toDateString(),
            'description' => 'Entry without number',
            'lines' => [
                ['account_code' => '1-1000', 'debit' => 100000, 'credit' => 0],
                ['account_code' => '4-1001', 'debit' => 0, 'credit' => 100000],
            ],
        ];

        $entry = $this->service->createEntry($data);

        expect($entry->entry_number)->toContain('JE-');
    });

    it('associates entry with current fiscal period', function () {
        $data = [
            'entry_date' => now()->toDateString(),
            'description' => 'Entry with fiscal period',
            'lines' => [
                ['account_code' => '1-1000', 'debit' => 100000, 'credit' => 0],
                ['account_code' => '4-1001', 'debit' => 0, 'credit' => 100000],
            ],
        ];

        $entry = $this->service->createEntry($data);

        expect($entry->fiscal_period_id)->not->toBeNull()
            ->and($entry->fiscalPeriod)->toBeInstanceOf(FiscalPeriod::class);
    });

});

describe('JournalService - postEntry', function () {

    it('posts a valid balanced entry', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $posted = $this->service->postEntry($entry);

        expect($posted->is_posted)->toBeTrue();
    });

    it('throws exception when posting already posted entry', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $this->service->postEntry($entry);
    })->throws(DocumentLockedException::class, 'already posted');

    it('throws exception when posting unbalanced entry', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 50000,
        ]);

        $this->service->postEntry($entry);
    })->throws(BusinessRuleException::class, 'not balanced');

    it('throws exception when posting entry with less than 2 lines', function () {
        $cashAccount = Account::where('code', '1-1000')->first();

        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 100000,
        ]);

        $this->service->postEntry($entry);
    })->throws(BusinessRuleException::class, 'at least two lines');

    it('throws exception when posting to closed fiscal period', function () {
        $closedPeriod = FiscalPeriod::factory()->closed()->create();
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create([
            'is_posted' => false,
            'fiscal_period_id' => $closedPeriod->id,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $this->service->postEntry($entry);
    })->throws(BusinessRuleException::class, 'sudah ditutup');

});

describe('JournalService - reverseEntry', function () {

    it('creates reversal entry with swapped debits and credits', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create(['is_posted' => true]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
            'description' => 'Original debit',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
            'description' => 'Original credit',
        ]);

        $entry->load('lines');

        $reversal = $this->service->reverseEntry($entry);

        expect($reversal)
            ->toBeInstanceOf(JournalEntry::class)
            ->and($reversal->is_posted)->toBeTrue()
            ->and($reversal->source_type)->toBe(JournalEntry::SOURCE_REVERSAL)
            ->and($reversal->reversal_of_id)->toBe($entry->id);

        // Verify swapped amounts
        $originalLine1 = $entry->lines->first();
        $reversalLine1 = $reversal->lines->firstWhere('account_id', $originalLine1->account_id);

        expect($reversalLine1->debit)->toBe($originalLine1->credit)
            ->and($reversalLine1->credit)->toBe($originalLine1->debit);

        // Verify original marked as reversed
        $entry->refresh();
        expect($entry->is_reversed)->toBeTrue()
            ->and($entry->reversed_by_id)->toBe($reversal->id);
    });

    it('throws exception when reversing unposted entry', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => false]);

        $this->service->reverseEntry($entry);
    })->throws(BusinessRuleException::class, 'unposted');

    it('throws exception when reversing already reversed entry', function () {
        $entry = JournalEntry::factory()->create(['is_posted' => true, 'is_reversed' => true]);

        $this->service->reverseEntry($entry);
    })->throws(BusinessRuleException::class, 'already reversed');

    it('creates reversal with correct description', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_number' => 'JE-001',
            'description' => 'Original entry',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);

        $entry->load('lines');

        $reversal = $this->service->reverseEntry($entry);

        expect($reversal->description)->toContain('Reversal of JE-001');
    });

    it('keeps the caller reversal reason on the ledger', function () {
        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_number' => 'JE-REASON',
            'description' => 'Original sale',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
        $entry->load('lines');

        $reversal = $this->service->reverseEntry($entry, 'Void POS-202608-0042');

        expect($reversal->description)->toBe('Void POS-202608-0042');
    });

    it('posts the reversal into the current open period when the original period is closed', function () {
        $closedPeriod = FiscalPeriod::factory()->closed()->create([
            'start_date' => '2020-01-01',
            'end_date' => '2020-01-31',
        ]);

        $cashAccount = Account::where('code', '1-1000')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        $entry = JournalEntry::factory()->create([
            'is_posted' => true,
            'entry_date' => '2020-01-15',
            'fiscal_period_id' => $closedPeriod->id,
            'description' => 'Old sale',
        ]);

        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $cashAccount->id,
            'debit' => 100000,
            'credit' => 0,
        ]);
        JournalEntryLine::factory()->create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenueAccount->id,
            'debit' => 0,
            'credit' => 100000,
        ]);
        $entry->load('lines');

        $reversal = $this->service->reverseEntry($entry, 'Void after close');

        expect($reversal->description)->toBe('Void after close')
            ->and($reversal->entry_date->toDateString())->toBe(now()->toDateString())
            ->and($reversal->is_posted)->toBeTrue();
    });

});

describe('JournalService - postInvoice', function () {

    it('creates journal entry for posted invoice', function () {
        $invoice = Invoice::factory()->create();

        // Create invoice items with specific totals
        $item1 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 500000,
            'line_total' => 500000,
        ]);
        $item2 = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 500000,
            'line_total' => 500000,
        ]);

        // Update invoice totals to match items
        $invoice->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postInvoice($invoice);

        expect($entry)
            ->toBeInstanceOf(JournalEntry::class)
            ->and($entry->is_posted)->toBeTrue()
            ->and($entry->source_type)->toBe(JournalEntry::SOURCE_INVOICE)
            ->and($entry->source_id)->toBe($invoice->id)
            ->and($entry->isBalanced())->toBeTrue();

        // Verify invoice updated
        $invoice->refresh();
        expect($invoice->journal_entry_id)->toBe($entry->id)
            ->and($invoice->receivable_account_id)->not->toBeNull();
    });

    it('creates debit to accounts receivable for total amount', function () {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $invoice->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postInvoice($invoice);

        $receivableAccount = Account::where('code', '1-1100')->first();
        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);

        expect($receivableLine->debit)->toBe(1100000)
            ->and($receivableLine->credit)->toBe(0);
    });

    it('creates credit to revenue account for subtotal', function () {
        $invoice = Invoice::factory()->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'tax_rate' => 0,
            'total_amount' => 1000000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 2,
            'unit_price' => 500000,
            'line_total' => 1000000,
        ]);

        $invoice->load('items.product', 'contact');

        $entry = $this->service->postInvoice($invoice);

        $revenueAccount = Account::where('code', '4-1001')->first();
        $revenueLine = $entry->lines->firstWhere('account_id', $revenueAccount->id);

        expect($revenueLine->credit)->toBe(1000000)
            ->and($revenueLine->debit)->toBe(0);
    });

    it('creates credit to tax payable for tax amount', function () {
        $invoice = Invoice::factory()->create();

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $invoice->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postInvoice($invoice);

        $taxAccount = Account::where('code', '2-1200')->first();
        $taxLine = $entry->lines->firstWhere('account_id', $taxAccount->id);

        expect($taxLine)->not->toBeNull()
            ->and($taxLine->credit)->toBe(100000);
    });

    it('throws exception when invoice already posted to journal', function () {
        // Create a real journal entry first
        $journalEntry = JournalEntry::factory()->create();

        $invoice = Invoice::factory()->create([
            'journal_entry_id' => $journalEntry->id,
            'subtotal' => 1000000,
            'total_amount' => 1000000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'line_total' => 1000000,
        ]);

        $this->service->postInvoice($invoice);
    })->throws(BusinessRuleException::class, 'already posted');

});

describe('JournalService - postBill', function () {

    it('creates journal entry for posted bill', function () {
        $bill = Bill::factory()->create();

        // Create bill items
        \App\Models\Purchasing\BillItem::factory()->count(2)->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 500000,
            'line_total' => 500000,
        ]);

        $bill->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postBill($bill);

        expect($entry)
            ->toBeInstanceOf(JournalEntry::class)
            ->and($entry->is_posted)->toBeTrue()
            ->and($entry->source_type)->toBe(JournalEntry::SOURCE_BILL)
            ->and($entry->source_id)->toBe($bill->id)
            ->and($entry->isBalanced())->toBeTrue();

        // Verify bill updated
        $bill->refresh();
        expect($bill->journal_entry_id)->toBe($entry->id)
            ->and($bill->payable_account_id)->not->toBeNull();
    });

    it('creates debit to expense account for subtotal', function () {
        $bill = Bill::factory()->create([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'tax_rate' => 0,
            'total_amount' => 1000000,
        ]);

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $bill->load('items', 'contact');

        $entry = $this->service->postBill($bill);

        $expenseAccount = Account::where('code', '5-1002')->first();
        $expenseLine = $entry->lines->firstWhere('account_id', $expenseAccount->id);

        expect($expenseLine->debit)->toBe(1000000)
            ->and($expenseLine->credit)->toBe(0);
    });

    it('creates debit to tax receivable for tax amount', function () {
        $bill = Bill::factory()->create();

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $bill->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postBill($bill);

        $taxAccount = Account::where('code', '1-1300')->first();
        $taxLine = $entry->lines->firstWhere('account_id', $taxAccount->id);

        expect($taxLine)->not->toBeNull()
            ->and($taxLine->debit)->toBe(100000);
    });

    it('creates credit to accounts payable for total amount', function () {
        $bill = Bill::factory()->create();

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $bill->update([
            'subtotal' => 1000000,
            'tax_amount' => 100000,
            'total_amount' => 1100000,
        ]);

        $entry = $this->service->postBill($bill);

        $payableAccount = Account::where('code', '2-1100')->first();
        $payableLine = $entry->lines->firstWhere('account_id', $payableAccount->id);

        expect($payableLine->credit)->toBe(1100000)
            ->and($payableLine->debit)->toBe(0);
    });

    it('throws exception when bill already posted to journal', function () {
        // Create a real journal entry first
        $journalEntry = JournalEntry::factory()->create();

        $bill = Bill::factory()->create(['journal_entry_id' => $journalEntry->id]);

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $this->service->postBill($bill);
    })->throws(BusinessRuleException::class, 'already posted');

});

describe('JournalService - postPayment', function () {

    it('creates journal entry for customer payment', function () {
        $invoice = Invoice::factory()->sent()->create();
        $cashAccount = Account::where('code', '1-1000')->first();

        $payment = \App\Models\Shared\Payment::factory()->create([
            'type' => \App\Models\Shared\Payment::TYPE_RECEIVE,
            'amount' => 500000,
            'cash_account_id' => $cashAccount->id,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
        ]);

        $entry = $this->service->postPayment($payment);

        expect($entry)
            ->toBeInstanceOf(JournalEntry::class)
            ->and($entry->is_posted)->toBeTrue()
            ->and($entry->source_type)->toBe(JournalEntry::SOURCE_PAYMENT)
            ->and($entry->isBalanced())->toBeTrue();
    });

    it('debits cash and credits receivable for customer payment', function () {
        $invoice = Invoice::factory()->sent()->create();
        $cashAccount = Account::where('code', '1-1000')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $payment = \App\Models\Shared\Payment::factory()->create([
            'type' => \App\Models\Shared\Payment::TYPE_RECEIVE,
            'amount' => 500000,
            'cash_account_id' => $cashAccount->id,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
        ]);

        $entry = $this->service->postPayment($payment);

        $cashLine = $entry->lines->firstWhere('account_id', $cashAccount->id);
        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);

        expect($cashLine->debit)->toBe(500000)
            ->and($cashLine->credit)->toBe(0)
            ->and($receivableLine->debit)->toBe(0)
            ->and($receivableLine->credit)->toBe(500000);
    });

    it('creates journal entry for supplier payment', function () {
        $bill = Bill::factory()->create();
        $cashAccount = Account::where('code', '1-1000')->first();

        $payment = \App\Models\Shared\Payment::factory()->create([
            'type' => \App\Models\Shared\Payment::TYPE_SEND,
            'amount' => 300000,
            'cash_account_id' => $cashAccount->id,
            'payable_type' => Bill::class,
            'payable_id' => $bill->id,
        ]);

        $entry = $this->service->postPayment($payment);

        expect($entry->isBalanced())->toBeTrue();
    });

    it('debits payable and credits cash for supplier payment', function () {
        $bill = Bill::factory()->create();
        $cashAccount = Account::where('code', '1-1000')->first();
        $payableAccount = Account::where('code', '2-1100')->first();

        $payment = \App\Models\Shared\Payment::factory()->create([
            'type' => \App\Models\Shared\Payment::TYPE_SEND,
            'amount' => 300000,
            'cash_account_id' => $cashAccount->id,
            'payable_type' => Bill::class,
            'payable_id' => $bill->id,
        ]);

        $entry = $this->service->postPayment($payment);

        $payableLine = $entry->lines->firstWhere('account_id', $payableAccount->id);
        $cashLine = $entry->lines->firstWhere('account_id', $cashAccount->id);

        expect($payableLine->debit)->toBe(300000)
            ->and($payableLine->credit)->toBe(0)
            ->and($cashLine->debit)->toBe(0)
            ->and($cashLine->credit)->toBe(300000);
    });

});

describe('JournalService - Multi-Currency', function () {

    it('postInvoice with IDR invoice creates JE in IDR (regression)', function () {
        $invoice = Invoice::factory()->create([
            'currency' => 'IDR',
            'exchange_rate' => 1,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $invoice->update([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);

        $entry = $this->service->postInvoice($invoice);

        $receivableAccount = Account::where('code', '1-1100')->first();
        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);

        expect($entry->isBalanced())->toBeTrue()
            ->and($receivableLine->debit)->toBe(1110000);
    });

    it('postInvoice with USD invoice creates JE in base currency IDR', function () {
        $exchangeRate = 15000;
        $invoice = Invoice::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => $exchangeRate,
        ]);

        // $500 USD item
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);

        // $300 USD item
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 300,
            'line_total' => 300,
        ]);

        $invoice->update([
            'subtotal' => 800,          // $800 USD
            'tax_amount' => 88,          // 11% of $800
            'discount_amount' => 0,
            'total_amount' => 888,       // $888 USD
        ]);

        $entry = $this->service->postInvoice($invoice);

        // All amounts should be in IDR
        $receivableAccount = Account::where('code', '1-1100')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();
        $taxAccount = Account::where('code', '2-1200')->first();

        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);
        $revenueLine = $entry->lines->firstWhere('account_id', $revenueAccount->id);
        $taxLine = $entry->lines->firstWhere('account_id', $taxAccount->id);

        // Revenue: ($500 + $300) * 15000 = 12,000,000
        expect($revenueLine->credit)->toBe(12_000_000)
            // Tax: $88 * 15000 = 1,320,000
            ->and($taxLine->credit)->toBe(1_320_000)
            // AR should be balancing figure: 12,000,000 + 1,320,000 = 13,320,000
            ->and($receivableLine->debit)->toBe(13_320_000)
            // JE must be balanced
            ->and($entry->isBalanced())->toBeTrue();
    });

    it('postBill with USD bill creates JE in base currency IDR', function () {
        $exchangeRate = 15000;
        $bill = Bill::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => $exchangeRate,
        ]);

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        $bill->update([
            'subtotal' => 1000,           // $1,000 USD
            'tax_amount' => 110,           // $110 USD
            'discount_amount' => 0,
            'total_amount' => 1110,        // $1,110 USD
        ]);

        $entry = $this->service->postBill($bill);

        $payableAccount = Account::where('code', '2-1100')->first();
        $expenseAccount = Account::where('code', '5-1002')->first();
        $taxAccount = Account::where('code', '1-1300')->first();

        $payableLine = $entry->lines->firstWhere('account_id', $payableAccount->id);
        $expenseLine = $entry->lines->firstWhere('account_id', $expenseAccount->id);
        $taxLine = $entry->lines->firstWhere('account_id', $taxAccount->id);

        // Expense: $1000 * 15000 = 15,000,000
        expect($expenseLine->debit)->toBe(15_000_000)
            // Tax: $110 * 15000 = 1,650,000
            ->and($taxLine->debit)->toBe(1_650_000)
            // AP should be balancing figure: 15,000,000 + 1,650,000 = 16,650,000
            ->and($payableLine->credit)->toBe(16_650_000)
            ->and($entry->isBalanced())->toBeTrue();
    });

    it('postPayment for USD invoice creates JE in base currency IDR', function () {
        $invoice = Invoice::factory()->sent()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
        ]);
        $cashAccount = Account::where('code', '1-1000')->first();
        $receivableAccount = Account::where('code', '1-1100')->first();

        $payment = \App\Models\Shared\Payment::factory()->create([
            'type' => \App\Models\Shared\Payment::TYPE_RECEIVE,
            'amount' => 500,              // $500 USD
            'currency' => 'USD',
            'exchange_rate' => 15000,
            'base_currency_amount' => 7_500_000, // $500 * 15000
            'cash_account_id' => $cashAccount->id,
            'payable_type' => Invoice::class,
            'payable_id' => $invoice->id,
        ]);

        $entry = $this->service->postPayment($payment);

        $cashLine = $entry->lines->firstWhere('account_id', $cashAccount->id);
        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);

        // Amount should be in IDR (7,500,000), not USD (500)
        expect($cashLine->debit)->toBe(7_500_000)
            ->and($receivableLine->credit)->toBe(7_500_000)
            ->and($entry->isBalanced())->toBeTrue();
    });

    it('multi-currency JE is always balanced even with rounding', function () {
        $exchangeRate = 14_853.7; // Awkward rate that causes rounding
        $invoice = Invoice::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => $exchangeRate,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 333,
            'line_total' => 333,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 667,
            'line_total' => 667,
        ]);

        $invoice->update([
            'subtotal' => 1000,
            'tax_amount' => 110,
            'discount_amount' => 50,
            'total_amount' => 1060,
        ]);

        $entry = $this->service->postInvoice($invoice);

        // Regardless of rounding, JE must balance
        expect($entry->isBalanced())->toBeTrue()
            ->and($entry->getTotalDebit())->toBeGreaterThan(0);
    });

});

describe('IS-M1: Currency tracking on JE lines', function () {

    it('IDR invoice JE lines have NULL currency fields', function () {
        $invoice = Invoice::factory()->create([
            'currency' => 'IDR',
            'exchange_rate' => 1,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 1000000,
            'line_total' => 1000000,
        ]);

        $invoice->update([
            'subtotal' => 1000000,
            'tax_amount' => 0,
            'total_amount' => 1000000,
        ]);

        $entry = $this->service->postInvoice($invoice);

        foreach ($entry->lines as $line) {
            expect($line->currency_code)->toBeNull()
                ->and($line->amount_currency)->toBeNull()
                ->and($line->exchange_rate)->toBeNull();
        }
    });

    it('USD invoice JE lines have correct currency metadata', function () {
        $exchangeRate = 15000;
        $invoice = Invoice::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => $exchangeRate,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 800,
            'line_total' => 800,
        ]);

        $invoice->update([
            'subtotal' => 800,
            'tax_amount' => 88,
            'discount_amount' => 0,
            'total_amount' => 888,
        ]);

        $entry = $this->service->postInvoice($invoice);

        $receivableAccount = Account::where('code', '1-1100')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();
        $taxAccount = Account::where('code', '2-1200')->first();

        $receivableLine = $entry->lines->firstWhere('account_id', $receivableAccount->id);
        $revenueLine = $entry->lines->firstWhere('account_id', $revenueAccount->id);
        $taxLine = $entry->lines->firstWhere('account_id', $taxAccount->id);

        // AR line tracks total invoice amount in original currency
        expect($receivableLine->currency_code)->toBe('USD')
            ->and($receivableLine->amount_currency)->toBe(888)
            ->and((float) $receivableLine->exchange_rate)->toBe(15000.0);

        // Revenue line tracks revenue in original currency
        expect($revenueLine->currency_code)->toBe('USD')
            ->and($revenueLine->amount_currency)->toBe(800)
            ->and((float) $revenueLine->exchange_rate)->toBe(15000.0);

        // Tax line tracks tax in original currency
        expect($taxLine->currency_code)->toBe('USD')
            ->and($taxLine->amount_currency)->toBe(88)
            ->and((float) $taxLine->exchange_rate)->toBe(15000.0);
    });

    it('USD bill JE lines have correct currency metadata', function () {
        $exchangeRate = 15000;
        $bill = Bill::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => $exchangeRate,
        ]);

        \App\Models\Purchasing\BillItem::factory()->create([
            'bill_id' => $bill->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'line_total' => 1000,
        ]);

        $bill->update([
            'subtotal' => 1000,
            'tax_amount' => 110,
            'discount_amount' => 0,
            'total_amount' => 1110,
        ]);

        $entry = $this->service->postBill($bill);

        $payableAccount = Account::where('code', '2-1100')->first();
        $expenseAccount = Account::where('code', '5-1002')->first();

        $payableLine = $entry->lines->firstWhere('account_id', $payableAccount->id);
        $expenseLine = $entry->lines->firstWhere('account_id', $expenseAccount->id);

        expect($payableLine->currency_code)->toBe('USD')
            ->and($payableLine->amount_currency)->toBe(1110)
            ->and((float) $payableLine->exchange_rate)->toBe(15000.0);

        expect($expenseLine->currency_code)->toBe('USD')
            ->and($expenseLine->amount_currency)->toBe(1000)
            ->and((float) $expenseLine->exchange_rate)->toBe(15000.0);
    });

    it('reversal JE preserves currency metadata from original', function () {
        $invoice = Invoice::factory()->create([
            'currency' => 'USD',
            'exchange_rate' => 15000,
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => 500,
            'line_total' => 500,
        ]);

        $invoice->update([
            'subtotal' => 500,
            'tax_amount' => 0,
            'total_amount' => 500,
        ]);

        $entry = $this->service->postInvoice($invoice);
        $reversal = $this->service->reverseEntry($entry);

        $receivableAccount = Account::where('code', '1-1100')->first();
        $reversalArLine = $reversal->lines->firstWhere('account_id', $receivableAccount->id);

        expect($reversalArLine->currency_code)->toBe('USD')
            ->and($reversalArLine->amount_currency)->toBe(500)
            ->and((float) $reversalArLine->exchange_rate)->toBe(15000.0);
    });
});
