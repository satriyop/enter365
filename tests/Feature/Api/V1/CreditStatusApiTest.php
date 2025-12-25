<?php

use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Credit Status API', function () {

    it('can get credit status for customer', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 10000000,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonStructure([
                'contact_id',
                'name',
                'credit_limit',
                'receivable_balance',
                'available_credit',
                'credit_utilization_percent',
                'is_exceeded',
                'is_warning',
                'can_create_invoice',
            ]);
    });

    it('returns credit status not available for supplier', function () {
        $supplier = Contact::factory()->supplier()->create();

        $response = $this->getJson("/api/v1/contacts/{$supplier->id}/credit-status");

        $response->assertUnprocessable();
    });

    it('calculates correct credit utilization', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 10000000,
        ]);

        // Create outstanding invoice (5M out of 10M limit = 50%)
        Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 5000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('receivable_balance', 5000000)
            ->assertJsonPath('available_credit', 5000000)
            ->assertJsonPath('is_exceeded', false);

        expect((float) $response->json('credit_utilization_percent'))->toBe(50.0);
    });

    it('detects exceeded credit limit', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 5000000,
        ]);

        // Create outstanding invoice exceeding limit
        Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 6000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('is_exceeded', true)
            ->assertJsonPath('available_credit', 0);
    });

    it('detects credit limit warning threshold', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 10000000,
        ]);

        // Create outstanding invoice at 85% (above 80% warning threshold)
        Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 8500000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('is_warning', true)
            ->assertJsonPath('is_exceeded', false);
    });

    it('handles customer with no credit limit', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 0,
        ]);

        // Create large outstanding invoice
        Invoice::factory()->forContact($customer)->sent()->create([
            'total_amount' => 100000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('is_exceeded', false)
            ->assertJsonPath('is_warning', false)
            ->assertJsonPath('can_create_invoice', true);
    });

    it('considers only outstanding invoices', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 10000000,
        ]);

        // Paid invoice should not count
        Invoice::factory()->forContact($customer)->paid()->create([
            'total_amount' => 5000000,
            'paid_amount' => 5000000,
        ]);

        // Draft invoice should not count
        Invoice::factory()->forContact($customer)->draft()->create([
            'total_amount' => 3000000,
            'paid_amount' => 0,
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('receivable_balance', 0)
            ->assertJsonPath('available_credit', 10000000);
    });

    it('calculates partial payments correctly', function () {
        $customer = Contact::factory()->customer()->create([
            'credit_limit' => 10000000,
        ]);

        // Partially paid invoice
        Invoice::factory()->forContact($customer)->partial()->create([
            'total_amount' => 5000000,
            'paid_amount' => 2000000, // 3M outstanding
        ]);

        $response = $this->getJson("/api/v1/contacts/{$customer->id}/credit-status");

        $response->assertOk()
            ->assertJsonPath('receivable_balance', 3000000)
            ->assertJsonPath('available_credit', 7000000);
    });
});
