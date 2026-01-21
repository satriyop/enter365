<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Logging\ContextualLoggerInterface;
use Throwable;

/**
 * Null implementation for testing - logs nothing.
 *
 * Use this in tests to avoid log noise while still
 * satisfying the interface contract.
 */
class NullContextualLogger implements ContextualLoggerInterface
{
    public function logEntry(string $class, string $method, array $parameters = []): void {}

    public function logExit(string $class, string $method, mixed $result = null): void {}

    public function logDomainEvent(object $event): void {}

    public function logOperation(string $operation, array $context = []): void {}

    public function logError(Throwable $exception, array $context = []): void {}

    public function logPerformance(string $operation, float $duration, array $metrics = []): void {}
}
