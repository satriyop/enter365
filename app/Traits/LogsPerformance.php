<?php

declare(strict_types=1);

namespace App\Traits;

use App\Contracts\Logging\ContextualLoggerInterface;

/**
 * Trait for services to easily log performance metrics.
 *
 * Provides methods for:
 * - Measuring and logging execution time
 * - Logging service method entry/exit
 *
 * Usage:
 * ```php
 * class InvoiceService
 * {
 *     use LogsPerformance;
 *
 *     public function post(Invoice $invoice): Invoice
 *     {
 *         return $this->measurePerformance('invoice.post', function () use ($invoice) {
 *             // ... post logic
 *             return $invoice;
 *         }, ['invoice_id' => $invoice->id]);
 *     }
 * }
 * ```
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
     *
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $context
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
     *
     * @param  callable(): T  $callback
     * @param  array<string, mixed>  $parameters
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
