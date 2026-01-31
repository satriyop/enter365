<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Accounting\Reports\AgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = new AgingReportService;

    // Set aging buckets config
    config(['accounting.aging_buckets' => [
        ['label' => 'Current', 'min' => 0, 'max' => 0],
        ['label' => '1-30 days', 'min' => 1, 'max' => 30],
        ['label' => '31-60 days', 'min' => 31, 'max' => 60],
        ['label' => '60+ days', 'min' => 61, 'max' => null],
    ]]);
});

describe('AgingReportService receivable aging', function () {
    it('generates receivable aging report', function () {
        $customer = Contact::factory()->customer()->create();

        // Current
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now(),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        // 15 days overdue
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->subDays(15),
            'total_amount' => 500000,
            'paid_amount' => 0,
        ]);

        $report = $this->service->getReceivableAging();

        expect($report)->toHaveKeys(['as_of_date', 'buckets', 'contacts', 'totals']);
        expect($report['contacts'])->toHaveCount(1);
        expect($report['totals']['total'])->toBe(1500000);
    });

    it('handles empty results', function () {
        $report = $this->service->getReceivableAging();

        expect($report['contacts'])->toHaveCount(0);
        expect($report['totals']['total'])->toBe(0);
    });

    it('filters by as of date', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'invoice_date' => now()->subDays(60),
            'due_date' => now()->subDays(30),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        // Should include invoice dated before cutoff
        $report = $this->service->getReceivableAging(now()->subDays(50));
        expect($report['contacts'])->toHaveCount(1);

        // Should not include invoice dated after cutoff
        $report = $this->service->getReceivableAging(now()->subDays(70));
        expect($report['contacts'])->toHaveCount(0);
    });
});

describe('AgingReportService payable aging', function () {
    it('generates payable aging report', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->subDays(20),
            'total_amount' => 2000000,
            'paid_amount' => 500000,
        ]);

        $report = $this->service->getPayableAging();

        expect($report)->toHaveKeys(['as_of_date', 'buckets', 'contacts', 'totals']);
        expect($report['contacts'])->toHaveCount(1);
        expect($report['totals']['total'])->toBe(1500000);
    });

    it('groups by contact', function () {
        $vendor1 = Contact::factory()->vendor()->create();
        $vendor2 = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor1->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->subDays(10),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor2->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->subDays(10),
            'total_amount' => 500000,
            'paid_amount' => 0,
        ]);

        $report = $this->service->getPayableAging();

        expect($report['contacts'])->toHaveCount(2);
    });
});

describe('AgingReportService contact aging', function () {
    it('gets aging for specific contact', function () {
        $contact = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $contact->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->subDays(10),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $aging = $this->service->getContactAging($contact);

        expect($aging)->toHaveKeys(['receivable', 'payable']);
        expect($aging['receivable']['buckets']['total'])->toBe(1000000);
        expect($aging['receivable']['invoice_count'])->toBe(1);
    });
});
