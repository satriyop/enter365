# Testing Patterns Guide

Common Pest testing patterns for Enter365.

---

## Test File Structure

```php
<?php

declare(strict_types=1);

use App\Enums\DocumentStatus;
use App\Models\Sales\Invoice;
use App\Services\Sales\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('InvoiceService', function () {

    beforeEach(function () {
        // Setup code runs before each test
        $this->user = User::factory()->create();
        $this->service = app(InvoiceServiceInterface::class);
    });

    describe('create', function () {

        it('creates invoice with draft status', function () {
            $data = [
                'contact_id' => Contact::factory()->customer()->create()->id,
                'invoice_date' => now(),
                'due_date' => now()->addDays(30),
            ];

            $invoice = $this->service->create($data);

            expect($invoice)->toBeInstanceOf(Invoice::class);
            expect($invoice->status)->toBe(DocumentStatus::Draft);
        });

        it('requires contact_id', function () {
            expect(fn() => $this->service->create([]))
                ->toThrow(ValidationException::class);
        });
    });

    describe('send', function () {

        it('transitions to sent status', function () {
            $invoice = Invoice::factory()
                ->draft()
                ->has(InvoiceItem::factory())
                ->create();

            $result = $this->service->send($invoice);

            expect($result->status)->toBe(DocumentStatus::Sent);
        });

        it('throws exception when invoice has no items', function () {
            $invoice = Invoice::factory()->draft()->create();

            expect(fn() => $this->service->send($invoice))
                ->toThrow(StateTransitionException::class);
        });
    });
});
```

---

## Common Test Patterns

### Authentication

```php
use Laravel\Sanctum\Sanctum;

// Authenticate user for API tests
Sanctum::actingAs($user);

// Or in HTTP tests
$this->actingAs($user);

// With specific abilities
Sanctum::actingAs($user, ['invoice:create', 'invoice:send']);
```

### Factory Setup Patterns

```php
// Create with specific status
$invoice = Invoice::factory()->paid()->create();

// Create with relationships
$invoice = Invoice::factory()
    ->has(InvoiceItem::factory()->count(3), 'items')
    ->forContact($customer)
    ->create();

// Create multiple with states
$drafts = Invoice::factory()->draft()->count(3)->create();
$sents = Invoice::factory()->sent()->count(2)->create();

// Chaining multiple modifiers
$quotation = Quotation::factory()
    ->submitted()
    ->highPriority()
    ->withFollowUp(3)
    ->forContact($customer)
    ->create();
```

### Testing Status Transitions

```php
describe('status transitions', function () {

    it('can transition from draft to sent', function () {
        $invoice = Invoice::factory()->draft()->create();

        $result = $this->service->send($invoice);

        expect($result->status)->toBe(DocumentStatus::Sent);
    });

    it('cannot transition from cancelled', function () {
        $invoice = Invoice::factory()->cancelled()->create();

        expect(fn() => $this->service->send($invoice))
            ->toThrow(StateTransitionException::class);
    });
});
```

### Testing with Events

```php
use Illuminate\Support\Facades\Event;

it('dispatches InvoiceSent event', function () {
    Event::fake();

    $invoice = Invoice::factory()->draft()
        ->has(InvoiceItem::factory())
        ->create();

    $this->service->send($invoice);

    Event::assertDispatched(InvoiceSent::class, function ($event) use ($invoice) {
        return $event->invoiceId === $invoice->id;
    });
});

it('dispatches multiple events', function () {
    Event::fake([InvoiceSent::class, InvoiceStatusChanged::class]);

    // ... action

    Event::assertDispatched(InvoiceSent::class);
    Event::assertDispatched(InvoiceStatusChanged::class);
});
```

### Testing Calculations

```php
it('calculates totals correctly', function () {
    $invoice = Invoice::factory()
        ->has(InvoiceItem::factory()->state([
            'unit_price' => 100_00, // Rp 100.00
            'quantity' => 2,
        ]))
        ->create(['include_tax' => true]);

    $totals = InvoiceTotals::calculate($invoice);

    expect($totals->subtotal)->toBe(200_00);
    expect($totals->tax)->toBe(22_00);  // 11%
    expect($totals->total)->toBe(222_00);
});
```

### Testing API Endpoints

```php
it('returns paginated invoices', function () {
    Sanctum::actingAs(User::factory()->create());

    Invoice::factory()->count(30)->create();

    $response = $this->getJson('/api/v1/invoices');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'invoice_number', 'status', 'total_amount']
            ],
            'meta' => ['current_page', 'total']
        ]);
});

it('filters by status', function () {
    Sanctum::actingAs(User::factory()->create());

    Invoice::factory()->draft()->count(3)->create();
    Invoice::factory()->sent()->count(2)->create();

    $response = $this->getJson('/api/v1/invoices?status=draft');

    $response->assertOk();
    expect($response->json('meta.total'))->toBe(3);
});

it('creates invoice with validation', function () {
    Sanctum::actingAs(User::factory()->create());

    $contact = Contact::factory()->customer()->create();

    $response = $this->postJson('/api/v1/invoices', [
        'contact_id' => $contact->id,
        'invoice_date' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(30)->format('Y-m-d'),
        'items' => [
            ['product_id' => Product::factory()->create()->id, 'quantity' => 2, 'unit_price' => 10000],
        ],
    ]);

    $response->assertCreated();
    expect(Invoice::count())->toBe(1);
});
```

