<?php

declare(strict_types=1);

namespace App\Services\Base;

use App\Contracts\Events\EventDispatcherInterface;
use App\Contracts\Logging\ContextualLoggerInterface;
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
 */
abstract class AbstractApplicationService
{
    protected EventDispatcherInterface $eventDispatcher;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        ContextualLoggerInterface $logger
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
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
     * Get authenticated user ID.
     */
    protected function getUserId(): ?int
    {
        return auth()->id();
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
