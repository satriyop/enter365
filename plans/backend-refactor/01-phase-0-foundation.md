# Phase 0: Foundation & Observability

> **Goal**: Establish observability infrastructure before making any other changes. You can't improve what you can't measure.

## Why First?

1. **Debugging Aid**: Better logging helps catch issues during refactoring
2. **Baseline Metrics**: Know current performance before changes
3. **Low Risk**: Additive changes, no breaking changes
4. **Immediate Value**: Benefits the team immediately

---

## Deliverables

- [ ] Structured logging infrastructure
- [ ] Exception handling standardization
- [ ] Basic metrics collection
- [ ] Health check endpoints
- [ ] Request/Response logging middleware

---

## Part 1: Structured Logging Infrastructure

### 1.1 Create Logging Contract

```php
<?php
// File: app/Contracts/Logging/ContextualLoggerInterface.php

declare(strict_types=1);

namespace App\Contracts\Logging;

interface ContextualLoggerInterface
{
    /**
     * Log service method entry.
     *
     * @param array<string, mixed> $parameters
     */
    public function logEntry(string $class, string $method, array $parameters = []): void;

    /**
     * Log service method exit.
     *
     * @param mixed $result
     */
    public function logExit(string $class, string $method, mixed $result = null): void;

    /**
     * Log domain event.
     */
    public function logDomainEvent(object $event): void;

    /**
     * Log business operation.
     *
     * @param array<string, mixed> $context
     */
    public function logOperation(string $operation, array $context = []): void;

    /**
     * Log error with context.
     *
     * @param array<string, mixed> $context
     */
    public function logError(\Throwable $exception, array $context = []): void;

    /**
     * Log performance metrics.
     *
     * @param array<string, mixed> $metrics
     */
    public function logPerformance(string $operation, float $duration, array $metrics = []): void;
}
```

### 1.2 Create Laravel Implementation

```php
<?php
// File: app/Infrastructure/Logging/LaravelContextualLogger.php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Logging\ContextualLoggerInterface;
use Illuminate\Support\Facades\Log;

class LaravelContextualLogger implements ContextualLoggerInterface
{
    private array $globalContext = [];

    public function __construct()
    {
        $this->globalContext = [
            'app_version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];
    }

    public function logEntry(string $class, string $method, array $parameters = []): void
    {
        Log::debug('Service method entry', [
            ...$this->globalContext,
            'class' => $class,
            'method' => $method,
            'parameters' => $this->sanitizeParameters($parameters),
            'user_id' => auth()->id(),
            'request_id' => request()->header('X-Request-ID'),
        ]);
    }

    public function logExit(string $class, string $method, mixed $result = null): void
    {
        Log::debug('Service method exit', [
            ...$this->globalContext,
            'class' => $class,
            'method' => $method,
            'result_type' => $result !== null ? get_debug_type($result) : 'null',
            'user_id' => auth()->id(),
        ]);
    }

    public function logDomainEvent(object $event): void
    {
        Log::info('Domain event fired', [
            ...$this->globalContext,
            'event_class' => get_class($event),
            'event_data' => $this->extractEventData($event),
            'user_id' => auth()->id(),
        ]);
    }

    public function logOperation(string $operation, array $context = []): void
    {
        Log::info("Operation: {$operation}", [
            ...$this->globalContext,
            ...$context,
            'user_id' => auth()->id(),
        ]);
    }

    public function logError(\Throwable $exception, array $context = []): void
    {
        Log::error($exception->getMessage(), [
            ...$this->globalContext,
            'exception_class' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            ...$context,
            'user_id' => auth()->id(),
        ]);
    }

    public function logPerformance(string $operation, float $duration, array $metrics = []): void
    {
        Log::info("Performance: {$operation}", [
            ...$this->globalContext,
            'duration_ms' => round($duration * 1000, 2),
            'metrics' => $metrics,
            'user_id' => auth()->id(),
        ]);
    }

    /**
     * Sanitize parameters to avoid logging sensitive data.
     */
    private function sanitizeParameters(array $parameters): array
    {
        $sensitive = ['password', 'token', 'secret', 'key', 'authorization'];

        return collect($parameters)->map(function ($value, $key) use ($sensitive) {
            if (in_array(strtolower($key), $sensitive)) {
                return '[REDACTED]';
            }
            if (is_object($value)) {
                return get_class($value) . ':' . ($value->id ?? 'new');
            }
            return $value;
        })->toArray();
    }

    /**
     * Extract data from domain event for logging.
     */
    private function extractEventData(object $event): array
    {
        $reflection = new \ReflectionClass($event);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($event);

            if ($value instanceof \DateTimeInterface) {
                $data[$property->getName()] = $value->format('c');
            } elseif (is_scalar($value) || is_null($value)) {
                $data[$property->getName()] = $value;
            } else {
                $data[$property->getName()] = get_debug_type($value);
            }
        }

        return $data;
    }
}
```

