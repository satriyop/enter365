# Exception Error Codes Reference

All domain exceptions with their error codes, context parameters, and usage.

---

## Exception Hierarchy

```
DomainException (abstract base)
├── BusinessRuleException
├── DocumentLockedException
├── EntityNotFoundException
├── InsufficientStockException
├── MissingAccountException
├── StateTransitionException
└── ValidationException
```

---

## DomainException (Base)

**Location:** `app/Exceptions/Domain/DomainException.php`

Base class for all domain exceptions. Provides:
- Error code
- HTTP status code
- Context array
- JSON response formatting

```php
use App\Exceptions\Domain\DomainException;

// All domain exceptions extend this
throw new DomainException(
    message: 'Something went wrong',
    code: 'DOMAIN_ERROR',
    context: ['key' => 'value'],
    statusCode: 422
);
```

---

## ValidationException

**Code:** `VALIDATION_ERROR`
**HTTP Status:** 422

For business rule validation failures (not form validation).

### Context Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `errors` | array | Field → error message mapping |

### Usage

```php
use App\Exceptions\Domain\ValidationException;

throw new ValidationException(
    message: 'Invoice must have at least one item.',
    errors: ['items' => 'At least one item is required']
);

// Or with static constructor
throw ValidationException::withErrors([
    'items' => 'At least one item is required',
    'contact_id' => 'Customer is required'
]);
```

### JSON Response

```json
{
    "code": "VALIDATION_ERROR",
    "message": "Invoice must have at least one item.",
    "errors": {
        "items": "At least one item is required"
    }
}
```

---

## StateTransitionException

**Code:** `INVALID_STATE_TRANSITION`
**HTTP Status:** 422

For invalid document workflow state transitions.

### Context Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `entity` | string | Entity type (Invoice, WorkOrder, etc.) |
| `current_state` | string | Current status |
| `target_state` | string | Attempted target status |

### Usage

```php
use App\Exceptions\Domain\StateTransitionException;

// Invalid transition
throw StateTransitionException::invalidTransition(
    entity: 'Faktur',           // Indonesian entity name
    currentState: 'draft',
    targetState: 'paid'
);

// Action not available
throw StateTransitionException::actionNotAvailable(
    action: 'kirim',            // Indonesian action
    currentState: 'cancelled',
    reason: 'Faktur yang dibatalkan tidak dapat dikirim.'
);

// Generic
throw new StateTransitionException(
    entity: 'Invoice',
    currentState: 'draft',
    targetState: 'completed',
    message: 'Work order must be started before completion.'
);
```

### JSON Response

```json
{
    "code": "INVALID_STATE_TRANSITION",
    "message": "Cannot transition Faktur from draft to paid",
    "context": {
        "entity": "Faktur",
        "current_state": "draft",
        "target_state": "paid"
    }
}
```

---

## DocumentLockedException

**Code:** `DOCUMENT_LOCKED`
**HTTP Status:** 422

For attempts to modify locked/posted documents.

### Context Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `document_type` | string | Type of document |
| `document_id` | int | Document ID |

### Usage

```php
use App\Exceptions\Domain\DocumentLockedException;

throw new DocumentLockedException(
    documentType: 'Invoice',
    documentId: $invoice->id,
    message: 'Posted invoices cannot be modified.'
);

// Or with static constructor
throw DocumentLockedException::cannotModify(
    documentType: 'Journal Entry',
    documentId: $entry->id,
    reason: 'Entries in closed periods cannot be modified.'
);
```

### JSON Response

```json
{
    "code": "DOCUMENT_LOCKED",
    "message": "Posted invoices cannot be modified.",
    "context": {
        "document_type": "Invoice",
        "document_id": 123
    }
}
```

---

## InsufficientStockException

**Code:** `INSUFFICIENT_STOCK`
**HTTP Status:** 422

For inventory availability failures.

### Context Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `product_id` | int | Product ID |
| `product_name` | string | Product name |
| `product_sku` | string | Product SKU |
| `requested` | float | Quantity requested |
| `available` | float | Quantity available |
| `shortage` | float | Shortage amount |
| `warehouse_id` | int | Warehouse ID |
| `warehouse_name` | string | Warehouse name |

