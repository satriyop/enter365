<?php

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Inventory\Warehouse;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\SalesReturn;
use App\Models\Sales\SalesReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->seed(\Database\Seeders\ChartOfAccountsSeeder::class);
});

describe('Sales Return CRUD', function () {

    it('can list all sales returns', function () {
        SalesReturn::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/sales-returns');

        $response->assertOk()
            ->assertJsonCount(5, 'data');
    });

    it('can filter sales returns by status', function () {
        SalesReturn::factory()->draft()->count(3)->create();
        SalesReturn::factory()->submitted()->count(2)->create();

        $response = $this->getJson('/api/v1/sales-returns?status=draft');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter sales returns by contact', function () {
        $contact = Contact::factory()->customer()->create();
        SalesReturn::factory()->forContact($contact)->count(2)->create();
        SalesReturn::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/sales-returns?contact_id={$contact->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter sales returns by date range', function () {
        SalesReturn::factory()->create(['return_date' => '2025-12-01']);
        SalesReturn::factory()->create(['return_date' => '2025-12-15']);
        SalesReturn::factory()->create(['return_date' => '2025-12-30']);

        $response = $this->getJson('/api/v1/sales-returns?start_date=2025-12-10&end_date=2025-12-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can create a sales return with items', function () {
        $contact = Contact::factory()->customer()->create();
        $product = Product::factory()->create();

        $response = $this->postJson('/api/v1/sales-returns', [
            'contact_id' => $contact->id,
            'return_date' => '2025-12-26',
            'reason' => 'damaged',
            'tax_rate' => 11,
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => 'Solar Panel 300W (Rusak)',
                    'quantity' => 2,
                    'unit' => 'pcs',
                    'unit_price' => 1500000,
                    'condition' => 'damaged',
                ],
                [
                    'description' => 'Inverter 3kW (Cacat)',
                    'quantity' => 1,
                    'unit' => 'unit',
                    'unit_price' => 8000000,
                    'condition' => 'defective',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status.value', 'draft')
            ->assertJsonCount(2, 'data.items');

        expect($response->json('data.return_number'))->toStartWith('SR-');
        expect($response->json('data.subtotal'))->toBe(11000000); // 2*1.5M + 1*8M
    });

    it('validates required fields when creating sales return', function () {
        $response = $this->postJson('/api/v1/sales-returns', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'return_date', 'items']);
    });

    it('can show a single sales return', function () {
        $salesReturn = SalesReturn::factory()->create();
        SalesReturnItem::factory()->forSalesReturn($salesReturn)->count(2)->create();

        $response = $this->getJson("/api/v1/sales-returns/{$salesReturn->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $salesReturn->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft sales return', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();
        SalesReturnItem::factory()->forSalesReturn($salesReturn)->create();

        $response = $this->putJson("/api/v1/sales-returns/{$salesReturn->id}", [
            'reason' => 'quality_issue',
            'notes' => 'Updated notes',
            'items' => [
                [
                    'description' => 'Updated Item',
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                ],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reason', 'quality_issue')
            ->assertJsonPath('data.notes', 'Updated notes')
            ->assertJsonCount(1, 'data.items');
    });

    it('cannot update a non-draft sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->create();

        $response = $this->putJson("/api/v1/sales-returns/{$salesReturn->id}", [
            'reason' => 'quality_issue',
        ]);

        $response->assertUnprocessable();
    });

    it('can delete a draft sales return', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/sales-returns/{$salesReturn->id}");

        $response->assertOk();
        $this->assertSoftDeleted('sales_returns', ['id' => $salesReturn->id]);
    });

    it('cannot delete a non-draft sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->create();

        $response = $this->deleteJson("/api/v1/sales-returns/{$salesReturn->id}");

        $response->assertUnprocessable();
    });
});

describe('Sales Return Workflow', function () {

    it('can submit a draft sales return with items', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();
        SalesReturnItem::factory()->forSalesReturn($salesReturn)->count(2)->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'submitted');

        $salesReturn->refresh();
        expect($salesReturn->submitted_at)->not->toBeNull();
        expect($salesReturn->submitted_by)->toBe($this->user->id);
    });

    it('cannot submit a sales return without items', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/submit");

        $response->assertUnprocessable();
    });

    it('can approve a submitted sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->withTotals(100000)->create();
        SalesReturnItem::factory()->forSalesReturn($salesReturn)->withLineTotal(100000)->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'approved');

        $salesReturn->refresh();
        expect($salesReturn->approved_at)->not->toBeNull();
        expect($salesReturn->approved_by)->toBe($this->user->id);
        expect($salesReturn->journal_entry_id)->not->toBeNull();
    });

    it('cannot approve a non-submitted sales return', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/approve");

        $response->assertUnprocessable();
    });

    it('can reject a submitted sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/reject", [
            'reason' => 'Return not valid',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Return not valid');

        $salesReturn->refresh();
        expect($salesReturn->rejected_at)->not->toBeNull();
        expect($salesReturn->rejected_by)->toBe($this->user->id);
    });

    it('cannot reject a non-submitted sales return', function () {
        $salesReturn = SalesReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/reject");

        $response->assertUnprocessable();
    });

    it('can complete an approved sales return', function () {
        $salesReturn = SalesReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'completed');

        $salesReturn->refresh();
        expect($salesReturn->completed_at)->not->toBeNull();
        expect($salesReturn->completed_by)->toBe($this->user->id);
    });

    it('cannot complete a non-approved sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/complete");

        $response->assertUnprocessable();
    });

    it('can cancel a draft sales return', function () {
        $salesReturn = SalesReturn::factory()->draft()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/cancel", [
            'reason' => 'No longer needed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('can cancel a submitted sales return', function () {
        $salesReturn = SalesReturn::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/cancel", [
            'reason' => 'Customer changed mind',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');
    });

    it('cannot cancel an approved sales return', function () {
        $salesReturn = SalesReturn::factory()->approved()->create();

        $response = $this->postJson("/api/v1/sales-returns/{$salesReturn->id}/cancel");

        $response->assertUnprocessable();
    });
});

