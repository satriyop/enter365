<?php

use App\Enums\DocumentStatus;
use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);
});

describe('Quotation CRUD', function () {

    it('can list all quotations', function () {
        Quotation::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter quotations by status', function () {
        Quotation::factory()->draft()->count(2)->create();
        Quotation::factory()->submitted()->count(3)->create();

        $response = $this->getJson('/api/v1/quotations?status=submitted');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can filter quotations by contact', function () {
        $customer = Contact::factory()->customer()->create();
        Quotation::factory()->forContact($customer)->count(2)->create();
        Quotation::factory()->count(3)->create();

        $response = $this->getJson("/api/v1/quotations?contact_id={$customer->id}");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter quotations by date range', function () {
        Quotation::factory()->create(['quotation_date' => '2024-12-01']);
        Quotation::factory()->create(['quotation_date' => '2024-12-15']);
        Quotation::factory()->create(['quotation_date' => '2024-12-25']);

        $response = $this->getJson('/api/v1/quotations?start_date=2024-12-10&end_date=2024-12-20');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can search quotations', function () {
        Quotation::factory()->create(['subject' => 'Panel Listrik 100A']);
        Quotation::factory()->create(['subject' => 'Solar Panel Installation']);
        Quotation::factory()->create(['reference' => 'REF-PANEL-001']);

        $response = $this->getJson('/api/v1/quotations?search=panel');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });

    it('can create a quotation with items', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => '2024-12-25',
            'valid_until' => '2025-01-25',
            'subject' => 'Panel Listrik 100A',
            'reference' => 'REF-001',
            'tax_rate' => 11,
            'items' => [
                [
                    'description' => 'Main Circuit Breaker 100A',
                    'quantity' => 1,
                    'unit' => 'pcs',
                    'unit_price' => 2500000,
                ],
                [
                    'description' => 'Busbar Copper',
                    'quantity' => 2,
                    'unit' => 'm',
                    'unit_price' => 500000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonCount(2, 'data.items');

        // Verify calculations: 2,500,000 + 1,000,000 = 3,500,000 subtotal
        // Tax: 3,500,000 * 11% = 385,000
        // Total: 3,885,000
        $response->assertJsonPath('data.subtotal', 3500000)
            ->assertJsonPath('data.tax_amount', 385000)
            ->assertJsonPath('data.total', 3885000);
    });

    it('sets default validity to 30 days', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => '2024-12-25',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.valid_until', '2025-01-24');
    });

    it('sets default terms and conditions', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => '2024-12-25',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        $response->assertCreated();
        expect($response->json('data.terms_conditions'))->toContain('SYARAT DAN KETENTUAN');
    });

    it('validates required fields when creating quotation', function () {
        $response = $this->postJson('/api/v1/quotations', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id', 'quotation_date', 'items']);
    });

    it('validates valid_until is after quotation_date', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => '2024-12-25',
            'valid_until' => '2024-12-20',
            'items' => [
                ['description' => 'Test', 'quantity' => 1, 'unit_price' => 100000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_until']);
    });

    it('can show a single quotation with items', function () {
        $quotation = Quotation::factory()->create();
        QuotationItem::factory()->forQuotation($quotation)->count(2)->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $quotation->id)
            ->assertJsonCount(2, 'data.items');
    });

    it('can update a draft quotation', function () {
        $quotation = Quotation::factory()->draft()->create();
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}", [
            'subject' => 'Updated Subject',
            'notes' => 'Updated notes',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.subject', 'Updated Subject')
            ->assertJsonPath('data.notes', 'Updated notes');
    });

    it('cannot update non-draft quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}", [
            'subject' => 'Should fail',
        ]);

        $response->assertUnprocessable();
    });

    it('can update quotation items', function () {
        $quotation = Quotation::factory()->draft()->create([
            'subtotal' => 1000000,
            'tax_amount' => 110000,
            'total_amount' => 1110000,
        ]);
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->putJson("/api/v1/quotations/{$quotation->id}", [
            'items' => [
                ['description' => 'New Item 1', 'quantity' => 2, 'unit_price' => 300000],
                ['description' => 'New Item 2', 'quantity' => 1, 'unit_price' => 400000],
            ],
        ]);

        $response->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.subtotal', 1000000); // 600000 + 400000
    });

    it('can delete a draft quotation', function () {
        $quotation = Quotation::factory()->draft()->create();

        $response = $this->deleteJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk();
        $this->assertSoftDeleted('quotations', ['id' => $quotation->id]);
    });

    it('cannot delete non-draft quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();

        $response = $this->deleteJson("/api/v1/quotations/{$quotation->id}");

        $response->assertUnprocessable();
    });
});