### Testing Form Requests

```php
it('validates required fields', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/invoices', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['contact_id', 'invoice_date']);
});

it('validates contact exists', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/v1/invoices', [
        'contact_id' => 99999,
    ]);

    $response->assertJsonValidationErrors(['contact_id']);
});
```

### Testing Exceptions

```php
it('throws InsufficientStockException', function () {
    $product = Product::factory()->outOfStock()->create();

    expect(fn() => $this->service->transfer($product, 100))
        ->toThrow(InsufficientStockException::class);
});

it('returns correct error context', function () {
    $product = Product::factory()->create();

    try {
        $this->service->transfer($product, 100);
    } catch (InsufficientStockException $e) {
        expect($e->getContext())->toHaveKey('product_id');
        expect($e->getContext()['shortage'])->toBeGreaterThan(0);
    }
});
```

### Testing with Database Assertions

```php
it('creates journal entry', function () {
    $invoice = Invoice::factory()->draft()
        ->has(InvoiceItem::factory())
        ->create();

    $this->service->send($invoice);

    $this->assertDatabaseHas('journal_entries', [
        'source_type' => 'invoice',
        'source_id' => $invoice->id,
    ]);
});

it('soft deletes invoice', function () {
    $invoice = Invoice::factory()->draft()->create();

    $this->service->delete($invoice);

    $this->assertSoftDeleted('invoices', ['id' => $invoice->id]);
});
```

---

## Using Datasets

```php
dataset('invoice_statuses', [
    'draft' => [DocumentStatus::Draft],
    'sent' => [DocumentStatus::Sent],
    'partial' => [DocumentStatus::Partial],
    'paid' => [DocumentStatus::Paid],
]);

it('can be loaded with any status', function (DocumentStatus $status) {
    $invoice = Invoice::factory()->create(['status' => $status]);

    expect($invoice->status)->toBe($status);
})->with('invoice_statuses');

// Inline dataset
it('validates amount limits', function (int $amount, bool $shouldPass) {
    // ...
})->with([
    'minimum' => [1, true],
    'maximum' => [1000000000, true],
    'zero' => [0, false],
    'negative' => [-1, false],
]);
```

---

## Performance Tips

### Use Specific Factories

```php
// Slow: Creates unnecessary relationships
$invoice = Invoice::factory()
    ->has(InvoiceItem::factory()->count(10))
    ->create();

// Fast: Only what you need
$invoice = Invoice::factory()->draft()->create();
```

### Use Database Transactions

```php
uses(RefreshDatabase::class);  // Uses transactions, fast

// Or manually for specific tests
beforeEach(function () {
    DB::beginTransaction();
});

afterEach(function () {
    DB::rollBack();
});
```

### Avoid Excessive Event Listeners

```php
// Disable events when not testing them
Event::fake();  // Prevents all listeners from running

// Or disable specific events
Event::fake([InvoiceSent::class]);
```

---

## Testing with OperationContext

Services that extend `AbstractApplicationService` support explicit user context injection via `withContext()`. This enables unit testing without mocking Laravel's auth system.

### Using TestsWithOperationContext Trait

```php
use Tests\Traits\TestsWithOperationContext;

uses(TestsWithOperationContext::class);
uses(RefreshDatabase::class);

describe('QuotationService', function () {

    it('creates quotation with specific user', function () {
        $service = app(QuotationServiceInterface::class);

        // Inject explicit user context
        $quotation = $this->withUserContext($service, userId: 42)
            ->create($data);

        expect($quotation->created_by)->toBe(42);
    });

    it('runs as system context for jobs', function () {
        $service = app(QuotationService::class);

        // System context (no authenticated user)
        $result = $this->withSystemContext($service)
            ->markExpired();

        expect($result)->toBeGreaterThanOrEqual(0);
    });

    it('simulates job with dispatcher user', function () {
        $service = app(InvoiceServiceInterface::class);

        // Job context with user who dispatched
        $result = $this->withJobContext($service, dispatchedBy: 123, jobName: 'ProcessInvoice')
            ->updatePaymentStatus($invoice);
    });
});
```

### Available Helper Methods

| Method | Purpose |
|--------|---------|
| `withUserContext($service, $userId)` | Inject specific user context |
| `withSystemContext($service)` | Inject system context (jobs, commands) |
| `withJobContext($service, $dispatchedBy, $jobName)` | Inject job context |
| `withContext($service, $context)` | Inject custom OperationContext |
| `createUserContext($userId)` | Create context without injecting |
| `createSystemContext()` | Create system context |

### Direct OperationContext Usage

