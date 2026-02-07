<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Purchasing\Bill;
use App\Models\Sales\Invoice;
use App\Models\User;
use App\Services\Sales\OverdueService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->service = app(OverdueService::class);
});

describe('OverdueService mark overdue', function () {
    it('marks overdue invoices', function () {
        $customer = Contact::factory()->customer()->create();

        // Create overdue invoice
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->subDays(10),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        // Create non-overdue invoice
        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->addDays(10),
            'total_amount' => 500000,
            'paid_amount' => 0,
        ]);

        $marked = $this->service->markOverdueInvoices();

        expect($marked)->toHaveCount(1);
        expect(Invoice::where('status', DocumentStatus::Overdue)->count())->toBe(1);
    });

    it('marks overdue bills', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->subDays(15),
            'total_amount' => 2000000,
            'paid_amount' => 0,
        ]);

        $marked = $this->service->markOverdueBills();

        expect($marked)->toHaveCount(1);
    });

    it('marks all overdue documents', function () {
        $customer = Contact::factory()->customer()->create();
        $vendor = Contact::factory()->vendor()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->subDays(10),
        ]);

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->subDays(10),
        ]);

        $result = $this->service->markAllOverdue();

        expect($result)->toHaveKeys(['invoices', 'bills']);
        expect($result['invoices'])->toHaveCount(1);
        expect($result['bills'])->toHaveCount(1);
    });
});

describe('OverdueService reports', function () {
    it('gets overdue summary', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Overdue,
            'total_amount' => 1000000,
            'paid_amount' => 200000,
        ]);

        $summary = $this->service->getOverdueSummary();

        expect($summary)->toHaveKeys(['invoices', 'bills']);
        expect($summary['invoices']['count'])->toBe(1);
        expect($summary['invoices']['total'])->toBe(800000);
    });

    it('gets upcoming due invoices', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->addDays(5),
        ]);

        Invoice::factory()->create([
            'contact_id' => $customer->id,
            'status' => DocumentStatus::Sent,
            'due_date' => now()->addDays(15), // Outside 7-day window
        ]);

        $upcoming = $this->service->getUpcomingDueInvoices(7);

        expect($upcoming)->toHaveCount(1);
    });

    it('gets upcoming due bills', function () {
        $vendor = Contact::factory()->vendor()->create();

        Bill::factory()->create([
            'contact_id' => $vendor->id,
            'status' => DocumentStatus::Received,
            'due_date' => now()->addDays(3),
        ]);

        $upcoming = $this->service->getUpcomingDueBills(7);

        expect($upcoming)->toHaveCount(1);
    });
});
