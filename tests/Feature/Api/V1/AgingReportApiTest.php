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

describe('Aging Report API', function () {

    it('can get receivable aging report', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->forContact($customer)->sent()->create([
            'due_date' => now()->addDays(15), // current
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/reports/receivable-aging');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'as_of_date',
                'buckets',
                'contacts',
                'totals',
            ]);
    });

    it('can get payable aging report', function () {
        $supplier = Contact::factory()->supplier()->create();

        Bill::factory()->forContact($supplier)->create([
            'status' => Bill::STATUS_RECEIVED,
            'due_date' => now()->addDays(15), // current
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/reports/payable-aging');

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'as_of_date',
                'buckets',
                'contacts',
                'totals',
            ]);
    });

    it('categorizes invoices into correct aging buckets', function () {
        $customer = Contact::factory()->customer()->create();

        // Current (not yet due)
        Invoice::factory()->forContact($customer)->sent()->create([
            'due_date' => now()->addDays(5),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        // 1-30 days overdue
        Invoice::factory()->forContact($customer)->overdue()->create([
            'due_date' => now()->subDays(15),
            'total_amount' => 2000000,
            'paid_amount' => 0,
        ]);

        // 31-60 days overdue
        Invoice::factory()->forContact($customer)->overdue()->create([
            'due_date' => now()->subDays(45),
            'total_amount' => 3000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/reports/receivable-aging');

        $response->assertOk();

        $totals = $response->json('totals');
        // bucket_0 = current, bucket_1 = 1-30, bucket_2 = 31-60
        expect($totals['bucket_0'])->toBe(1000000);
        expect($totals['bucket_1'])->toBe(2000000);
        expect($totals['bucket_2'])->toBe(3000000);
        expect($totals['total'])->toBe(6000000);
    });

    it('groups aging by contact', function () {
        $customer1 = Contact::factory()->customer()->create(['name' => 'Customer A']);
        $customer2 = Contact::factory()->customer()->create(['name' => 'Customer B']);

        Invoice::factory()->forContact($customer1)->sent()->create([
            'due_date' => now()->addDays(5),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->forContact($customer2)->sent()->create([
            'due_date' => now()->addDays(5),
            'total_amount' => 2000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/reports/receivable-aging');

        $response->assertOk();

        $contacts = $response->json('contacts');
        expect($contacts)->toHaveCount(2);
    });

    it('can get aging for specific contact', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->forContact($customer)->sent()->count(3)->create([
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/reports/contacts/{$customer->id}/aging");

        $response->assertOk()
            ->assertJsonStructure([
                'report_name',
                'contact',
                'as_of_date',
                'receivable',
                'payable',
            ]);
    });

    it('excludes paid invoices from aging', function () {
        $customer = Contact::factory()->customer()->create();

        // Paid invoice should not appear
        Invoice::factory()->forContact($customer)->paid()->create([
            'total_amount' => 5000000,
            'paid_amount' => 5000000,
        ]);

        // Outstanding invoice should appear
        Invoice::factory()->forContact($customer)->sent()->create([
            'due_date' => now()->addDays(5),
            'total_amount' => 1000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson('/api/v1/reports/receivable-aging');

        $response->assertOk()
            ->assertJsonPath('totals.total', 1000000);
    });

    it('handles partial payments in aging', function () {
        $customer = Contact::factory()->customer()->create();

        Invoice::factory()->forContact($customer)->partial()->create([
            'due_date' => now()->addDays(5),
            'total_amount' => 5000000,
            'paid_amount' => 2000000, // 3M outstanding
        ]);

        $response = $this->getJson('/api/v1/reports/receivable-aging');

        $response->assertOk()
            ->assertJsonPath('totals.total', 3000000);
    });
});