### 1.3 Create Null Logger for Testing

```php
<?php
// File: app/Infrastructure/Logging/NullContextualLogger.php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Logging\ContextualLoggerInterface;

/**
 * Null implementation for testing - logs nothing.
 */
class NullContextualLogger implements ContextualLoggerInterface
{
    public function logEntry(string $class, string $method, array $parameters = []): void {}
    public function logExit(string $class, string $method, mixed $result = null): void {}
    public function logDomainEvent(object $event): void {}
    public function logOperation(string $operation, array $context = []): void {}
    public function logError(\Throwable $exception, array $context = []): void {}
    public function logPerformance(string $operation, float $duration, array $metrics = []): void {}
}
```

### 1.4 Register in Service Provider

```php
// Add to app/Providers/AppServiceProvider.php in registerInfrastructureServices()

$this->app->singleton(
    \App\Contracts\Logging\ContextualLoggerInterface::class,
    \App\Infrastructure\Logging\LaravelContextualLogger::class
);
```

---

## Part 2: Exception Handling Standardization

### 2.1 Create Base Domain Exception

```php
<?php
// File: app/Exceptions/Domain/DomainException.php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;

/**
 * Base exception for all domain-specific errors.
 *
 * Domain exceptions are expected errors from business rule violations.
 * They should be caught and converted to appropriate API responses.
 */
abstract class DomainException extends Exception
{
    protected string $errorCode;
    protected array $context = [];

    public function __construct(
        string $message,
        string $errorCode = 'DOMAIN_ERROR',
        array $context = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->errorCode = $errorCode;
        $this->context = $context;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * HTTP status code for API responses.
     */
    abstract public function getStatusCode(): int;

    /**
     * Convert to array for API response.
     */
    public function toArray(): array
    {
        return [
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
```

### 2.2 Create Specific Domain Exceptions

```php
<?php
// File: app/Exceptions/Domain/ValidationException.php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class ValidationException extends DomainException
{
    public function __construct(string $message, array $errors = [])
    {
        parent::__construct($message, 'VALIDATION_ERROR', ['errors' => $errors]);
    }

    public function getStatusCode(): int
    {
        return 422;
    }

    public static function fromErrors(array $errors): self
    {
        return new self('Data tidak valid.', $errors);
    }
}
```

```php
<?php
// File: app/Exceptions/Domain/BusinessRuleException.php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class BusinessRuleException extends DomainException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 'BUSINESS_RULE_VIOLATION', $context);
    }

    public function getStatusCode(): int
    {
        return 409; // Conflict
    }

    public static function insufficientStock(string $product, float $required, float $available): self
    {
        return new self(
            "Stok tidak cukup untuk {$product}. Dibutuhkan: {$required}, Tersedia: {$available}",
            ['product' => $product, 'required' => $required, 'available' => $available]
        );
    }

    public static function creditLimitExceeded(string $contact, int $limit, int $outstanding): self
    {
        return new self(
            "Limit kredit terlampaui untuk {$contact}. Limit: {$limit}, Outstanding: {$outstanding}",
            ['contact' => $contact, 'limit' => $limit, 'outstanding' => $outstanding]
        );
    }

    public static function fiscalPeriodClosed(string $period): self
    {
        return new self(
            "Periode fiskal {$period} sudah ditutup. Tidak bisa posting transaksi.",
            ['period' => $period]
        );
    }
}
```

```php
<?php
// File: app/Exceptions/Domain/EntityNotFoundException.php

declare(strict_types=1);

namespace App\Exceptions\Domain;

class EntityNotFoundException extends DomainException
{
    public function __construct(string $entity, int|string $id)
    {
        parent::__construct(
            "{$entity} dengan ID {$id} tidak ditemukan.",
            'ENTITY_NOT_FOUND',
            ['entity' => $entity, 'id' => $id]
        );
    }

    public function getStatusCode(): int
    {
        return 404;
    }
}
```

### 2.3 Update DocumentLockedException & StateTransitionException