describe('Sales Return from Invoice', function () {

    it('can create sales return from invoice', function () {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->for($invoice)->count(2)->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/create-sales-return", [
            'return_date' => '2025-12-26',
            'reason' => 'damaged',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.invoice_id', $invoice->id)
            ->assertJsonPath('data.contact_id', $invoice->contact_id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can list sales returns for an invoice', function () {
        $invoice = Invoice::factory()->create();
        SalesReturn::factory()->forInvoice($invoice)->count(2)->create();
        SalesReturn::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}/sales-returns");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });
});

describe('Sales Return with Warehouse', function () {

    it('can create sales return with warehouse for inventory', function () {
        $contact = Contact::factory()->customer()->create();
        $warehouse = Warehouse::factory()->create();
        $product = Product::factory()->create(['track_inventory' => true]);

        $response = $this->postJson('/api/v1/sales-returns', [
            'contact_id' => $contact->id,
            'warehouse_id' => $warehouse->id,
            'return_date' => '2025-12-26',
            'reason' => 'damaged',
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 5,
                    'unit' => 'pcs',
                    'unit_price' => 100000,
                    'condition' => 'good',
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.warehouse_id', $warehouse->id);
    });
});

describe('Sales Return Statistics', function () {

    it('returns sales return statistics', function () {
        SalesReturn::factory()->draft()->count(2)->create();
        SalesReturn::factory()->submitted()->count(3)->create();
        SalesReturn::factory()->approved()->withTotals(500000)->count(1)->create();
        SalesReturn::factory()->completed()->withTotals(300000)->count(1)->create();
        SalesReturn::factory()->cancelled()->count(1)->create();

        $response = $this->getJson('/api/v1/sales-returns-statistics');

        $response->assertOk()
            ->assertJsonStructure([
                'total_count',
                'draft_count',
                'submitted_count',
                'approved_count',
                'completed_count',
                'cancelled_count',
                'total_value',
                'by_reason',
            ]);

        expect($response->json('total_count'))->toBe(8);
        expect($response->json('draft_count'))->toBe(2);
        expect($response->json('submitted_count'))->toBe(3);
    });

    it('can filter statistics by date range', function () {
        SalesReturn::factory()->create(['return_date' => '2025-12-01']);
        SalesReturn::factory()->create(['return_date' => '2025-12-15']);
        SalesReturn::factory()->create(['return_date' => '2025-12-30']);

        $response = $this->getJson('/api/v1/sales-returns-statistics?start_date=2025-12-10&end_date=2025-12-20');

        $response->assertOk();
        expect($response->json('total_count'))->toBe(1);
    });
});
