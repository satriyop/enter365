<?php

declare(strict_types=1);

use App\Contracts\Accounting\JournalServiceInterface;
use App\Models\Accounting\Account;
use App\Models\Accounting\JournalEntry;
use App\Models\Purchasing\Bill;
use App\Models\Purchasing\PurchaseReturn;
use App\Models\Purchasing\PurchaseReturnItem;
use App\Models\Sales\Invoice;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Services\Accounting\Strategies\Returns\FullReturnJournalStrategy;
use App\Services\Accounting\Strategies\Returns\InventoryOnlyReturnStrategy;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\FiscalPeriodSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    authenticatedAdmin();
    $this->seed([
        ChartOfAccountsSeeder::class,
        FiscalPeriodSeeder::class,
    ]);
});

describe('FullReturnJournalStrategy', function () {
    beforeEach(function () {
        $this->journalService = Mockery::mock(JournalServiceInterface::class);
        app()->instance(JournalServiceInterface::class, $this->journalService);
        $this->strategy = new FullReturnJournalStrategy($this->journalService);
    });

    describe('onSalesReturnApprove', function () {
        it('creates journal entry with correct debits and credits for sales return with tax', function () {
            $invoice = Invoice::factory()->create([
                'receivable_account_id' => Account::where('code', '1-1100')->first()->id,
            ]);

            $salesReturn = SalesReturn::factory()
                ->forInvoice($invoice)
                ->withTotals(100000)
                ->create([
                    'subtotal' => 100000,
                    'tax_rate' => 11,
                    'tax_amount' => 11000,
                    'total_amount' => 111000,
                    'return_date' => now(),
                    'return_number' => 'SR-202402-0001',
                ]);

            SalesReturnItem::factory()->create(['sales_return_id' => $salesReturn->id]);

            $expectedEntry = new JournalEntry(['id' => 1]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['entry_date'])->toBe($data['entry_date'])
                        ->and($data['reference'])->toBe('SR-202402-0001')
                        ->and($data['source_type'])->toBe(SalesReturn::class)
                        ->and($data['lines'])->toHaveCount(3)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('4-1004')
                        ->and($lines[0]['debit'])->toBe(100000)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('Retur penjualan');

                    expect($lines[1]['account_code'])->toBe('2-1200')
                        ->and($lines[1]['debit'])->toBe(11000)
                        ->and($lines[1]['credit'])->toBe(0)
                        ->and($lines[1]['description'])->toContain('Reversal PPN Keluaran');

                    expect($lines[2]['account_code'])->toBe('1-1100')
                        ->and($lines[2]['debit'])->toBe(0)
                        ->and($lines[2]['credit'])->toBe(111000)
                        ->and($lines[2]['description'])->toContain('Pengurangan piutang');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onSalesReturnApprove($salesReturn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('creates journal entry without tax line when tax amount is zero', function () {
            $invoice = Invoice::factory()->create([
                'receivable_account_id' => Account::where('code', '1-1100')->first()->id,
            ]);

            $salesReturn = SalesReturn::factory()
                ->forInvoice($invoice)
                ->create([
                    'subtotal' => 100000,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 100000,
                    'return_date' => now(),
                    'return_number' => 'SR-202402-0002',
                ]);

            $expectedEntry = new JournalEntry(['id' => 2]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) {
                    expect($data['lines'])->toHaveCount(2);

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('4-1004')
                        ->and($lines[0]['debit'])->toBe(100000);

                    expect($lines[1]['account_code'])->toBe('1-1100')
                        ->and($lines[1]['credit'])->toBe(100000);

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onSalesReturnApprove($salesReturn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
        });

        it('uses default receivable account when invoice has no custom account', function () {
            $invoice = Invoice::factory()->create(['receivable_account_id' => null]);

            $salesReturn = SalesReturn::factory()
                ->forInvoice($invoice)
                ->withTotals(100000)
                ->create();

            $expectedEntry = new JournalEntry(['id' => 3]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) {
                    $lines = $data['lines'];
                    $creditLine = collect($lines)->firstWhere('credit', '>', 0);

                    expect($creditLine['account_code'])->toBe('1-1100');

                    return true;
                })
                ->andReturn($expectedEntry);

            $this->strategy->onSalesReturnApprove($salesReturn);
        });
    });

    describe('onPurchaseReturnApprove', function () {
        it('creates journal entry with correct debits and credits for purchase return with tax', function () {
            $bill = Bill::factory()->create([
                'payable_account_id' => Account::where('code', '2-1100')->first()->id,
            ]);

            $purchaseReturn = PurchaseReturn::factory()
                ->forBill($bill)
                ->withTotals(200000)
                ->create([
                    'subtotal' => 200000,
                    'tax_rate' => 11,
                    'tax_amount' => 22000,
                    'total_amount' => 222000,
                    'return_date' => now(),
                    'return_number' => 'PR-202402-0001',
                ]);

            PurchaseReturnItem::factory()->create(['purchase_return_id' => $purchaseReturn->id]);

            $expectedEntry = new JournalEntry(['id' => 4]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) {
                    expect($data)->toHaveKeys(['entry_date', 'description', 'reference', 'source_type', 'source_id', 'lines'])
                        ->and($data['reference'])->toBe('PR-202402-0001')
                        ->and($data['source_type'])->toBe(PurchaseReturn::class)
                        ->and($data['lines'])->toHaveCount(3)
                        ->and($autoPost)->toBeTrue();

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('2-1100')
                        ->and($lines[0]['debit'])->toBe(222000)
                        ->and($lines[0]['credit'])->toBe(0)
                        ->and($lines[0]['description'])->toContain('Pengurangan utang');

                    expect($lines[1]['account_code'])->toBe('5-1004')
                        ->and($lines[1]['debit'])->toBe(0)
                        ->and($lines[1]['credit'])->toBe(200000)
                        ->and($lines[1]['description'])->toContain('Retur pembelian');

                    expect($lines[2]['account_code'])->toBe('1-1300')
                        ->and($lines[2]['debit'])->toBe(0)
                        ->and($lines[2]['credit'])->toBe(22000)
                        ->and($lines[2]['description'])->toContain('Reversal PPN Masukan');

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onPurchaseReturnApprove($purchaseReturn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
            $this->journalService->shouldHaveReceived('createEntry')->once();
        });

        it('creates journal entry without tax line when tax amount is zero', function () {
            $bill = Bill::factory()->create([
                'payable_account_id' => Account::where('code', '2-1100')->first()->id,
            ]);

            $purchaseReturn = PurchaseReturn::factory()
                ->forBill($bill)
                ->create([
                    'subtotal' => 150000,
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'total_amount' => 150000,
                    'return_date' => now(),
                    'return_number' => 'PR-202402-0002',
                ]);

            $expectedEntry = new JournalEntry(['id' => 5]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data, $autoPost) {
                    expect($data['lines'])->toHaveCount(2);

                    $lines = $data['lines'];

                    expect($lines[0]['account_code'])->toBe('2-1100')
                        ->and($lines[0]['debit'])->toBe(150000);

                    expect($lines[1]['account_code'])->toBe('5-1004')
                        ->and($lines[1]['credit'])->toBe(150000);

                    return true;
                })
                ->andReturn($expectedEntry);

            $result = $this->strategy->onPurchaseReturnApprove($purchaseReturn);

            expect($result)->toBeInstanceOf(JournalEntry::class);
        });

        it('uses default payable account when bill has no custom account', function () {
            $bill = Bill::factory()->create(['payable_account_id' => null]);

            $purchaseReturn = PurchaseReturn::factory()
                ->forBill($bill)
                ->withTotals(100000)
                ->create();

            $expectedEntry = new JournalEntry(['id' => 6]);

            $this->journalService
                ->shouldReceive('createEntry')
                ->once()
                ->withArgs(function ($data) {
                    $lines = $data['lines'];
                    $debitLine = collect($lines)->firstWhere('debit', '>', 0);

                    expect($debitLine['account_code'])->toBe('2-1100');

                    return true;
                })
                ->andReturn($expectedEntry);

            $this->strategy->onPurchaseReturnApprove($purchaseReturn);
        });
    });

    describe('getIdentifier', function () {
        it('returns full_journal as identifier', function () {
            expect($this->strategy->getIdentifier())->toBe('full_journal');
        });
    });
});

describe('InventoryOnlyReturnStrategy', function () {
    beforeEach(function () {
        $this->strategy = new InventoryOnlyReturnStrategy;
    });

    describe('onSalesReturnApprove', function () {
        it('returns null for sales return', function () {
            $salesReturn = SalesReturn::factory()
                ->withTotals(100000)
                ->create();

            $result = $this->strategy->onSalesReturnApprove($salesReturn);

            expect($result)->toBeNull();
        });
    });

    describe('onPurchaseReturnApprove', function () {
        it('returns null for purchase return', function () {
            $purchaseReturn = PurchaseReturn::factory()
                ->withTotals(200000)
                ->create();

            $result = $this->strategy->onPurchaseReturnApprove($purchaseReturn);

            expect($result)->toBeNull();
        });
    });

    describe('getIdentifier', function () {
        it('returns inventory_only as identifier', function () {
            expect($this->strategy->getIdentifier())->toBe('inventory_only');
        });
    });
});
