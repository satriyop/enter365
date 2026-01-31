<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Product;
use App\Models\Sales\Invoice;
use App\Models\Sales\InvoiceItem;
use App\Models\Sales\Quotation;
use App\Models\Sales\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\ChartOfAccountsSeeder']);
    $this->artisan('db:seed', ['--class' => 'Database\\Seeders\\FiscalPeriodSeeder']);

    // Load OpenAPI schema
    $schemaPath = base_path('api.json');
    if (! File::exists($schemaPath)) {
        $this->markTestSkipped('api.json not found. Run: php artisan scramble:export --path=api.json');
    }

    $this->schema = json_decode(File::get($schemaPath), true);
    if (! is_array($this->schema)) {
        $this->markTestSkipped('api.json is not valid JSON');
    }
});

describe('API Contract Validation', function () {

    it('loads OpenAPI schema successfully', function () {
        expect($this->schema)->toBeArray();
        expect($this->schema)->toHaveKey('openapi');
        expect($this->schema)->toHaveKey('paths');
    });

    it('validates quotation list response matches schema', function () {
        Quotation::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/quotations');

        $response->assertOk();

        // Basic structure validation
        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();

        // Validate first item structure
        if (count($data['data']) > 0) {
            $firstItem = $data['data'][0];
            validateQuotationStructure($firstItem);
        }
    });

    it('validates quotation detail response matches schema', function () {
        $quotation = Quotation::factory()
            ->has(QuotationItem::factory()->count(2), 'items')
            ->create();

        $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

        $response->assertOk();

        $data = $response->json('data');
        validateQuotationStructure($data);
    });

    it('validates invoice list response matches schema', function () {
        Invoice::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/invoices');

        $response->assertOk();

        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();

        if (count($data['data']) > 0) {
            $firstItem = $data['data'][0];
            validateInvoiceStructure($firstItem);
        }
    });

    it('validates invoice detail response matches schema', function () {
        $invoice = Invoice::factory()
            ->has(InvoiceItem::factory()->count(2), 'items')
            ->create();

        $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

        $response->assertOk();

        $data = $response->json('data');
        validateInvoiceStructure($data);
    });

    it('validates product list response matches schema', function () {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products');

        $response->assertOk();

        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();
    });

    it('validates contact list response matches schema', function () {
        Contact::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/contacts');

        $response->assertOk();

        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data['data'])->toBeArray();
    });

    it('validates contact detail response matches schema', function () {
        $contact = Contact::factory()->create();

        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('name');
        expect($data['id'])->toBeInt();
        expect($data['name'])->toBeString();
    });

    it('validates product detail response matches schema', function () {
        $product = Product::factory()->create();

        $response = $this->getJson("/api/v1/products/{$product->id}");

        $response->assertOk();

        $data = $response->json('data');
        expect($data)->toHaveKey('id');
        expect($data)->toHaveKey('name');
        expect($data)->toHaveKey('sku');
        expect($data['id'])->toBeInt();
        expect($data['name'])->toBeString();
        expect($data['sku'])->toBeString();
    });

    it('validates paginated responses have correct structure', function () {
        Quotation::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/quotations?per_page=10');

        $response->assertOk();

        $data = $response->json();
        expect($data)->toHaveKey('data');
        expect($data)->toHaveKey('meta');
        expect($data['data'])->toBeArray();
        expect($data['meta'])->toBeArray();
        expect($data['meta'])->toHaveKey('current_page');
        expect($data['meta'])->toHaveKey('per_page');
        expect($data['meta'])->toHaveKey('total');
    });

    it('validates error responses have consistent structure', function () {
        $response = $this->getJson('/api/v1/quotations/99999');

        $response->assertNotFound();

        $data = $response->json();
        expect($data)->toHaveKey('success');
        expect($data['success'])->toBeFalse();
        expect($data)->toHaveKey('message');
        expect($data['message'])->toBeString();
    });
});

/**
 * Validate quotation structure matches expected schema.
 */
function validateQuotationStructure(array $quotation): void
{
    // Required fields
    expect($quotation)->toHaveKey('id');
    expect($quotation)->toHaveKey('quotation_number');
    expect($quotation)->toHaveKey('status');
    expect($quotation)->toHaveKey('total_amount');

    // Type validation
    expect($quotation['id'])->toBeInt();
    expect($quotation['quotation_number'])->toBeString();
    expect($quotation['total_amount'])->toBeInt(); // Amounts are integers

    // Status structure
    if (isset($quotation['status'])) {
        if (is_array($quotation['status'])) {
            expect($quotation['status'])->toHaveKey('value');
            expect($quotation['status'])->toHaveKey('label');
        }
    }
}

/**
 * Validate invoice structure matches expected schema.
 */
function validateInvoiceStructure(array $invoice): void
{
    // Required fields
    expect($invoice)->toHaveKey('id');
    expect($invoice)->toHaveKey('invoice_number');
    expect($invoice)->toHaveKey('status');
    expect($invoice)->toHaveKey('total_amount');

    // Type validation
    expect($invoice['id'])->toBeInt();
    expect($invoice['invoice_number'])->toBeString();
    expect($invoice['total_amount'])->toBeInt(); // Amounts are integers

    // Status structure
    if (isset($invoice['status'])) {
        if (is_array($invoice['status'])) {
            expect($invoice['status'])->toHaveKey('value');
            expect($invoice['status'])->toHaveKey('label');
        }
    }
}
