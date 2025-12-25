<?php

use App\Models\Accounting\Account;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Invoice API', function () {
    
    it('can list all invoices', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter invoices by status', function () {
        Invoice::factory()->draft()->count(2)->create();
        Invoice::factory()->sent()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices?status=sent');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter invoices by contact', function () {
        $customer = Contact::factory()->customer()->create();
        Invoice::factory()->forContact($customer)->count(2)->create();
        Invoice::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/invoices?contact_id={$customer->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can create an invoice with items', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => '2024-12-25',
            'due_date' => '2025-01-25',
            'description' => 'Test Invoice',
            'reference' => 'PO-001',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Jasa Konsultasi',
                    'quantity' => 10,
                    'unit' => 'jam',
                    'unit_price' => 500000,
                ],
                [
                    'description' => 'Biaya Transport',
                    'quantity' => 1,
                    'unit' => 'paket',
                    'unit_price' => 250000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.items');
        
        // Verify calculations: 5,000,000 + 250,000 = 5,250,000 subtotal
        // Tax: 5,250,000 * 11% = 577,500
        // Total: 5,827,500
        $response->assertJsonPath('data.subtotal', 5250000)
            ->assertJsonPath('data.tax_amount', 577500)
            ->assertJsonPath('data.total_amount', 5827500);
    });

    it('validates required fields when creating invoice', function () {
        $response = $this->postJson('/api/v1/invoices', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'invoice_date', 'due_date', 'items']);
    });

    it('validates due date is after invoice date', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $customer->id,
            'invoice_date' => '2024-12-25',
            'due_date' => '2024-12-20', // Before invoice date
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    });

    it('can show a single invoice with items', function () {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->forInvoice($invoice)->count(2)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create();
        InvoiceItem::factory()->forInvoice($invoice)->create();
        $newCustomer = Contact::factory()->customer()->create();

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'contact_id' => $newCustomer->id,
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.contact_id', $newCustomer->id)
            ->assertJsonPath('data.description', 'Updated description');
    });

    it('cannot update posted invoice', function () {
        $invoice = Invoice::factory()->sent()->create();

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'description' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can update invoice items', function () {
        $invoice = Invoice::factory()->draft()->create([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);
        InvoiceItem::factory()->forInvoice($invoice)->create();

        $response = $this->putJson("/api/v1/invoices/{$invoice->id}", [
            'items' => [
                ['description' => 'New Item 1', 'quantity' => 2, 'unit_price' => 300000],
                ['description' => 'New Item 2', 'quantity' => 1, 'unit_price' => 400000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.subtotal', 1000000); // 600000 + 400000
    });

    it('can delete a draft invoice', function () {
        $invoice = Invoice::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk();
        $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
    });

    it('cannot delete posted invoice', function () {
        $invoice = Invoice::factory()->sent()->create();

        $response = $this->deleteJson("/api/v1/invoices/{$invoice->id}");

        $response->assertUnprocessable();
    });

    it('can post a draft invoice to journal', function () {
        $customer = Contact::factory()->customer()->create();
        $invoice = Invoice::factory()->draft()->forContact($customer)->create([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);
        InvoiceItem::factory()->forInvoice($invoice)->create([
            'amount' => 1000000,
        ]);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/post");

        $response->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonStructure(['data' => ['journal_entry']]);
        
        // Verify journal entry was created
        $this->assertNotNull($response->json('data.journal_entry_id'));
    });

    it('cannot post already posted invoice', function () {
        $invoice = Invoice::factory()->sent()->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/post");

        $response->assertUnprocessable();
    });
});