```php
use App\Support\OperationContext;

it('uses operation context directly', function () {
    $context = OperationContext::forUser(42)
        ->withMetadata(['request_id' => 'test-123']);

    $service = app(QuotationServiceInterface::class)
        ->withContext($context);

    $quotation = $service->create($data);

    expect($quotation->created_by)->toBe(42);
});
```

### When to Use OperationContext in Tests

| Scenario | Use |
|----------|-----|
| Testing service logic with specific user | `withUserContext($service, $userId)` |
| Testing job/command behavior | `withSystemContext($service)` |
| Testing audit trail | `withUserContext()` + assert `created_by` |
| Testing without auth mocking | Any `with*Context()` method |

---

## Unit Testing with In-Memory Repositories

For true unit tests that don't hit the database, use in-memory repositories. This is particularly valuable for testing service logic in isolation.

### When to Use In-Memory vs Feature Tests

| Scenario | Test Type | Why |
|----------|-----------|-----|
| Service business logic | Unit + In-Memory Repository | Fast, isolated |
| Database interactions | Feature + RefreshDatabase | Tests actual queries |
| API endpoints | Feature + RefreshDatabase | Tests full stack |
| State transitions | Both | Unit for logic, Feature for persistence |

### Using InMemoryQuotationRepository

```php
<?php

use App\Services\Sales\DocumentBasedQuotationService;
use Tests\Support\InMemoryQuotationRepository;
use App\Contracts\Events\EventDispatcherInterface;
use App\Domain\Shared\Events\NullEventDispatcher;

describe('QuotationService Unit Tests', function () {

    beforeEach(function () {
        $this->repository = new InMemoryQuotationRepository;
        $this->eventDispatcher = new NullEventDispatcher;

        $this->service = new DocumentBasedQuotationService(
            $this->repository,
            $this->eventDispatcher,
            // ... other dependencies
        );
    });

    afterEach(function () {
        $this->repository->reset();  // Clean state between tests
    });

    it('finds quotations by status without database', function () {
        // Arrange - seed repository with test data
        $this->repository->create([
            'contact_id' => 1,
            'status' => DocumentStatus::Draft,
        ]);
        $this->repository->create([
            'contact_id' => 2,
            'status' => DocumentStatus::Submitted,
        ]);

        // Act
        $drafts = $this->repository->findByStatus(DocumentStatus::Draft);

        // Assert
        expect($drafts)->toHaveCount(1);
    });

    it('can pre-seed specific scenarios', function () {
        $existing = new Quotation(['contact_id' => 1]);
        $existing->id = 100;
        $existing->status = DocumentStatus::Approved;

        $this->repository->seed([$existing]);

        $found = $this->repository->find(100);
        expect($found)->not->toBeNull();
        expect($found->status)->toBe(DocumentStatus::Approved);
    });
});
```

### Helper Methods for Testing

| Method | Purpose |
|--------|---------|
| `reset()` | Clear all data, reset ID counter |
| `seed(array $entities)` | Pre-populate with specific entities |
| `getCollection()` | Direct access for custom assertions |
| `count(array $criteria)` | Count without database |

### Binding In-Memory Repository in Tests

```php
beforeEach(function () {
    $repository = new InMemoryQuotationRepository;

    // Replace binding for this test
    $this->app->instance(
        QuotationRepositoryInterface::class,
        $repository
    );

    $this->repository = $repository;
    $this->service = app(QuotationServiceInterface::class);
});
```

### Gotcha: Specifications Don't Work In-Memory

Specifications use Eloquent Builder and won't work with in-memory repositories:

```php
// ❌ This will throw RuntimeException
$this->repository->match(new ActiveQuotationsSpecification);

// ✅ Use findBy() with explicit criteria instead
$this->repository->findBy(['status' => DocumentStatus::Draft]);
```

See: [REPOSITORIES.md](REPOSITORIES.md#in-memory-repository-for-unit-tests) for full implementation details.

---

## Test Organization

```
tests/
├── Feature/
│   ├── Sales/
│   │   ├── InvoiceServiceTest.php
│   │   ├── InvoiceControllerTest.php
│   │   ├── QuotationServiceTest.php
│   │   └── QuotationControllerTest.php
│   ├── Purchasing/
│   │   ├── BillServiceTest.php
│   │   └── PurchaseOrderServiceTest.php
│   └── Manufacturing/
│       ├── BomServiceTest.php
│       └── WorkOrderServiceTest.php
├── Unit/
│   ├── Domain/
│   │   ├── InvoiceStateMachineTest.php
│   │   └── InvoiceCalculatorTest.php
│   └── Services/
│       └── InvoiceServiceTest.php
└── Pest.php
```

---

## Running Tests

```bash
# All tests
php artisan test

# Specific file
php artisan test tests/Feature/Sales/InvoiceServiceTest.php

# Filter by name
php artisan test --filter=InvoiceService

# Filter by description
php artisan test --filter="creates invoice"

# Parallel execution
php artisan test --parallel

# With coverage
php artisan test --coverage
```
