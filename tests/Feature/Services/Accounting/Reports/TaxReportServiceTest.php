<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Services\Accounting\Reports\TaxReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new TaxReportService;
});

describe('getPpnSummary', function () {
    it('returns correct nested structure with output_tax and input_tax', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        expect($result)->toHaveKeys(['period', 'output_tax', 'input_tax', 'net_tax', 'net_tax_status', 'details'])
            ->and($result['period'])->toBe(['start' => '2024-01-01', 'end' => '2024-01-31'])
            ->and($result['output_tax'])->toHaveKeys(['count', 'base', 'tax'])
            ->and($result['output_tax']['count'])->toBe(1)
            ->and($result['output_tax']['tax'])->toEqual(110000)
            ->and($result['input_tax'])->toHaveKeys(['count', 'base', 'tax'])
            ->and($result['input_tax']['count'])->toBe(1)
            ->and($result['input_tax']['tax'])->toEqual(55000)
            ->and($result['net_tax'])->toEqual(55000)
            ->and($result['net_tax_status'])->toBe('payable');
    });

    it('calculates net_tax correctly when output greater than input', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Paid,
            'invoice_date' => '2024-02-10',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Paid,
            'bill_date' => '2024-02-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-02-01', '2024-02-29');

        expect($result['net_tax'])->toEqual(110000)
            ->and($result['net_tax_status'])->toBe('payable');
    });

    it('calculates net_tax correctly when input greater than output', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-03-10',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-03-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-03-01', '2024-03-31');

        expect($result['net_tax'])->toEqual(-55000)
            ->and($result['net_tax_status'])->toBe('refundable');
    });

    it('filters by date range correctly', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        // Outside range
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-02-15',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        expect($result['output_tax']['count'])->toBe(1)
            ->and($result['output_tax']['tax'])->toEqual(110000)
            ->and($result['input_tax']['count'])->toBe(1)
            ->and($result['input_tax']['tax'])->toEqual(55000);
    });

    it('handles empty results when no invoices or bills exist', function () {
        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        // net_tax = 0, and 0 >= 0 → 'payable'
        expect($result['output_tax']['count'])->toBe(0)
            ->and($result['output_tax']['tax'])->toEqual(0)
            ->and($result['input_tax']['count'])->toBe(0)
            ->and($result['input_tax']['tax'])->toEqual(0)
            ->and($result['net_tax'])->toEqual(0)
            ->and($result['net_tax_status'])->toBe('payable');
    });

    it('only includes invoices with valid status', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Draft,
            'invoice_date' => '2024-01-16',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Cancelled,
            'invoice_date' => '2024-01-17',
            'tax_amount' => 330000,
            'subtotal' => 3000000,
            'total_amount' => 3330000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        expect($result['output_tax']['count'])->toBe(1)
            ->and($result['output_tax']['tax'])->toEqual(110000);
    });

    it('only includes bills with valid status', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Draft,
            'bill_date' => '2024-01-16',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Cancelled,
            'bill_date' => '2024-01-17',
            'tax_amount' => 330000,
            'subtotal' => 3000000,
            'total_amount' => 3330000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        expect($result['input_tax']['count'])->toBe(1)
            ->and($result['input_tax']['tax'])->toEqual(110000);
    });

    it('only includes documents with tax_amount greater than zero', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-16',
            'tax_amount' => 0,
            'subtotal' => 1000000,
            'total_amount' => 1000000,
            'tax_rate' => 0,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-21',
            'tax_amount' => 0,
            'subtotal' => 500000,
            'total_amount' => 500000,
            'tax_rate' => 0,
        ]);

        $result = $this->service->getPpnSummary('2024-01-01', '2024-01-31');

        expect($result['output_tax']['count'])->toBe(1)
            ->and($result['output_tax']['tax'])->toEqual(110000)
            ->and($result['input_tax']['count'])->toBe(1)
            ->and($result['input_tax']['tax'])->toEqual(55000);
    });
});

describe('getMonthlyPpnSummary', function () {
    it('returns summary for all 12 months', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-06-15',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getMonthlyPpnSummary(2024);

        expect($result)->toHaveCount(12)
            ->and($result[0])->toHaveKeys(['month', 'month_name', 'output', 'input', 'net'])
            ->and($result[0]['month'])->toBe('2024-01')
            ->and($result[0]['output'])->toEqual(110000)
            ->and($result[0]['input'])->toEqual(55000)
            ->and($result[0]['net'])->toEqual(55000)
            ->and($result[5]['month'])->toBe('2024-06')
            ->and($result[5]['output'])->toEqual(220000);
    });

    it('returns zero values for months without transactions', function () {
        $result = $this->service->getMonthlyPpnSummary(2024);

        expect($result)->toHaveCount(12)
            ->and($result[0]['output'])->toEqual(0)
            ->and($result[0]['input'])->toEqual(0)
            ->and($result[0]['net'])->toEqual(0);
    });
});

