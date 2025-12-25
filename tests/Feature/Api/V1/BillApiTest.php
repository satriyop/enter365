<?php

use App\Models\Accounting\Bill;
use App\Models\Accounting\BillItem;
use App\Models\Accounting\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Bill API', function () {
    
    it('can list all bills', function () {
        Bill::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/bills');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter bills by status', function () {
        Bill::factory()->draft()->count(2)->create();
        Bill::factory()->received()->count(3)->create();

        $response = $this->getJson('/api/v1/bills?status=received');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter bills by supplier', function () {
        $supplier = Contact::factory()->supplier()->create();
        Bill::factory()->forContact($supplier)->count(2)->create();
        Bill::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/bills?contact_id={$supplier->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create a bill with items', function () {
        $supplier = Contact::factory()->supplier()->create();

        $response = $this->postJson('/api/v1/bills', [
            'contact_id' => $supplier->id,
            'vendor_invoice_number' => 'INV-SUP-001',
            'bill_date' => '2024-12-25',
            'due_date' => '2025-01-25',
            'description' => 'Purchase from supplier',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Barang A',
                    'quantity' => 100,
                    'unit' => 'pcs',
                    'unit_price' => 25000,
                ],
                [
                    'description' => 'Barang B',
                    'quantity' => 50,
                    'unit' => 'pcs',
                    'unit_price' => 15000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.vendor_invoice_number', 'INV-SUP-001')
            ->assertJsonCount(2, 'data.items');
        
        // Verify calculations: 2,500,000 + 750,000 = 3,250,000 subtotal
        // Tax: 3,250,000 * 11% = 357,500
        // Total: 3,607,500
        $response->assertJsonPath('data.subtotal', 3250000)
            ->assertJsonPath('data.tax_amount', 357500)
            ->assertJsonPath('data.total_amount', 3607500);
    });

    it('validates required fields when creating bill', function () {
        $response = $this->postJson('/api/v1/bills', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'bill_date', 'due_date', 'items']);
    });

    it('can show a single bill with items', function () {
        $bill = Bill::factory()->create();
        BillItem::factory()->forBill($bill)->count(2)->create();

        $response = $this->getJson("/api/v1/bills/{$bill->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft bill', function () {
        $bill = Bill::factory()->draft()->create();
        BillItem::factory()->forBill($bill)->create();

        $response = $this->putJson("/api/v1/bills/{$bill->id}", [
            'vendor_invoice_number' => 'UPDATED-INV',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.vendor_invoice_number', 'UPDATED-INV')
            ->assertJsonPath('data.description', 'Updated description');
    });

    it('cannot update posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->putJson("/api/v1/bills/{$bill->id}", [
            'description' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft bill', function () {
        $bill = Bill::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/bills/{$bill->id}");

        $response->assertOk();
        $this->assertSoftDeleted('bills', ['id' => $bill->id]);
    });

    it('cannot delete posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->deleteJson("/api/v1/bills/{$bill->id}");

        $response->assertUnprocessable();
    });

    it('can post a draft bill to journal', function () {
        $supplier = Contact::factory()->supplier()->create();
        $bill = Bill::factory()->draft()->forContact($supplier)->create([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);
        BillItem::factory()->forBill($bill)->create([
            'amount' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertOk()
            ->assertJsonPath('data.status', 'received')
            ->assertJsonStructure(['data' => ['journal_entry']]);
        
        $this->assertNotNull($response->json('data.journal_entry_id'));
    });

    it('cannot post already posted bill', function () {
        $bill = Bill::factory()->received()->create();

        $response = $this->postJson("/api/v1/bills/{$bill->id}/post");

        $response->assertUnprocessable();
    });
});
