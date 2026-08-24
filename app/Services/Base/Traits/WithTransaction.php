<?php

declare(strict_types=1);

namespace App\Services\Base\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Transaction management for services.
 *
 * Provides executeInTransaction() and execute() methods
 * with automatic logging and error handling.
 */
trait WithTransaction
{
    /**
     * Execute operation within transaction with logging.
     *
     * @template T
     *
     * @param  string  $operation  Operation name for logging
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $context  Additional context for logging
     * @param  int  $attempts  Deadlock retries (Laravel retries only deadlock / serialization failures)
     * @return T
     *
     * @throws \Throwable
     */
    protected function executeInTransaction(string $operation, callable $callback, array $context = [], int $attempts = 3): mixed
    {
        $this->logger->logEntry(static::class, $operation, $context);
        $start = microtime(true);

        try {
            $result = DB::transaction($callback, $attempts);

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
     * Execute operation without transaction.
     *
     * Use for nested transactions or read operations.
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
}