### Usage

```php
use App\Exceptions\Domain\InsufficientStockException;

throw new InsufficientStockException(
    productId: $product->id,
    productName: $product->name,
    productSku: $product->sku,
    requested: 100,
    available: 50,
    warehouseId: $warehouse->id,
    warehouseName: $warehouse->name
);

// Or with static constructor
throw InsufficientStockException::forProduct(
    product: $product,
    requested: 100,
    available: 50,
    warehouse: $warehouse
);
```

### JSON Response

```json
{
    "code": "INSUFFICIENT_STOCK",
    "message": "Stok tidak mencukupi untuk transfer.",
    "context": {
        "product_id": 123,
        "product_name": "MCB 16A",
        "product_sku": "MCB-16A-001",
        "requested": 100,
        "available": 50,
        "shortage": 50,
        "warehouse_id": 1,
        "warehouse_name": "Gudang Utama"
    }
}
```

---

## MissingAccountException

**Code:** `MISSING_ACCOUNT`
**HTTP Status:** 422

For missing chart of accounts entries.

### Context Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `account_type` | string | Type of account needed |
| `account_code` | string | Expected account code |

### Usage

```php
use App\Exceptions\Domain\MissingAccountException;

throw new MissingAccountException(
    accountType: 'Accounts Receivable',
    accountCode: '1-1200',
    message: 'Receivable account not configured for invoices.'
);

// Or with static constructor
throw MissingAccountException::forType(
    accountType: 'COGS',
    context: 'Invoice posting'
);
```

### JSON Response

```json
{
    "code": "MISSING_ACCOUNT",
    "message": "Receivable account not configured for invoices.",
    "context": {
        "account_type": "Accounts Receivable",
        "account_code": "1-1200"
    }
}
```

---

## BusinessRuleException

**Code:** `BUSINESS_RULE_VIOLATION`
**HTTP Status:** 409

For business rule violations that are not form validation errors.

### Usage

```php
use App\Exceptions\Domain\BusinessRuleException;

throw new BusinessRuleException(
    message: 'Pesanan pembelian sudah memiliki penerimaan barang.'
);
```

---

## EntityNotFoundException

**Code:** `ENTITY_NOT_FOUND`
**HTTP Status:** 404

For entity not found errors with domain context.

### Usage

```php
use App\Exceptions\Domain\EntityNotFoundException;

// Use constructor (NOT static factory - see SKILL.md Gotcha #20)
throw new EntityNotFoundException('Quotation', $id);
```

---

## When to Use Each Exception

| Scenario | Exception |
|----------|-----------|
| Business rule violated | `BusinessRuleException` |
| Form/data validation failed | `ValidationException` |
| Invalid workflow transition | `StateTransitionException` |
| Trying to edit posted document | `DocumentLockedException` |
| Not enough inventory | `InsufficientStockException` |
| Account not found/configured | `MissingAccountException` |
| Entity not found | `EntityNotFoundException` |

---

## Exception Handler Response

All domain exceptions are automatically handled by Laravel's exception handler:

```php
// In app/Exceptions/Handler.php or bootstrap/app.php

$exceptions->render(function (DomainException $e, Request $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'code' => $e->getErrorCode(),
            'message' => $e->getMessage(),
            'context' => $e->getContext(),
        ], $e->getStatusCode());
    }
});
```

---

## Testing Exceptions

```php
use App\Exceptions\Domain\StateTransitionException;
use App\Exceptions\Domain\InsufficientStockException;

it('throws StateTransitionException on invalid transition', function () {
    $invoice = Invoice::factory()->cancelled()->create();

    expect(fn() => $service->send($invoice))
        ->toThrow(StateTransitionException::class);
});

it('throws InsufficientStockException when stock is low', function () {
    $product = Product::factory()->outOfStock()->create();

    expect(fn() => $service->transfer($product, 100))
        ->toThrow(InsufficientStockException::class);
});

it('includes correct context in exception', function () {
    try {
        // ... action that throws
    } catch (InsufficientStockException $e) {
        expect($e->getContext())->toHaveKey('product_id');
        expect($e->getContext()['shortage'])->toBe(50);
    }
});
```