describe('Quotation Workflow', function () {

    it('can submit a draft quotation', function () {
        $quotation = Quotation::factory()->draft()->create();
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/submit");

        $response->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $quotation->refresh();
        expect($quotation->submitted_at)->not->toBeNull();
    });

    it('cannot submit quotation without items', function () {
        $quotation = Quotation::factory()->draft()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/submit");

        $response->assertUnprocessable();
    });

    it('cannot submit non-draft quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/submit");

        $response->assertUnprocessable();
    });

    it('can approve a submitted quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $quotation->refresh();
        expect($quotation->approved_at)->not->toBeNull();
    });

    it('cannot approve non-submitted quotation', function () {
        $quotation = Quotation::factory()->draft()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/approve");

        $response->assertUnprocessable();
    });

    it('cannot approve expired quotation', function () {
        $quotation = Quotation::factory()->submitted()->create([
            'valid_until' => now()->subDays(10),
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/approve");

        $response->assertUnprocessable();
    });

    it('can reject a submitted quotation with reason', function () {
        $quotation = Quotation::factory()->submitted()->create();
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/reject", [
            'reason' => 'Harga terlalu tinggi',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.rejection_reason', 'Harga terlalu tinggi');
    });

    it('cannot reject without reason', function () {
        $quotation = Quotation::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/reject", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['reason']);
    });

    it('can create revision from approved quotation', function () {
        $quotation = Quotation::factory()->approved()->create();
        QuotationItem::factory()->forQuotation($quotation)->count(2)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/revise");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revision', 1)
            ->assertJsonPath('data.quotation_number', $quotation->quotation_number)
            ->assertJsonCount(2, 'data.items');
    });

    it('can create revision from rejected quotation', function () {
        $quotation = Quotation::factory()->rejected()->create();
        QuotationItem::factory()->forQuotation($quotation)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/revise");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.revision', 1);
    });

    it('revision links to original quotation', function () {
        $original = Quotation::factory()->approved()->create();
        QuotationItem::factory()->forQuotation($original)->create();

        $response = $this->postJson("/api/v1/quotations/{$original->id}/revise");

        $response->assertCreated()
            ->assertJsonPath('data.original_quotation_id', $original->id);
    });

    it('multiple revisions increment revision number', function () {
        $original = Quotation::factory()->approved()->create(['revision' => 0]);
        QuotationItem::factory()->forQuotation($original)->create();

        // First revision
        $response1 = $this->postJson("/api/v1/quotations/{$original->id}/revise");
        $response1->assertCreated()
            ->assertJsonPath('data.revision', 1);

        $revision1 = Quotation::find($response1->json('data.id'));

        // Approve first revision
        $revision1->update(['status' => 'approved']);

        // Second revision from first revision
        $response2 = $this->postJson("/api/v1/quotations/{$revision1->id}/revise");
        $response2->assertCreated()
            ->assertJsonPath('data.revision', 2);
    });
});

