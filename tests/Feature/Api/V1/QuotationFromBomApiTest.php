<?php

use App\Models\Accounting\Bom;
use App\Models\Accounting\BomItem;
use App\Models\Accounting\Contact;
use App\Models\Accounting\Product;
use App\Models\Accounting\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    $user = User::factory()->create();
    Sanctum::actingAs($user);
});

describe('Create Quotation from BOM', function () {

    it('can create quotation from active BOM with default margin', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals(1000000, 200000, 100000)->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quotation_type', 'single')
            ->assertJsonPath('data.status', 'draft');

        // Default margin is 20%
        $expectedTotal = (int) round(1300000 * 1.2); // BOM cost * 1.20
        expect($response->json('data.subtotal'))->toBe($expectedTotal);
    });

    it('can create quotation from BOM with custom margin', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals(1000000, 0, 0)->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'margin_percent' => 50,
        ]);

        $response->assertCreated();

        // 1000000 * 1.50 = 1500000
        expect($response->json('data.subtotal'))->toBe(1500000);
    });

    it('can create quotation from BOM with direct selling price', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals(1000000, 0, 0)->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'selling_price' => 2000000,
        ]);

        $response->assertCreated();
        expect($response->json('data.subtotal'))->toBe(2000000);
    });

    it('creates single line item by default', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create();

        // Add some BOM items
        BomItem::factory()->count(3)->create(['bom_id' => $bom->id]);

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertCreated();

        // Should have 1 item (the finished product), not 3 expanded items
        expect($response->json('data.items'))->toHaveCount(1);
    });

    it('can expand BOM items as quotation line items', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create();

        // Add 3 BOM items
        BomItem::factory()->count(3)->create([
            'bom_id' => $bom->id,
            'unit_cost' => 100000,
            'quantity' => 1,
        ]);

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'expand_items' => true,
            'margin_percent' => 25,
        ]);

        $response->assertCreated();

        // Should have 3 items (expanded from BOM)
        expect($response->json('data.items'))->toHaveCount(3);

        // Each item should have margin applied: 100000 * 1.25 = 125000
        foreach ($response->json('data.items') as $item) {
            expect($item['unit_price'])->toBe(125000);
        }
    });

    it('stores source_bom_id reference', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertCreated();

        $quotation = Quotation::find($response->json('data.id'));
        expect($quotation->source_bom_id)->toBe($bom->id);
    });

    it('uses BOM number as reference', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create(['bom_number' => 'BOM-TEST-001']);

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.reference', 'BOM-TEST-001');
    });

    it('includes variant name in subject if BOM is variant', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create([
            'name' => 'PLTS 50 kWp',
            'variant_name' => 'Premium',
        ]);

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', 'PLTS 50 kWp - Premium');
    });
});

describe('Validation', function () {

    it('requires bom_id', function () {
        $contact = Contact::factory()->customer()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'contact_id' => $contact->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bom_id']);
    });

    it('requires contact_id', function () {
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['contact_id']);
    });

    it('rejects draft BOM', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->draft()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bom_id']);
    });

    it('rejects inactive BOM', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->inactive()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['bom_id']);
    });

    it('validates margin_percent range', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'margin_percent' => 600, // Max is 500
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['margin_percent']);
    });

    it('validates selling_price is not negative', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'selling_price' => -1000,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['selling_price']);
    });

    it('validates valid_until is after quotation_date', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'quotation_date' => '2024-12-27',
            'valid_until' => '2024-12-20', // Before quotation_date
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['valid_until']);
    });
});

describe('Optional Parameters', function () {

    it('accepts custom quotation_date and valid_until', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'quotation_date' => '2024-12-15',
            'valid_until' => '2025-01-15',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.quotation_date', '2024-12-15')
            ->assertJsonPath('data.valid_until', '2025-01-15');
    });

    it('accepts custom subject', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals()->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'subject' => 'Custom Subject for Solar Installation',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.subject', 'Custom Subject for Solar Installation');
    });

    it('accepts custom tax_rate', function () {
        $contact = Contact::factory()->customer()->create();
        $bom = Bom::factory()->active()->withTotals(1000000, 0, 0)->create();

        $response = $this->postJson('/api/v1/quotations/from-bom', [
            'bom_id' => $bom->id,
            'contact_id' => $contact->id,
            'selling_price' => 1000000,
            'tax_rate' => 12.0,
        ]);

        $response->assertCreated();
        expect((float) $response->json('data.tax_rate'))->toBe(12.0);
        expect($response->json('data.tax_amount'))->toBe(120000); // 1000000 * 12%
    });
});
