<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\JournalEntryLine;
use App\Models\Accounting\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Cash Flow Statement', function () {

    it('can generate cash flow statement', function () {
        $response = $this->getJson('/api/v1/reports/cash-flow');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'operating' => ['items', 'subtotal'],
                'investing' => ['items', 'subtotal'],
                'financing' => ['items', 'subtotal'],
                'net_cash_flow',
                'beginning_cash',
                'ending_cash',
            ])
            ->assertJsonPath('report_name', 'Laporan Arus Kas');
    });

    it('can filter by date range', function () {
        $response = $this->getJson('/api/v1/reports/cash-flow?start_date=2024-01-01&end_date=2024-12-31');

        $response->assertOk()
            ->assertJsonPath('period.start', '2024-01-01')
            ->assertJsonPath('period.end', '2024-12-31');
    });

    it('shows operating activities from customer payments', function () {
        $customer = Contact::factory()->customer()->create();
        $cashAccount = Account::where('code', '1-1001')->first();

        $invoice = Invoice::factory()
            ->forContact($customer)
            ->sent()
            ->create([
                'total_amount' => 10000000,
                'paid_amount' => 0,
            ]);

        // Receive payment from customer
        Payment::factory()
            ->forInvoice($invoice)
            ->forAccount($cashAccount)
            ->create([
                'amount' => 10000000,
                'payment_date' => now()->toDateString(),
            ]);

        $response = $this->getJson('/api/v1/reports/cash-flow?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();

        $operating = $response->json('operating');
        expect($operating['subtotal'])->toBeGreaterThan(0);

        // Should have customer receipts
        $customerReceipts = collect($operating['items'])->firstWhere('description', 'Penerimaan dari pelanggan');
        expect($customerReceipts)->not->toBeNull();
        expect($customerReceipts['amount'])->toBe(10000000);
    });

    it('shows operating activities from supplier payments', function () {
        $supplier = Contact::factory()->supplier()->create();
        $cashAccount = Account::where('code', '1-1001')->first();

        $bill = Bill::factory()
            ->forContact($supplier)
            ->create([
                'status' => Bill::STATUS_RECEIVED,
                'total_amount' => 5000000,
                'paid_amount' => 0,
            ]);

        // Pay supplier
        Payment::factory()
            ->forBill($bill)
            ->forAccount($cashAccount)
            ->create([
                'amount' => 5000000,
                'payment_date' => now()->toDateString(),
            ]);

        $response = $this->getJson('/api/v1/reports/cash-flow?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();

        $operating = $response->json('operating');

        // Should have supplier payments (negative)
        $supplierPayments = collect($operating['items'])->firstWhere('description', 'Pembayaran ke pemasok');
        expect($supplierPayments)->not->toBeNull();
        expect($supplierPayments['amount'])->toBe(-5000000);
    });

    it('calculates net cash flow correctly', function () {
        $customer = Contact::factory()->customer()->create();
        $supplier = Contact::factory()->supplier()->create();
        $cashAccount = Account::where('code', '1-1001')->first();

        // Receive from customer
        $invoice = Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forInvoice($invoice)->forAccount($cashAccount)->create([
            'amount' => 10000000,
            'payment_date' => now()->toDateString(),
        ]);

        // Pay to supplier
        $bill = Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'total_amount' => 3000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forBill($bill)->forAccount($cashAccount)->create([
            'amount' => 3000000,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/reports/cash-flow?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();

        $netCashFlow = $response->json('net_cash_flow');
        $operating = $response->json('operating.subtotal');

        // Net should be receipts - payments = 10M - 3M = 7M
        expect($operating)->toBe(7000000);
    });

    it('calculates beginning and ending cash correctly', function () {
        $cashAccount = Account::where('code', '1-1001')->first();
        $revenueAccount = Account::where('code', '4-1001')->first();

        // Create prior period cash transaction
        $priorEntry = JournalEntry::factory()->posted()->create([
            'entry_date' => now()->subMonth()->toDateString(),
        ]);
        JournalEntryLine::factory()->forEntry($priorEntry)->forAccount($cashAccount)->debit(5000000)->create();
        JournalEntryLine::factory()->forEntry($priorEntry)->forAccount($revenueAccount)->credit(5000000)->create();

        $response = $this->getJson('/api/v1/reports/cash-flow?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertOk();

        $beginningCash = $response->json('beginning_cash');
        $endingCash = $response->json('ending_cash');
        $netCashFlow = $response->json('net_cash_flow');

        // Beginning cash should include prior period
        expect($beginningCash)->toBe(5000000);

        // Ending = Beginning + Net
        expect($endingCash)->toBe($beginningCash + $netCashFlow);
    });

});

describe('Daily Cash Movement', function () {

    it('can get daily cash movement', function () {
        $response = $this->getJson('/api/v1/reports/daily-cash-movement');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'period' => ['start', 'end'],
                'movements',
                'total_receipts',
                'total_payments',
                'net_movement',
            ])
            ->assertJsonPath('report_name', 'Pergerakan Kas Harian');
    });

    it('can filter by date range', function () {
        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $response = $this->getJson("/api/v1/reports/daily-cash-movement?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk()
            ->assertJsonPath('period.start', $startDate)
            ->assertJsonPath('period.end', $endDate);
    });

    it('shows daily running balance', function () {
        $customer = Contact::factory()->customer()->create();
        $cashAccount = Account::where('code', '1-1001')->first();

        // Create payments on different days
        $invoice1 = Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forInvoice($invoice1)->forAccount($cashAccount)->create([
            'amount' => 5000000,
            'payment_date' => now()->startOfMonth()->addDays(5)->toDateString(),
        ]);

        $invoice2 = Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 3000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forInvoice($invoice2)->forAccount($cashAccount)->create([
            'amount' => 3000000,
            'payment_date' => now()->startOfMonth()->addDays(10)->toDateString(),
        ]);

        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->startOfMonth()->addDays(15)->toDateString();

        $response = $this->getJson("/api/v1/reports/daily-cash-movement?start_date={$startDate}&end_date={$endDate}");

        $response->assertOk();

        $movements = collect($response->json('movements'));

        // Find day 5 and day 10
        $day5 = $movements->firstWhere('date', now()->startOfMonth()->addDays(5)->toDateString());
        $day10 = $movements->firstWhere('date', now()->startOfMonth()->addDays(10)->toDateString());

        expect($day5['receipts'])->toBe(5000000);
        expect($day10['receipts'])->toBe(3000000);

        // Running balance should accumulate
        expect($day10['balance'])->toBeGreaterThan($day5['balance']);
    });

    it('shows correct structure for each day', function () {
        $response = $this->getJson('/api/v1/reports/daily-cash-movement?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk();

        $movements = $response->json('movements');
        expect($movements)->toHaveCount(1);

        $movement = $movements[0];
        expect($movement)->toHaveKeys(['date', 'receipts', 'payments', 'net', 'balance']);
    });

    it('calculates totals correctly', function () {
        $customer = Contact::factory()->customer()->create();
        $supplier = Contact::factory()->supplier()->create();
        $cashAccount = Account::where('code', '1-1001')->first();

        // Receipts
        $invoice = Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 10000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forInvoice($invoice)->forAccount($cashAccount)->create([
            'amount' => 10000000,
            'payment_date' => now()->toDateString(),
        ]);

        // Payments
        $bill = Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'total_amount' => 4000000,
            'paid_amount' => 0,
        ]);
        Payment::factory()->forBill($bill)->forAccount($cashAccount)->create([
            'amount' => 4000000,
            'payment_date' => now()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/reports/daily-cash-movement?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $response->assertOk()
            ->assertJsonPath('total_receipts', 10000000)
            ->assertJsonPath('total_payments', 4000000)
            ->assertJsonPath('net_movement', 6000000);
    });

});