describe('Quotation Conversion', function () {

    it('can convert approved quotation to invoice', function () {
        $quotation = Quotation::factory()->approved()->create([
            'subject' => 'Panel Listrik 100A',
            'subtotal' => 5000000,
            'tax_amount' => 550000,
            'total_amount' => 5550000,
        ]);
        QuotationItem::factory()->forQuotation($quotation)->count(2)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated()
            ->assertJsonStructure(['message', 'invoice', 'quotation']);

        $quotation->refresh();
        expect($quotation->status)->toBe(DocumentStatus::Converted);
        expect($quotation->converted_to_invoice_id)->not->toBeNull();
        expect($quotation->converted_at)->not->toBeNull();
    });

    it('invoice has correct data from quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'subject' => 'Panel Listrik 100A',
            'subtotal' => 5000000,
            'tax_rate' => 11,
            'tax_amount' => 550000,
            'total_amount' => 5550000,
        ]);
        QuotationItem::factory()->forQuotation($quotation)->create([
            'description' => 'MCB 100A',
            'quantity' => 1,
            'unit_price' => 5000000,
            'line_total' => 5000000,
        ]);

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertCreated();

        $invoice = Invoice::find($response->json('invoice.id'));
        expect($invoice->description)->toBe('Panel Listrik 100A');
        expect($invoice->reference)->toBe($quotation->getFullNumber());
        expect($invoice->total_amount)->toBe(5550000);
        expect($invoice->items)->toHaveCount(1);
    });

    it('cannot convert non-approved quotation', function () {
        $quotation = Quotation::factory()->submitted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertStatus(409);
    });

    it('cannot convert already converted quotation', function () {
        $quotation = Quotation::factory()->converted()->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/convert-to-invoice");

        $response->assertStatus(409);
    });
});

describe('Quotation Expiry', function () {

    it('shows expired status for past valid_until', function () {
        $quotation = Quotation::factory()->create([
            'valid_until' => now()->subDays(5),
            'status' => DocumentStatus::Submitted,
        ]);

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_expired', true)
            ->assertJsonPath('data.days_until_expiry', 0);
    });

    it('shows days until expiry for future valid_until', function () {
        $quotation = Quotation::factory()->create([
            'valid_until' => now()->addDays(10),
        ]);

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk()
            ->assertJsonPath('data.is_expired', false);
        expect($response->json('data.days_until_expiry'))->toBeGreaterThanOrEqual(9);
    });

    it('can filter expired quotations', function () {
        Quotation::factory()->expired()->count(2)->create();
        Quotation::factory()->validFor(30)->count(3)->create();

        $response = $this->getJson('/api/v1/quotations?status=expired');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    });

    it('can filter active quotations', function () {
        Quotation::factory()->expired()->count(2)->create();
        Quotation::factory()->converted()->count(1)->create();
        Quotation::factory()->draft()->count(3)->create();

        $response = $this->getJson('/api/v1/quotations?active_only=true');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    });
});

describe('Quotation Calculations', function () {

    it('calculates subtotal from items', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'tax_rate' => 0,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 100000],
                ['description' => 'Item 2', 'quantity' => 3, 'unit_price' => 200000],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subtotal', 800000); // 200000 + 600000
    });

    it('applies percentage discount', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'tax_rate' => 11,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 1000000],
            ],
        ]);

        $response->assertCreated();
        // Subtotal: 1,000,000
        // Discount: 100,000 (10%)
        // Taxable: 900,000
        // Tax: 99,000 (11%)
        // Total: 999,000
        $response->assertJsonPath('data.subtotal', 1000000)
            ->assertJsonPath('data.discount_amount', 100000)
            ->assertJsonPath('data.tax_amount', 99000)
            ->assertJsonPath('data.total', 999000);
    });

    it('applies fixed discount', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'discount_type' => 'fixed',
            'discount_value' => 50000,
            'tax_rate' => 11,
            'items' => [
                ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 1000000],
            ],
        ]);

        $response->assertCreated();
        // Subtotal: 1,000,000
        // Discount: 50,000
        // Taxable: 950,000
        // Tax: 104,500 (11%)
        // Total: 1,054,500
        $response->assertJsonPath('data.subtotal', 1000000)
            ->assertJsonPath('data.discount_amount', 50000)
            ->assertJsonPath('data.tax_amount', 104500)
            ->assertJsonPath('data.total', 1054500);
    });

    it('applies line item discount', function () {
        $customer = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'tax_rate' => 0,
            'items' => [
                [
                    'description' => 'Item with discount',
                    'quantity' => 1,
                    'unit_price' => 1000000,
                    'discount_percent' => 20,
                ],
            ],
        ]);

        $response->assertCreated();
        // Gross: 1,000,000
        // Line Discount: 200,000 (20%)
        // Line Total: 800,000
        $response->assertJsonPath('data.items.0.discount_percent', 20)
            ->assertJsonPath('data.items.0.discount_amount', 200000)
            ->assertJsonPath('data.items.0.line_total', 800000)
            ->assertJsonPath('data.subtotal', 800000);
    });
});

