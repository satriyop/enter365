<?php

declare(strict_types=1);

namespace App\Contracts\Logging;

/**
 * Interface for structured contextual logging throughout the application.
 *
 * Provides consistent logging patterns for:
 * - Service method entry/exit tracking
 * - Domain event logging
 * - Business operation logging
 * - Error logging with context
 * - Performance metrics
 */
interface ContextualLoggerInterface
{
    /**
     * Log service method entry.
     *
     * @param  array<string, mixed>  $parameters
     */
    public function logEntry(string $class, string $method, array $parameters = []): void;

    /**
     * Log service method exit.
     */
    public function logExit(string $class, string $method, mixed $result = null): void;

    /**
     * Log domain event.
     */
    public function logDomainEvent(object $event): void;

    /**
     * Log business operation.
     *
     * @param  array<string, mixed>  $context
     */
    public function logOperation(string $operation, array $context = []): void;

    /**
     * Log error with context.
     *
     * @param  array<string, mixed>  $context
     */
    public function logError(\Throwable $exception, array $context = []): void;

    /**
     * Log performance metrics.
     *
     * @param  array<string, mixed>  $metrics
     */
    public function logPerformance(string $operation, float $duration, array $metrics = []): void;
}