```php
<?php
// File: app/Exceptions/Domain/DocumentLockedException.php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Illuminate\Database\Eloquent\Model;

class DocumentLockedException extends DomainException
{
    public function __construct(string $message, array $context = [])
    {
        parent::__construct($message, 'DOCUMENT_LOCKED', $context);
    }

    public function getStatusCode(): int
    {
        return 409; // Conflict
    }

    public static function cannotEdit(Model $document, string $reason = ''): self
    {
        $type = class_basename($document);
        $message = "Dokumen {$type} tidak dapat diubah.";
        if ($reason) {
            $message .= " {$reason}";
        }

        return new self($message, [
            'document_type' => $type,
            'document_id' => $document->id,
            'status' => $document->status?->value ?? $document->status,
        ]);
    }

    public static function cannotDelete(Model $document, string $reason = ''): self
    {
        $type = class_basename($document);
        $message = "Dokumen {$type} tidak dapat dihapus.";
        if ($reason) {
            $message .= " {$reason}";
        }

        return new self($message, [
            'document_type' => $type,
            'document_id' => $document->id,
            'status' => $document->status?->value ?? $document->status,
        ]);
    }

    public static function hasDependencies(Model $document, string $dependency): self
    {
        $type = class_basename($document);

        return new self(
            "Dokumen {$type} tidak dapat dihapus karena memiliki {$dependency} terkait.",
            [
                'document_type' => $type,
                'document_id' => $document->id,
                'dependency' => $dependency,
            ]
        );
    }
}
```

### 2.4 Create Global Exception Handler Updates

```php
<?php
// Add to bootstrap/app.php exception handling

use App\Exceptions\Domain\DomainException;
use Illuminate\Foundation\Configuration\Exceptions;

->withExceptions(function (Exceptions $exceptions) {
    // Handle domain exceptions
    $exceptions->renderable(function (DomainException $e, $request) {
        if ($request->expectsJson()) {
            return response()->json($e->toArray(), $e->getStatusCode());
        }

        return null; // Let default handler deal with non-API requests
    });

    // Log all exceptions with context
    $exceptions->report(function (Throwable $e) {
        $logger = app(\App\Contracts\Logging\ContextualLoggerInterface::class);
        $logger->logError($e, [
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'input' => request()->except(['password', 'password_confirmation']),
        ]);
    });
})
```

---

## Part 3: Request ID Middleware

### 3.1 Create Request ID Middleware

```php
<?php
// File: app/Http/Middleware/RequestIdMiddleware.php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-ID') ?? Str::uuid()->toString();

        // Make request ID available globally
        $request->headers->set('X-Request-ID', $requestId);

        // Add to response
        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
```

### 3.2 Register Middleware

```php
// Add to bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\RequestIdMiddleware::class,
    ]);
})
```

---

## Part 4: Health Check Endpoint

### 4.1 Create Health Check Controller

```php
<?php
// File: app/Http/Controllers/Api/HealthController.php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function check(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['status'] === 'ok');

        return response()->json([
            'status' => $healthy ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
        ], $healthy ? 200 : 503);
    }

    public function ready(): JsonResponse
    {
        // Basic readiness check - can accept traffic
        return response()->json([
            'status' => 'ready',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    public function live(): JsonResponse
    {
        // Basic liveness check - process is running
        return response()->json([
            'status' => 'live',
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => 'ok',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed',
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $key = 'health_check_' . time();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $latency = (microtime(true) - $start) * 1000;

            return [
                'status' => $value === 'ok' ? 'ok' : 'error',
                'latency_ms' => round($latency, 2),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Cache connection failed',
            ];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('app/.health_check');
            file_put_contents($path, 'ok');
            $content = file_get_contents($path);
            unlink($path);

            return [
                'status' => $content === 'ok' ? 'ok' : 'error',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Storage write failed',
            ];
        }
    }
}
```

### 4.2 Register Routes

```php
// Add to routes/api.php

use App\Http\Controllers\Api\HealthController;

// Health check endpoints (no auth required)
Route::get('/health', [HealthController::class, 'check']);
Route::get('/health/ready', [HealthController::class, 'ready']);
Route::get('/health/live', [HealthController::class, 'live']);
```

---

## Part 5: Performance Logging Trait

### 5.1 Create Performance Logging Trait