describe('Quotation Duplicate', function () {

    it('can duplicate a quotation', function () {
        $quotation = Quotation::factory()->approved()->create([
            'subject' => 'Original Subject',
        ]);
        QuotationItem::factory()->forQuotation($quotation)->count(2)->create();

        $response = $this->postJson("/api/v1/quotations/{$quotation->id}/duplicate");

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.subject', 'Original Subject')
            ->assertJsonCount(2, 'data.items');

        // New quotation number
        expect($response->json('data.quotation_number'))->not->toBe($quotation->quotation_number);
        // No original quotation link (it's a duplicate, not a revision)
        expect($response->json('data.original_quotation_id'))->toBeNull();
    });
});

describe('Quotation Statistics', function () {

    it('returns quotation statistics', function () {
        Quotation::factory()->draft()->count(2)->create();
        Quotation::factory()->submitted()->count(3)->create();
        Quotation::factory()->approved()->count(4)->create();
        Quotation::factory()->rejected()->count(1)->create();
        Quotation::factory()->converted()->count(2)->create();

        $response = $this->getJson('/api/v1/quotations-statistics');

        $response->assertOk()
            ->assertJsonPath('data.total', 12)
            ->assertJsonPath('data.by_status.draft', 2)
            ->assertJsonPath('data.by_status.submitted', 3)
            ->assertJsonPath('data.by_status.approved', 4)
            ->assertJsonPath('data.by_status.rejected', 1)
            ->assertJsonPath('data.by_status.converted', 2);
    });

    it('can filter statistics by date range', function () {
        Quotation::factory()->create(['quotation_date' => '2024-12-01']);
        Quotation::factory()->create(['quotation_date' => '2024-12-15']);
        Quotation::factory()->create(['quotation_date' => '2024-12-25']);

        $response = $this->getJson('/api/v1/quotations-statistics?start_date=2024-12-10&end_date=2024-12-20');

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    });
});

describe('Quotation PDF', function () {

    it('returns not implemented for pdf endpoint', function () {
        $quotation = Quotation::factory()->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}/pdf");

        $response->assertStatus(501)
            ->assertJsonPath('quotation_number', $quotation->getFullNumber());
    });
});

describe('Quotation with Products', function () {

    it('can create quotation with product reference', function () {
        $customer = Contact::factory()->customer()->create();
        $product = Product::factory()->create([
            'name' => 'MCB 100A',
            'selling_price' => 2500000,
            'unit' => 'pcs',
        ]);

        $response = $this->postJson('/api/v1/quotations', [
            'contact_id' => $customer->id,
            'quotation_date' => now()->toDateString(),
            'items' => [
                [
                    'product_id' => $product->id,
                    'description' => $product->name,
                    'quantity' => 2,
                    'unit' => $product->unit,
                    'unit_price' => $product->selling_price,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.0.product_id', $product->id)
            ->assertJsonPath('data.items.0.description', 'MCB 100A');
    });
});
