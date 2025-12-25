<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Export API', function () {

    it('can export trial balance as CSV', function () {
        $response = $this->get('/api/v1/export/trial-balance?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export trial balance as JSON', function () {
        $response = $this->getJson('/api/v1/export/trial-balance?format=json');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'headers',
            ]);
    });

    it('can export balance sheet', function () {
        $response = $this->get('/api/v1/export/balance-sheet?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export income statement', function () {
        $response = $this->get('/api/v1/export/income-statement?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export general ledger', function () {
        $account = Account::first();

        $response = $this->get("/api/v1/export/general-ledger?account_id={$account->id}&format=csv");

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('requires account_id for general ledger export', function () {
        $response = $this->get('/api/v1/export/general-ledger?format=csv');

        $response->assertUnprocessable();
    });

    it('can export receivable aging', function () {
        $customer = Contact::factory()->customer()->create();
        Invoice::factory()->forContact($customer)->sent()->create();

        $response = $this->get('/api/v1/export/receivable-aging?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export payable aging', function () {
        $supplier = Contact::factory()->supplier()->create();
        Bill::factory()->forContact($supplier)->create(['status' => 'received']);

        $response = $this->get('/api/v1/export/payable-aging?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export invoices list', function () {
        Invoice::factory()->count(5)->create();

        $response = $this->get('/api/v1/export/invoices?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export invoices with date filter', function () {
        Invoice::factory()->create(['invoice_date' => now()]);
        Invoice::factory()->create(['invoice_date' => now()->subMonth()]);

        $startDate = now()->startOfMonth()->toDateString();
        $endDate = now()->endOfMonth()->toDateString();

        $response = $this->get("/api/v1/export/invoices?start_date={$startDate}&end_date={$endDate}&format=csv");

        $response->assertOk();
    });

    it('can export invoices with status filter', function () {
        Invoice::factory()->draft()->count(2)->create();
        Invoice::factory()->sent()->count(3)->create();

        $response = $this->get('/api/v1/export/invoices?status=sent&format=csv');

        $response->assertOk();
    });

    it('can export bills list', function () {
        Bill::factory()->count(5)->create();

        $response = $this->get('/api/v1/export/bills?format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('can export tax report', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'tax_amount' => 110000,
        ]);

        $response = $this->get('/api/v1/export/tax-report?month=' . now()->month . '&year=' . now()->year . '&format=csv');

        $response->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    });

    it('includes correct headers in CSV export', function () {
        $response = $this->get('/api/v1/export/trial-balance?format=csv');

        $response->assertOk();

        $content = $response->getContent();
        // Check that Indonesian headers are present
        expect($content)->toContain('Kode');
        expect($content)->toContain('Nama Akun');
        expect($content)->toContain('Debit');
        expect($content)->toContain('Kredit');
    });

    it('includes proper filename in Content-Disposition header', function () {
        $response = $this->get('/api/v1/export/trial-balance?format=csv');

        $response->assertOk();

        $contentDisposition = $response->headers->get('Content-Disposition');
        expect($contentDisposition)->toContain('trial-balance');
        expect($contentDisposition)->toContain('.csv');
    });
});