```php
<?php
// File: app/Traits/LogsPerformance.php

declare(strict_types=1);

namespace App\Traits;

use App\Contracts\Logging\ContextualLoggerInterface;

/**
 * Trait for services to easily log performance metrics.
 */
trait LogsPerformance
{
    private ?ContextualLoggerInterface $performanceLogger = null;

    protected function getPerformanceLogger(): ContextualLoggerInterface
    {
        if ($this->performanceLogger === null) {
            $this->performanceLogger = app(ContextualLoggerInterface::class);
        }

        return $this->performanceLogger;
    }

    /**
     * Measure and log execution time of a callback.
     *
     * @template T
     * @param string $operation
     * @param callable(): T $callback
     * @param array<string, mixed> $context
     * @return T
     */
    protected function measurePerformance(string $operation, callable $callback, array $context = []): mixed
    {
        $start = microtime(true);

        try {
            $result = $callback();
            $duration = microtime(true) - $start;

            $this->getPerformanceLogger()->logPerformance($operation, $duration, [
                ...$context,
                'status' => 'success',
            ]);

            return $result;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $start;

            $this->getPerformanceLogger()->logPerformance($operation, $duration, [
                ...$context,
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Log service method entry and exit.
     *
     * @template T
     * @param callable(): T $callback
     * @param array<string, mixed> $parameters
     * @return T
     */
    protected function loggedExecution(string $method, callable $callback, array $parameters = []): mixed
    {
        $class = static::class;
        $logger = $this->getPerformanceLogger();

        $logger->logEntry($class, $method, $parameters);

        try {
            $result = $callback();
            $logger->logExit($class, $method, $result);
            return $result;
        } catch (\Throwable $e) {
            $logger->logError($e, [
                'class' => $class,
                'method' => $method,
                'parameters' => $parameters,
            ]);
            throw $e;
        }
    }
}
```

### 5.2 Example Usage in Service

```php
<?php
// Example: Using LogsPerformance in InvoiceService

use App\Traits\LogsPerformance;

class InvoiceService extends AbstractDocumentService implements InvoiceServiceInterface
{
    use LogsPerformance;

    public function post(Invoice $invoice): Invoice
    {
        return $this->loggedExecution(__METHOD__, function () use ($invoice) {
            return $this->measurePerformance('invoice.post', function () use ($invoice) {
                if (! $invoice->stateMachine()->canPost()) {
                    throw StateTransitionException::actionNotAvailable('posting', $invoice->status->label());
                }

                $this->journalService->postInvoice($invoice);
                $this->cogsStrategy->onInvoicePost($invoice);
                $invoice->transitionTo(DocumentStatus::Sent, auth()->id());

                return $invoice->fresh(['contact', 'items', 'journalEntry.lines.account']);
            }, ['invoice_id' => $invoice->id, 'total_amount' => $invoice->total_amount]);
        }, ['invoice_id' => $invoice->id]);
    }
}
```

---

## Verification Checklist

After completing this phase, verify:

- [ ] `ContextualLoggerInterface` is registered in container
- [ ] All domain exceptions extend `DomainException`
- [ ] Exception handler returns proper JSON for API requests
- [ ] Request ID is present in response headers
- [ ] Health check endpoints respond correctly:
  - `GET /api/health` returns 200
  - `GET /api/health/ready` returns 200
  - `GET /api/health/live` returns 200
- [ ] All existing tests still pass
- [ ] Run `vendor/bin/pint` with no errors

---

## Tests to Add

```php
<?php
// File: tests/Feature/HealthCheckTest.php

use function Pest\Laravel\getJson;

describe('Health Check Endpoints', function () {

    it('returns healthy status when all checks pass', function () {
        getJson('/api/health')
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'timestamp',
                'checks' => [
                    'database' => ['status'],
                    'cache' => ['status'],
                    'storage' => ['status'],
                ],
                'version',
            ])
            ->assertJson(['status' => 'healthy']);
    });

    it('returns ready status', function () {
        getJson('/api/health/ready')
            ->assertOk()
            ->assertJson(['status' => 'ready']);
    });

    it('returns live status', function () {
        getJson('/api/health/live')
            ->assertOk()
            ->assertJson(['status' => 'live']);
    });
});
```

```php
<?php
// File: tests/Unit/Infrastructure/LaravelContextualLoggerTest.php

use App\Infrastructure\Logging\LaravelContextualLogger;
use Illuminate\Support\Facades\Log;

describe('LaravelContextualLogger', function () {

    it('logs service entry with sanitized parameters', function () {
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(fn ($message, $context) =>
                $message === 'Service method entry' &&
                $context['parameters']['password'] === '[REDACTED]'
            );

        $logger = new LaravelContextualLogger();
        $logger->logEntry('TestService', 'login', ['email' => 'test@example.com', 'password' => 'secret']);
    });

    it('logs domain events', function () {
        Log::shouldReceive('info')
            ->once()
            ->withArgs(fn ($message) => $message === 'Domain event fired');

        $event = new class {
            public int $invoiceId = 123;
            public string $status = 'sent';
        };

        $logger = new LaravelContextualLogger();
        $logger->logDomainEvent($event);
    });
});
```

---

## Next Phase

Once Phase 0 is complete and verified, proceed to [Phase 1: Domain Layer Consolidation](./02-phase-1-domain-layer.md).
