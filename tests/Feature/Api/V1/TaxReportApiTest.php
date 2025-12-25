<?php

use App\Models\Accounting\Bill;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Tax Report API (PPN)', function () {

    it('can get PPN summary', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();
        $supplier = Contact::factory()->supplier()->withNpwp()->create();

        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'subtotal' => 10000000,
            'tax_amount' => 1100000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'bill_date' => now(),
            'subtotal' => 5000000,
            'tax_amount' => 550000,
            'tax_rate' => 11,
        ]);

        $response = $this->getJson('/api/v1/reports/ppn-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'period',
                'output_tax',
                'input_tax',
                'net_tax',
            ]);

        // Output tax (PPN Keluaran) from invoices
        expect($response->json('output_tax.tax'))->toBe(1100000);
        // Input tax (PPN Masukan) from bills
        expect($response->json('input_tax.tax'))->toBe(550000);
        // Net = 1,100,000 - 550,000 = 550,000
        expect($response->json('net_tax'))->toBe(550000);
    });

    it('can get monthly PPN report', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'subtotal' => 10000000,
            'tax_amount' => 1100000,
        ]);

        $response = $this->getJson('/api/v1/reports/ppn-monthly?year=' . now()->year);

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'year',
                'months',
                'total_output',
                'total_input',
                'total_net',
            ]);
    });

    it('can get tax invoice list (Faktur Keluaran)', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        Invoice::factory()->forContact($customer)->sent()->count(3)->create([
            'invoice_date' => now(),
            'tax_amount' => 110000,
        ]);

        $response = $this->getJson('/api/v1/reports/tax-invoice-list');

        $response->assertOk()
            ->assertJsonCount(3, 'invoices');
    });

    it('can get input tax list (Faktur Masukan)', function () {
        $supplier = Contact::factory()->supplier()->withNpwp()->create();

        Bill::factory()->forContact($supplier)->count(3)->create([
            'status' => Bill::STATUS_RECEIVED,
            'bill_date' => now(),
            'tax_amount' => 110000,
        ]);

        $response = $this->getJson('/api/v1/reports/input-tax-list');

        $response->assertOk()
            ->assertJsonCount(3, 'bills');
    });

    it('filters tax reports by date range', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        // Invoice this month
        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'tax_amount' => 110000,
        ]);

        // Invoice last month
        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now()->subMonth(),
            'tax_amount' => 220000,
        ]);

        $response = $this->getJson('/api/v1/reports/ppn-summary?start_date=' . now()->startOfMonth()->toDateString() . '&end_date=' . now()->endOfMonth()->toDateString());

        $response->assertOk()
            ->assertJsonPath('output_tax.tax', 110000);
    });

    it('excludes draft invoices from tax reports', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        // Draft invoice should not appear
        Invoice::factory()->forContact($customer)->draft()->create([
            'invoice_date' => now(),
            'tax_amount' => 110000,
        ]);

        // Sent invoice should appear
        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'tax_amount' => 220000,
        ]);

        $response = $this->getJson('/api/v1/reports/ppn-summary');

        $response->assertOk()
            ->assertJsonPath('output_tax.tax', 220000);
    });

    it('includes NPWP in tax invoice list', function () {
        $customer = Contact::factory()->customer()->create([
            'npwp' => '12.345.678.9-012.345',
        ]);

        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'tax_amount' => 110000,
        ]);

        $response = $this->getJson('/api/v1/reports/tax-invoice-list');

        $response->assertOk();

        $invoice = $response->json('invoices.0');
        expect($invoice['npwp_pembeli'])->toBe('12.345.678.9-012.345');
    });

    it('calculates 11% PPN correctly', function () {
        $customer = Contact::factory()->customer()->withNpwp()->create();

        // Invoice with subtotal 10,000,000 should have 1,100,000 tax
        Invoice::factory()->forContact($customer)->sent()->create([
            'invoice_date' => now(),
            'subtotal' => 10000000,
            'tax_amount' => 1100000,
            'tax_rate' => 11,
            'total_amount' => 11100000,
        ]);

        $response = $this->getJson('/api/v1/reports/ppn-summary');

        $response->assertOk()
            ->assertJsonPath('output_tax.tax', 1100000);
    });
});
