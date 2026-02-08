---
pattern: testing
title: "Testing Pattern"
location: tests/
tags: [testing, pest]
---

# Testing Pattern

## AI Agent Quick Reference

**Use this pattern when:**
- Writing new tests
- Understanding test conventions
- Following AC-tagging rules
- Organizing test files

**Key rule:** Use Pest, organize by Epic, tag with User Story references.

---

## Test File Structure

```php
<?php

declare(strict_types=1);

use App\Models\Accounting\Invoice;
use App\Models\Accounting\Contact;
use App\Models\User;
use App\Services\Accounting\InvoiceService;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

// ─────────────────────────────────────────────────────────────
// Setup
// ─────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->contact = Contact::factory()->create();
    $this->actingAs($this->user);
});

// ─────────────────────────────────────────────────────────────
// US-3.1: Create Invoice
// ─────────────────────────────────────────────────────────────

describe('US-3.1: Create Invoice', function () {

    it('[US-3.1][AC1] can create invoice with valid data', function () {
        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $this->contact->id,
            'date' => '2024-01-25',
            'due_date' => '2024-02-25',
            'items' => [
                [
                    'product_id' => Product::factory()->create()->id,
                    'quantity' => 2,
                    'price' => 100000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => ['id', 'number', 'total'],
            ]);

        expect(Invoice::count())->toBe(1);
    });

    it('[US-3.1][AC2] requires at least one line item', function () {
        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $this->contact->id,
            'date' => '2024-01-25',
            'due_date' => '2024-02-25',
            'items' => [],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });

    it('[US-3.1][AC3] validates due date is after invoice date', function () {
        $response = $this->postJson('/api/v1/invoices', [
            'contact_id' => $this->contact->id,
            'date' => '2024-02-25',
            'due_date' => '2024-01-25', // Before invoice date
            'items' => [
                ['product_id' => 1, 'quantity' => 1, 'price' => 100000],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['due_date']);
    });

});

// ─────────────────────────────────────────────────────────────
// US-3.2: Approve Invoice
// ─────────────────────────────────────────────────────────────

describe('US-3.2: Approve Invoice', function () {

    it('[US-3.2][AC1] can approve draft invoice', function () {
        $invoice = Invoice::factory()
            ->draft()
            ->hasItems(2)
            ->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/approve");

        $response->assertOk();

        expect($invoice->fresh())
            ->status->toBe('approved')
            ->approved_at->not->toBeNull();
    });

    it('[US-3.2][AC2] cannot approve already approved invoice', function () {
        $invoice = Invoice::factory()->approved()->create();

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/approve");

        $response->assertBadRequest()
            ->assertJson([
                'error' => [
                    'code' => 'INVOICE_ALREADY_APPROVED',
                ],
            ]);
    });

    it('[US-3.2][AC3] creates journal entry on approval', function () {
        $invoice = Invoice::factory()->draft()->hasItems(1)->create();

        $this->postJson("/api/v1/invoices/{$invoice->id}/approve");

        expect($invoice->journalEntries)->toHaveCount(1);
    });

});
```

---

## Directory Organization

```
tests/
├── Browser/                    # E2E browser tests (Pest v4)
├── Contract/                   # API contract validation tests
│   ├── ApiContractTest.php
│   └── ApiContractEdgeCasesTest.php
├── Feature/
│   ├── Api/V1/                 # API endpoint tests (HTTP/JSON)
│   │   ├── InvoiceApiTest.php
│   │   ├── QuotationApiTest.php
│   │   └── ...
│   ├── Domain/                 # Domain logic tests (state machines, handlers, events)
│   │   ├── Accounting/
│   │   ├── Sales/
│   │   ├── Purchasing/
│   │   ├── Inventory/
│   │   ├── Manufacturing/
│   │   └── ...
│   ├── Services/               # Service layer tests
│   │   ├── Accounting/
│   │   ├── Sales/
│   │   ├── Purchasing/
│   │   ├── Inventory/
│   │   └── ...
│   ├── Integration/            # Cross-module workflow tests
│   ├── Commands/               # Artisan command tests
│   ├── QueryServices/          # Query service tests
│   └── Models/                 # Model relationship/scope tests
│
├── Unit/
│   ├── Domain/                 # Unit tests for domain logic
│   ├── Listeners/              # Event listener unit tests
│   ├── Services/               # Service unit tests
│   ├── Filters/                # Filter unit tests
│   └── Policies/               # Policy unit tests
│
└── Traits/                     # Shared test traits
```

---

## AC-Tagging Convention

When USER_STORIES.md exists for a module:

```php
// Format: [US-{Epic}.{Story}][AC{n}] description
it('[US-3.1][AC1] can create invoice with valid data', function () { });
it('[US-3.1][AC2] requires at least one line item', function () { });
it('[US-3.2][AC1] can approve draft invoice', function () { });
```

Group tests by User Story:

```php
describe('US-3.1: Create Invoice', function () {
    it('[US-3.1][AC1] ...', function () { });
    it('[US-3.1][AC2] ...', function () { });
});
```

---

## Common Patterns

### Authentication Helpers

Use `authenticatedAdmin()` (defined in `tests/Pest.php`) for API tests that need admin permissions:

```php
beforeEach(function () {
    $this->user = authenticatedAdmin();  // Creates user + admin role + Sanctum token
});
```

For tests that only need basic authentication:

```php
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});
```

**Important:** Endpoints protected by Gates (e.g., `Gate::authorize('settings.features')`) require the admin role — use `authenticatedAdmin()`, not plain `actingAs()`.

### Factory Usage

```php
// Simple
$invoice = Invoice::factory()->create();

// With state
$invoice = Invoice::factory()->draft()->create();
$invoice = Invoice::factory()->approved()->create();

// With relationships
$invoice = Invoice::factory()
    ->hasItems(3)
    ->for($contact)
    ->create();
```

### API Testing

```php
// POST with JSON
$this->postJson('/api/v1/invoices', $data)
    ->assertCreated();

// GET with query params
$this->getJson('/api/v1/invoices?filter[status]=draft')
    ->assertOk();

// Auth required
$this->withoutAuth()
    ->getJson('/api/v1/invoices')
    ->assertUnauthorized();
```

### Expectation API

```php
expect($invoice)
    ->status->toBe('approved')
    ->total->toBe(1100000)
    ->items->toHaveCount(3);

expect(Invoice::count())->toBe(1);
```

### Database Assertions

```php
$this->assertDatabaseHas('invoices', [
    'number' => 'INV-202401-0001',
    'status' => 'draft',
]);

$this->assertDatabaseMissing('invoices', [
    'id' => $invoice->id,
]);
```

---

## Test Commands

```bash
# Run all tests
php artisan test

# Run specific file
php artisan test tests/Feature/Invoicing/Epic-1/InvoiceCreationTest.php

# Filter by name
php artisan test --filter="can create invoice"

# Run with coverage
php artisan test --coverage
```

---

## Related Documents

- [ADR-0003: Service Layer Pattern](../08-adr/0003-service-layer-pattern.md)
- [CLAUDE.md Testing Guidelines](../../CLAUDE.md) (AC-Tagging section)