describe('getTaxInvoiceList', function () {
    it('returns correct Indonesian structure and ordering', function () {
        $customer1 = Contact::factory()->customer()->create(['npwp' => '123456789']);
        $customer2 = Contact::factory()->customer()->create(['npwp' => '987654321']);

        Invoice::factory()->create([
            'contact_id' => $customer1->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer2->id,
            'status' => DocumentStatus::Paid,
            'invoice_date' => '2024-01-10',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getTaxInvoiceList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(2)
            ->and($result[0])->toHaveKeys(['tanggal', 'nomor_faktur', 'nama_pembeli', 'npwp_pembeli', 'alamat', 'dpp', 'ppn', 'total'])
            // Ordered by invoice_date ascending
            ->and($result[0]['tanggal'])->toBe('10/01/2024')
            ->and($result[1]['tanggal'])->toBe('15/01/2024');
    });

    it('filters by date range', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-02-15',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getTaxInvoiceList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(1)
            ->and($result[0]['tanggal'])->toBe('15/01/2024');
    });

    it('only includes invoices with valid status and tax_amount greater than zero', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Draft,
            'invoice_date' => '2024-01-16',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-17',
            'tax_amount' => 0,
            'subtotal' => 1000000,
            'total_amount' => 1000000,
            'tax_rate' => 0,
        ]);

        $result = $this->service->getTaxInvoiceList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(1);
    });

    it('returns empty collection when no invoices exist', function () {
        $result = $this->service->getTaxInvoiceList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(0);
    });
});

describe('getInputTaxList', function () {
    it('returns correct Indonesian structure and ordering', function () {
        $vendor1 = Contact::factory()->vendor()->create(['npwp' => '123456789']);
        $vendor2 = Contact::factory()->vendor()->create(['npwp' => '987654321']);

        Bill::factory()->create([
            'contact_id' => $vendor1->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
            'vendor_invoice_number' => 'VIN-001',
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor2->id,
            'status' => DocumentStatus::Paid,
            'bill_date' => '2024-01-10',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
            'vendor_invoice_number' => 'VIN-002',
        ]);

        $result = $this->service->getInputTaxList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(2)
            ->and($result[0])->toHaveKeys(['tanggal', 'nomor_faktur_vendor', 'nomor_internal', 'nama_penjual', 'npwp_penjual', 'dpp', 'ppn', 'total'])
            // Ordered by bill_date ascending
            ->and($result[0]['tanggal'])->toBe('10/01/2024')
            ->and($result[1]['tanggal'])->toBe('15/01/2024');
    });

    it('filters by date range', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-02-15',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getInputTaxList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(1)
            ->and($result[0]['tanggal'])->toBe('15/01/2024');
    });

    it('only includes bills with valid status and tax_amount greater than zero', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Draft,
            'bill_date' => '2024-01-16',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-17',
            'tax_amount' => 0,
            'subtotal' => 1000000,
            'total_amount' => 1000000,
            'tax_rate' => 0,
        ]);

        $result = $this->service->getInputTaxList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(1);
    });

    it('returns empty collection when no bills exist', function () {
        $result = $this->service->getInputTaxList('2024-01-01', '2024-01-31');

        expect($result)->toHaveCount(0);
    });
});

describe('getMonthlyPpn', function () {
    it('returns correct structure with period, output_tax invoices, input_tax bills', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-01-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-01-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getMonthlyPpn(1, 2024);

        expect($result)->toHaveKeys(['period', 'output_tax', 'input_tax'])
            ->and($result['period'])->toBe(['month' => 1, 'year' => 2024])
            ->and($result['output_tax'])->toHaveKey('invoices')
            ->and($result['output_tax']['invoices'])->toHaveCount(1)
            ->and($result['input_tax'])->toHaveKey('bills')
            ->and($result['input_tax']['bills'])->toHaveCount(1);
    });

    it('returns invoices and bills with correct keys', function () {
        $customer = Contact::factory()->customer()->create(['npwp' => '123456789']);
        $vendor = Contact::factory()->vendor()->create(['npwp' => '987654321']);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-02-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-02-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
            'vendor_invoice_number' => 'VIN-001',
        ]);

        $result = $this->service->getMonthlyPpn(2, 2024);

        expect($result['output_tax']['invoices'])->toHaveCount(1)
            ->and($result['output_tax']['invoices'][0])->toHaveKeys(['invoice_number', 'date', 'contact', 'npwp', 'subtotal', 'tax_amount'])
            ->and($result['input_tax']['bills'])->toHaveCount(1)
            ->and($result['input_tax']['bills'][0])->toHaveKeys(['bill_number', 'date', 'contact', 'npwp', 'subtotal', 'tax_amount']);
    });

    it('returns empty collections when no transactions exist', function () {
        $result = $this->service->getMonthlyPpn(3, 2024);

        expect($result['output_tax']['invoices'])->toHaveCount(0)
            ->and($result['input_tax']['bills'])->toHaveCount(0);
    });

    it('only includes transactions from specified month', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-04-15',
            'tax_amount' => 110000,
            'subtotal' => 1000000,
            'total_amount' => 1110000,
            'tax_rate' => 11,
        ]);

        // Outside range
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => '2024-05-15',
            'tax_amount' => 220000,
            'subtotal' => 2000000,
            'total_amount' => 2220000,
            'tax_rate' => 11,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'bill_date' => '2024-04-20',
            'tax_amount' => 55000,
            'subtotal' => 500000,
            'total_amount' => 555000,
            'tax_rate' => 11,
        ]);

        $result = $this->service->getMonthlyPpn(4, 2024);

        expect($result['output_tax']['invoices'])->toHaveCount(1)
            ->and($result['input_tax']['bills'])->toHaveCount(1);
    });
});
