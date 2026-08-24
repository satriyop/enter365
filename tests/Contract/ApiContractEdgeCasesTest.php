<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Models\Contacts\Contact;
use App\Models\Sales\Invoice;
use App\Models\Sales\Quotation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = authenticatedAdmin();

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

describe('API Contract Edge Cases', function () {

    describe('Empty Collections', function () {

        it('handles empty quotation list response', function () {
            $response = $this->getJson('/api/v1/quotations');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('data');
            expect($data['data'])->toBeArray();
            expect($data['data'])->toBeEmpty();

            // Pagination meta should still be present
            if (isset($data['meta'])) {
                expect($data['meta'])->toHaveKey('total');
                expect($data['meta']['total'])->toBe(0);
            }
        });

        it('handles empty invoice list response', function () {
            $response = $this->getJson('/api/v1/invoices');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('data');
            expect($data['data'])->toBeArray();
            expect($data['data'])->toBeEmpty();
        });

        it('handles empty product list response', function () {
            $response = $this->getJson('/api/v1/products');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('data');
            expect($data['data'])->toBeArray();
            expect($data['data'])->toBeEmpty();
        });
    });

    describe('Pagination Edge Cases', function () {

        it('handles first page correctly', function () {
            Quotation::factory()->count(25)->create();

            $response = $this->getJson('/api/v1/quotations?page=1&per_page=10');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('data');
            expect($data)->toHaveKey('meta');
            expect($data['meta'])->toHaveKey('current_page');
            expect($data['meta']['current_page'])->toBe(1);
            expect($data['data'])->toHaveCount(10);
        });

        it('handles last page correctly', function () {
            Quotation::factory()->count(25)->create();

            $response = $this->getJson('/api/v1/quotations?page=3&per_page=10');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('meta');
            expect($data['meta'])->toHaveKey('current_page');
            expect($data['meta']['current_page'])->toBe(3);
            expect($data['data'])->toHaveCount(5); // Last 5 items
        });

        it('handles page beyond total pages', function () {
            Quotation::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/quotations?page=999&per_page=10');

            $response->assertOk();

            $data = $response->json();
            expect($data['data'])->toBeEmpty();
            expect($data['meta']['current_page'])->toBe(999);
        });

        it('handles per_page=1 correctly', function () {
            Quotation::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/quotations?per_page=1');

            $response->assertOk();

            $data = $response->json();
            expect($data['data'])->toHaveCount(1);
            expect($data['meta'])->toHaveKey('per_page');
            expect($data['meta']['per_page'])->toBe(1);
        });

        it('handles large per_page value', function () {
            Quotation::factory()->count(100)->create();

            $response = $this->getJson('/api/v1/quotations?per_page=1000');

            $response->assertOk();

            $data = $response->json();
            // Should respect max per_page limit (usually 100)
            expect($data['data'])->toHaveCount(100);
        });
    });

    describe('Numeric Edge Cases', function () {

        it('handles zero total_amount correctly', function () {
            $quotation = Quotation::factory()->create([
                'subtotal' => 0,
                'tax_amount' => 0,
                'total_amount' => 0,
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('total_amount');
            expect($data['total_amount'])->toBe(0);
            expect($data['total_amount'])->toBeInt();
        });

        it('handles very large total_amount correctly', function () {
            $quotation = Quotation::factory()->create([
                'subtotal' => 999999999999,
                'tax_amount' => 109999999999,
                'total_amount' => 1109999999998,
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('total_amount');
            expect($data['total_amount'])->toBeInt();
            expect($data['total_amount'])->toBeGreaterThan(0);
        });

        it('handles negative amounts correctly (if allowed)', function () {
            // Some systems allow negative amounts for credits/returns
            $invoice = Invoice::factory()->create([
                'subtotal' => -1000000,
                'tax_amount' => -110000,
                'total_amount' => -1110000,
            ]);

            $response = $this->getJson("/api/v1/invoices/{$invoice->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('total_amount');
            // Should handle negative values if business logic allows
            expect($data['total_amount'])->toBeInt();
        });
    });

    describe('String Field Edge Cases', function () {

        it('handles very long quotation numbers', function () {
            $quotation = Quotation::factory()->create([
                'quotation_number' => str_repeat('Q', 200), // Very long number
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('quotation_number');
            expect($data['quotation_number'])->toBeString();
        });

        it('handles special characters in subject', function () {
            $quotation = Quotation::factory()->create([
                'subject' => 'Test "Quotation" with <special> chars & symbols: @#$%',
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('subject');
            expect($data['subject'])->toBeString();
            // Should be properly escaped/encoded
        });

        it('handles unicode characters in contact name', function () {
            $contact = Contact::factory()->create([
                'name' => 'PT. Jaya Makmur 株式会社 🏢',
            ]);

            $response = $this->getJson("/api/v1/contacts/{$contact->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('name');
            expect($data['name'])->toBeString();
        });

        it('handles empty string fields', function () {
            $quotation = Quotation::factory()->create([
                'notes' => '',
                'reference' => '',
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            // Empty strings should still be strings, not null
            if (isset($data['notes'])) {
                expect($data['notes'])->toBeString();
            }
        });
    });

    describe('Date Edge Cases', function () {

        it('handles leap year dates correctly', function () {
            $quotation = Quotation::factory()->create([
                'quotation_date' => '2024-02-29', // Leap year
                'valid_until' => '2024-03-29',
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('quotation_date');
            expect($data['quotation_date'])->toBeString();
        });

        it('handles far future dates', function () {
            $quotation = Quotation::factory()->create([
                'quotation_date' => '2099-12-31',
                'valid_until' => '2100-01-30',
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('quotation_date');
            expect($data['quotation_date'])->toBeString();
        });

        it('handles far past dates', function () {
            $quotation = Quotation::factory()->create([
                'quotation_date' => '2000-01-01',
                'valid_until' => '2000-01-31',
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('quotation_date');
            expect($data['quotation_date'])->toBeString();
        });
    });

    describe('Nested Object Edge Cases', function () {

        it('handles quotation with no items', function () {
            $quotation = Quotation::factory()->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('items');
            expect($data['items'])->toBeArray();
            expect($data['items'])->toBeEmpty();
        });

        it('handles quotation with single item', function () {
            $quotation = Quotation::factory()
                ->has(\App\Models\Sales\QuotationItem::factory()->count(1), 'items')
                ->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('items');
            expect($data['items'])->toBeArray();
            expect($data['items'])->toHaveCount(1);
        });

        it('handles status object with all fields', function () {
            $quotation = Quotation::factory()->submitted()->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data)->toHaveKey('status');
            if (is_array($data['status'])) {
                expect($data['status'])->toHaveKey('value');
                expect($data['status'])->toHaveKey('label');
                expect($data['status'])->toHaveKey('color');
                expect($data['status'])->toHaveKey('is_terminal');
                expect($data['status'])->toHaveKey('is_editable');
            }
        });
    });

    describe('Filter Edge Cases', function () {

        it('handles filter with no results', function () {
            Quotation::factory()->draft()->count(5)->create();

            $response = $this->getJson('/api/v1/quotations?status=approved');

            $response->assertOk();

            $data = $response->json();
            expect($data['data'])->toBeArray();
            expect($data['data'])->toBeEmpty();
        });

        it('handles multiple filters correctly', function () {
            $customer = Contact::factory()->customer()->create();
            Quotation::factory()
                ->forContact($customer)
                ->draft()
                ->count(3)
                ->create();
            Quotation::factory()
                ->forContact($customer)
                ->submitted()
                ->count(2)
                ->create();

            $response = $this->getJson("/api/v1/quotations?contact_id={$customer->id}&status=draft");

            $response->assertOk();

            $data = $response->json();
            expect($data['data'])->toHaveCount(3);
        });

        it('handles invalid filter values gracefully', function () {
            $response = $this->getJson('/api/v1/quotations?status=invalid_status');

            // Should either return empty or validation error
            $response->assertOk(); // Or assertUnprocessable() if validation is strict

            $data = $response->json();
            expect($data)->toHaveKey('data');
        });
    });

    describe('Error Response Edge Cases', function () {

        it('handles 404 with consistent error structure', function () {
            $response = $this->getJson('/api/v1/quotations/999999');

            $response->assertNotFound();

            $data = $response->json();
            expect($data)->toHaveKey('success');
            expect($data['success'])->toBeFalse();
            expect($data)->toHaveKey('message');
            expect($data['message'])->toBeString();
        });

        it('handles 422 validation error with consistent structure', function () {
            $response = $this->postJson('/api/v1/quotations', [
                'contact_id' => 999999, // Invalid contact
            ]);

            $response->assertUnprocessable();

            $data = $response->json();
            expect($data)->toHaveKey('success');
            expect($data['success'])->toBeFalse();
            expect($data)->toHaveKey('message');
            expect($data)->toHaveKey('errors');
        });

        it('handles 401 unauthorized with consistent structure', function () {
            // Note: Testing unauthorized responses requires special setup
            // This is better tested in feature tests with proper auth mocking
            // Skipping here as contract tests focus on response structure when authenticated
            $this->markTestSkipped('Unauthorized response structure tested in feature tests');
        });
    });

    describe('Type Consistency Edge Cases', function () {

        it('ensures total_amount is always integer, never float', function () {
            $quotation = Quotation::factory()->create([
                'total_amount' => 1000000,
            ]);

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data['total_amount'])->toBeInt();
            expect($data['total_amount'])->not->toBeFloat();
        });

        it('ensures id is always integer', function () {
            $quotation = Quotation::factory()->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            expect($data['id'])->toBeInt();
            expect($data['id'])->not->toBeString();
        });

        it('ensures boolean fields are actual booleans', function () {
            $quotation = Quotation::factory()->submitted()->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json('data');
            if (isset($data['status']['is_terminal'])) {
                expect($data['status']['is_terminal'])->toBeBool();
            }
            if (isset($data['status']['is_editable'])) {
                expect($data['status']['is_editable'])->toBeBool();
            }
        });
    });

    describe('Response Structure Edge Cases', function () {

        it('ensures data key always exists in list responses', function () {
            $response = $this->getJson('/api/v1/quotations');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('data');
            // data should never be null, always array
            expect($data['data'])->not->toBeNull();
        });

        it('ensures meta key exists in paginated responses', function () {
            Quotation::factory()->count(5)->create();

            $response = $this->getJson('/api/v1/quotations?per_page=2');

            $response->assertOk();

            $data = $response->json();
            expect($data)->toHaveKey('meta');
            expect($data['meta'])->toBeArray();
        });

        it('handles single resource response structure', function () {
            $quotation = Quotation::factory()->create();

            $response = $this->getJson("/api/v1/quotations/{$quotation->id}");

            $response->assertOk();

            $data = $response->json();
            // Single resource should have 'data' wrapper
            expect($data)->toHaveKey('data');
            expect($data['data'])->toBeArray();
            expect($data['data'])->toHaveKey('id');
        });
    });
});
