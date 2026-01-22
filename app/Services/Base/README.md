# Service Layer Base Classes

This directory contains base classes and traits for application services.

## Overview

The service layer is now built using a composable architecture with:
- `BaseService` - Core functionality for all services
- Traits - Composable features that can be added as needed
- `AbstractDocumentService` - Legacy compatibility layer for documents

## BaseService

All application services should extend `BaseService`, which provides core functionality.

### Features Provided

- **Transaction Management** (via `WithTransaction` trait)
  - `executeInTransaction()` - Run operations in DB transaction
  - `execute()` - Run operations without transaction
  - Automatic logging of entry/exit/performance

- **Event Dispatching** (via `WithEventDispatching` trait)
  - `dispatch()` - Dispatch domain events with logging

- **Operation Context** (via `WithOperationContext` trait)
  - `withContext()` - Set explicit context (tests, jobs)
  - `getContext()` - Resolve from container or auth
  - `getUserId()` - Get authenticated user ID
  - `getTenantId()` - Get tenant ID (future multi-tenant)
  - `requireTenantId()` - Get tenant ID or throw exception

- **Error Handling**
  - `fail()` - Create failure result with logging

### Constructor Signature

```php
public function __construct(
    EventDispatcherInterface $eventDispatcher,
    ContextualLoggerInterface $logger
)
```

### Example: Non-Document Service

```php
<?php
namespace App\Services\Accounting;

use App\Services\Base\BaseService;
use App\Models\Accounting\Budget;

class BudgetService extends BaseService
{
    public function createBudget(array $data): Budget
    {
        return $this->executeInTransaction('create_budget', function () use ($data) {
            return Budget::create($data);
        });
    }

    public function addLine(Budget $budget, array $data): BudgetLine
    {
        return $this->executeInTransaction('add_budget_line', function () use ($budget, $data) {
            return $budget->lines()->create($data);
        }, ['budget_id' => $budget->id]);
    }
}
```

## Traits

### WithTransaction

For services that need database transactions.

**Methods:**
- `executeInTransaction(string $operation, callable $callback, array $context = []): mixed`
- `execute(string $operation, callable $callback, array $context = []): mixed`

**Example:**
```php
use App\Services\Base\Traits\WithTransaction;

class MyService
{
    use WithTransaction;

    public function processData(array $data)
    {
        return $this->executeInTransaction('process_data', function () use ($data) {
            // All operations here run in a transaction
            return Model::create($data);
        });
    }
}
```

### WithEventDispatching

For services that dispatch domain events.

**Methods:**
- `dispatch(object $event): void`

**Example:**
```php
use App\Services\Base\Traits\WithEventDispatching;

class MyService
{
    use WithEventDispatching;

    public function completeOrder(Order $order)
    {
        // Business logic...
        $this->dispatch(new OrderCompleted($order));
    }
}
```

### WithOperationContext

For services that need user/tenant context.

**Methods:**
- `withContext(OperationContext $context): static` - Returns cloned service with context
- `getContext(): OperationContext` - Resolve context from container
- `getUserId(): ?int` - Get user ID
- `getTenantId(): ?int` - Get tenant ID
- `requireTenantId(): int` - Get tenant ID or throw

**Example:**
```php
use App\Services\Base\Traits\WithOperationContext;

class MyService
{
    use WithOperationContext;

    public function createRecord(array $data)
    {
        $userId = $this->getUserId();
        $data['created_by'] = $userId;

        return Model::create($data);
    }
}
```

### WithDocuments

For services managing documents (Invoices, Bills, Quotations, etc.).

**Methods:**
- `createDocument(array $data): CreateResult`
- `updateDocument(Model $document, array $data): UpdateResult`
- `deleteDocument(Model $document): DeleteResult`
- `createItems(Model $document, array $items): void`
- `recalculateTotals(Model $document): void`
- `loadRelations(Model $document): Model`
- `validateEditable(Model $document): void`
- `validateDeletable(Model $document): void`

**Required Abstract Methods:**
- `getDocumentNumberField(): string` - e.g., `'invoice_number'`
- `getDocumentNumberPrefix(): string` - e.g., `'INV-202401-'`
- `getDocumentNumberConfig(): array` - e.g., `['table' => 'invoices', 'column' => 'invoice_number']`
- `getItemRelation(): string` - e.g., `'items'`

