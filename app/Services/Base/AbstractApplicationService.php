<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
use App\Support\OperationContext;
use App\Support\Results\ServiceResult;
use Illuminate\Support\Facades\DB;

/**
 * Base class for application services.
 *
 * Application services orchestrate domain operations and coordinate between
 * repositories, domain services, and external services. They represent
 * use cases / application logic.
 *
 * Provides:
 * - Transaction management
 * - Contextual logging
 * - Event dispatching
 * - Error handling
 * - Operation context for user tracking
 */
abstract class AbstractApplicationService
{
    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    protected ?OperationContext $operationContext = null;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
    }

    /**
     * Set operation context for this service instance.
     *
     * Returns a clone to maintain immutability and allow fluent chaining.
     *
     * @example
     * $service->withContext(OperationContext::fromAuth())->create($data);
     */
    public function withContext(OperationContext $context): static
    {
        $clone = clone $this;
        $clone->operationContext = $context;

        return $clone;
    }

    /**
     * Get operation context.
     *
     * Resolution order (Laravel way):
     * 1. Explicitly set via withContext() - for tests and jobs
     * 2. Resolved from container - bound by BindOperationContext middleware
     * 3. Fallback to fromAuth() - for edge cases (shouldn't happen in HTTP context)
     */
    protected function getContext(): OperationContext
    {
        // 1. Explicit context (tests, jobs)
        if ($this->operationContext !== null) {
            return $this->operationContext;
        }

        // 2. Container binding (middleware)
        if (app()->bound(OperationContext::class)) {
            return app(OperationContext::class);
        }

        // 3. Fallback (shouldn't happen if middleware is registered)
        return OperationContext::fromAuth();
    }

    /**
     * Execute operation within transaction with logging.
     *
     * @template T
     *
     * @param  string  $operation  Operation name for logging
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $context  Additional context for logging
     * @return T
     *
     * @throws \Throwable
     */
    protected function executeInTransaction(string $operation, callable $callback, array $context = []): mixed
    {
        $this->logger->logEntry(static::class, $operation, $context);
        $start = microtime(true);

        try {
            $result = DB::transaction($callback);

            $this->logger->logPerformance(
                $operation,
                microtime(true) - $start,
                ['status' => 'success', ...$context]
            );

            $this->logger->logExit(static::class, $operation, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->logError($e, [
                'operation' => $operation,
                ...$context,
            ]);

            throw $e;
        }
    }

    /**
     * Execute operation without transaction (for nested transactions or read operations).
     *
     * @template T
     *
     * @param  string  $operation  Operation name for logging
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $context
     * @return T
     *
     * @throws \Throwable
     */
    protected function execute(string $operation, callable $callback, array $context = []): mixed
    {
        $this->logger->logEntry(static::class, $operation, $context);
        $start = microtime(true);

        try {
            $result = $callback();

            $this->logger->logPerformance(
                $operation,
                microtime(true) - $start,
                ['status' => 'success', ...$context]
            );

            $this->logger->logExit(static::class, $operation, $result);

            return $result;
        } catch (\Throwable $e) {
            $this->logger->logError($e, [
                'operation' => $operation,
                ...$context,
            ]);

            throw $e;
        }
    }

    /**
     * Dispatch domain event.
     */
    protected function dispatch(object $event): void
    {
        $this->eventDispatcher->dispatch($event);
        $this->logger->logDomainEvent($event);
    }

    /**
     * Get authenticated user ID from operation context.
     *
     * Uses getContext() for consistent resolution (explicit → container → fallback).
     */
    protected function getUserId(): ?int
    {
        return $this->getContext()->userId;
    }

    /**
     * Get tenant ID from operation context.
     *
     * For future multi-tenant support. Returns null until tenant infrastructure is ready.
     */
    protected function getTenantId(): ?int
    {
        return $this->getContext()->tenantId;
    }

    /**
     * Get tenant ID, throwing if not set.
     *
     * Use this for operations that MUST have tenant scope.
     *
     * @throws \RuntimeException if tenant ID is not set
     */
    protected function requireTenantId(): int
    {
        return $this->getContext()->requireTenantId();
    }

    /**
     * Create a failure result with logging.
     *
     * @param  array<string, mixed>  $context
     * @return ServiceResult<null>
     */
    protected function fail(string $message, array $context = []): ServiceResult
    {
        $this->logger->logOperation('operation_failed', [
            'message' => $message,
            ...$context,
        ]);

        return ServiceResult::failure($message);
    }
}
