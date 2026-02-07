<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Tax\NsfpRange;
use App\Models\User;
use App\Services\Tax\EfakturExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = new EfakturExportService;
});

describe('EfakturExportService', function () {
    it('generates CSV with FK and OF rows', function () {
        $contact = Contact::factory()->customer()->create([
            'npwp' => '01.234.567.8-901.000',
        ]);

        $range = NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2), 'items')
            ->sent()
            ->create([
                'contact_id' => $contact->id,
                'invoice_date' => '2026-01-15',
                'nsfp_number' => '010.000-26.00000001',
                'nsfp_range_id' => $range->id,
                'nsfp_assigned_at' => now(),
                'subtotal' => 1000000,
                'tax_amount' => 110000,
            ]);

        $csv = $this->service->generateCsv('2026-01-01', '2026-01-31');

        expect($csv)->not->toBeEmpty();

        $lines = explode("\n", $csv);
        // 1 FK row + 2 OF rows = 3 lines
        expect($lines)->toHaveCount(3);
        expect($lines[0])->toStartWith('FK,');
        expect($lines[1])->toStartWith('OF,');
        expect($lines[2])->toStartWith('OF,');
    });

    it('includes cancelled invoices with cancel flag', function () {
        $contact = Contact::factory()->customer()->create();
        $range = NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->create([
                'contact_id' => $contact->id,
                'invoice_date' => '2026-01-15',
                'status' => DocumentStatus::Cancelled,
                'nsfp_number' => '010.000-26.00000005',
                'nsfp_range_id' => $range->id,
                'nsfp_assigned_at' => now(),
                'is_nsfp_cancelled' => true,
            ]);

        $csv = $this->service->generateCsv('2026-01-01', '2026-01-31');

        expect($csv)->not->toBeEmpty();

        $lines = explode("\n", $csv);
        // Only FK row for cancelled (no OF rows)
        expect($lines)->toHaveCount(1);
        expect($lines[0])->toContain(',1,'); // is_cancelled flag = 1
    });

    it('returns empty string for period with no NSFP invoices', function () {
        $csv = $this->service->generateCsv('2026-06-01', '2026-06-30');

        expect($csv)->toBe('');
    });

    it('excludes invoices without NSFP numbers', function () {
        Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create([
                'invoice_date' => '2026-01-15',
                'nsfp_number' => null,
            ]);

        $csv = $this->service->generateCsv('2026-01-01', '2026-01-31');

        expect($csv)->toBe('');
    });

    it('strips NPWP formatting characters', function () {
        $contact = Contact::factory()->customer()->create([
            'npwp' => '01.234.567.8-901.000',
        ]);
        $range = NsfpRange::factory()->withRange(1, 1000)->forYear('26')->create();

        Invoice::factory()
            ->has(InvoiceItem::factory(), 'items')
            ->sent()
            ->create([
                'contact_id' => $contact->id,
                'invoice_date' => '2026-01-15',
                'nsfp_number' => '010.000-26.00000001',
                'nsfp_range_id' => $range->id,
                'nsfp_assigned_at' => now(),
            ]);

        $csv = $this->service->generateCsv('2026-01-01', '2026-01-31');
        $fkRow = explode("\n", $csv)[0];

        // NPWP should be stripped of dots and dashes
        expect($fkRow)->toContain('012345678901000');
    });
});
