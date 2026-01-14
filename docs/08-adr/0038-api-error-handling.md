---
adr: "0038"
title: "API Error Handling"
status: accepted
date: 2024-11-15
deciders: [Product Team]
tags: [api, errors]
related_adrs: [0034]
related_modules: [api]
impact: medium
---

# ADR-0038: API Error Handling

## AI Agent Quick Reference

**Use this ADR when:**
- Handling API exceptions
- Creating error responses
- Implementing validation errors
- Understanding error codes

**Key takeaway:** Consistent JSON error format with error code, message, and optional details.

---

## Decision

Use consistent JSON error response format with HTTP status codes and machine-readable error codes.

---

## Context

API errors need:
1. Consistent format
2. Helpful error messages
3. Machine-readable codes
4. Validation error details

---

## Implementation

### Error Response Format

```json
{
    "error": {
        "code": "INVOICE_NOT_FOUND",
        "message": "The requested invoice was not found.",
        "details": null
    }
}
```

### Validation Error Format

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "The given data was invalid.",
        "details": {
            "contact_id": ["The contact id field is required."],
            "date": ["The date must be a valid date."]
        }
    }
}
```

### HTTP Status Codes

| Code | When to Use |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 204 | No Content (delete) |
| 400 | Bad Request |
| 401 | Unauthenticated |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 500 | Server Error |

### Exception Handler

```php
// bootstrap/app.php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (Throwable $e, Request $request) {
        if ($request->expectsJson()) {
            return app(ApiExceptionHandler::class)->render($e);
        }
    });
})
```

### API Exception Handler

```php
// app/Exceptions/ApiExceptionHandler.php
class ApiExceptionHandler
{
    public function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => $this->validationError($e),
            $e instanceof ModelNotFoundException => $this->notFound($e),
            $e instanceof AuthenticationException => $this->unauthenticated(),
            $e instanceof AuthorizationException => $this->forbidden($e),
            $e instanceof BusinessException => $this->businessError($e),
            default => $this->serverError($e),
        };
    }

    private function validationError(ValidationException $e): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'The given data was invalid.',
                'details' => $e->errors(),
            ],
        ], 422);
    }

    private function notFound(ModelNotFoundException $e): JsonResponse
    {
        $model = class_basename($e->getModel());

        return response()->json([
            'error' => [
                'code' => strtoupper($model) . '_NOT_FOUND',
                'message' => "The requested {$model} was not found.",
                'details' => null,
            ],
        ], 404);
    }

    private function businessError(BusinessException $e): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'details' => $e->getDetails(),
            ],
        ], 400);
    }
}
```

### Business Exception

```php
// app/Exceptions/BusinessException.php
class BusinessException extends Exception
{
    public function __construct(
        protected string $errorCode,
        string $message,
        protected ?array $details = null
    ) {
        parent::__construct($message);
    }

    public function getCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }
}

// Usage
throw new BusinessException(
    'INSUFFICIENT_STOCK',
    'Not enough stock to fulfill this order.',
    ['product_id' => 5, 'available' => 10, 'requested' => 15]
);
```

### Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| VALIDATION_ERROR | 422 | Input validation failed |
| INVOICE_NOT_FOUND | 404 | Invoice doesn't exist |
| INSUFFICIENT_STOCK | 400 | Not enough inventory |
| PERIOD_CLOSED | 400 | Fiscal period is closed |
| JOURNAL_UNBALANCED | 400 | Debits ≠ Credits |
| DUPLICATE_ENTRY | 400 | Record already exists |

---

## References

- [ADR-0034: API Versioning](./0034-api-versioning.md)
- [API Design](../01-architecture/api-design.md)