**Example:**
```php
<?php
namespace App\Services\Sales;

use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithDocuments;

class InvoiceService extends BaseService
{
    use WithDocuments;

    protected function getDocumentNumberField(): string
    {
        return 'invoice_number';
    }

    protected function getDocumentNumberPrefix(): string
    {
        return 'INV-'.now()->format('Ym').'-';
    }

    protected function getDocumentNumberConfig(): array
    {
        return ['table' => 'invoices', 'column' => 'invoice_number'];
    }

    protected function getItemRelation(): string
    {
        return 'items';
    }

    public function create(array $data): Invoice
    {
        return $this->createDocument($data)->getDataOrFail();
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        return $this->updateDocument($invoice, $data)->getDataOrFail();
    }

    public function delete(Invoice $invoice): bool
    {
        return $this->deleteDocument($invoice)->isSuccess();
    }
}
```

## AbstractDocumentService

**@deprecated** Use `BaseService + WithDocuments` trait directly.

This class is maintained for backward compatibility with existing document services.

Document services that extend this class:
- `InvoiceService`
- `DeliveryOrderService`
- `SalesReturnService`
- `BillService`
- `PurchaseOrderService`
- `PurchaseReturnService`

### Migration Path

To update an existing document service:

**Before:**
```php
class MyDocumentService extends AbstractDocumentService
{
    // ...
}
```

**After:**
```php
use App\Services\Base\BaseService;
use App\Services\Base\Traits\WithDocuments;

class MyDocumentService extends BaseService
{
    use WithDocuments;

    // Same abstract methods...
}
```

## Architecture Decisions

### Why Traits Instead of Multiple Inheritance?

PHP doesn't support multiple inheritance. Traits provide:
- Composable functionality (mix and match as needed)
- Single inheritance (cleaner hierarchy)
- Explicit intent (seeing traits tells you what a service does)

### Why Separate Concerns?

- **BaseService** provides core service functionality
- **Traits** provide optional features
- **Services** only include what they need

This reduces coupling and makes code more maintainable.

### Operation Context

The `OperationContext` provides:
- User tracking (who performed the action)
- Tenant tracking (future multi-tenant support)
- Request metadata (IP address, timestamp)

Services resolve context automatically:
1. Explicit context set via `withContext()` (tests, jobs)
2. Container binding (HTTP requests, via `BindOperationContext` middleware)
3. Fallback to `auth()` (edge cases)

## Testing Services

Services are tested using Pest. Example:

```php
<?php
use App\Services\Accounting\BudgetService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(BudgetService::class);
});

it('creates budget', function () {
    $budget = $this->service->createBudget(['name' => 'Test']);

    expect($budget->name)->toBe('Test');
});
```

## Directory Structure

```
app/Services/Base/
├── BaseService.php              # Core service class
├── AbstractDocumentService.php   # Legacy compatibility
├── Traits/                     # Composable traits
│   ├── WithTransaction.php
│   ├── WithEventDispatching.php
│   ├── WithOperationContext.php
│   └── WithDocuments.php
└── README.md                   # This file
```

## Best Practices

1. **Extend BaseService** for all new services
2. **Use traits** for composable features
3. **Use executeInTransaction()** for write operations
4. **Use execute()** for read operations or nested transactions
5. **Use dispatch()** for domain events
6. **Use getContext()** for user/tenant information
7. **Throw exceptions** for business rule violations (don't return failure results)
8. **Return models** from public methods (let layers above handle wrapping)

## Migration Guide

### For New Services

```php
// ✅ DO THIS
class MyService extends BaseService
{
    // Use traits as needed
}

// ❌ DON'T DO THIS
class MyService extends AbstractApplicationService
```

### For Existing Services Extending AbstractApplicationService

```php
// Before
class MyService extends AbstractApplicationService
{
    public function __construct(...) {
        parent::__construct(...);
    }
}

// After
class MyService extends BaseService
{
    // Constructor is optional, uses parent automatically
}
```

### For Document Services

```php
// Before (still works, but deprecated)
class InvoiceService extends AbstractDocumentService
{
    // ...
}

// After
class InvoiceService extends BaseService
{
    use WithDocuments;

    // Same abstract methods as before...
}
```

## Troubleshooting

### Class not found: `BaseService`

Run: `composer dump-autoload`

### Missing method error

Ensure you're using the right traits:
- Need transactions? Use `WithTransaction`
- Need events? Use `WithEventDispatching`
- Need context? Use `WithOperationContext`
- Need document features? Use `WithDocuments`

### Test failures after migration

Check:
1. Constructor signature - should match `BaseService` if overriding
2. Use statements - import from `App\Services\Base\*`
3. Parent constructor call - not needed when extending `BaseService`
