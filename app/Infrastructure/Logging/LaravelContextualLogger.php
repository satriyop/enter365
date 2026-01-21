<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use App\Contracts\Logging\ContextualLoggerInterface;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Throwable;

/**
 * Laravel implementation of contextual logging.
 *
 * Provides structured logging with global context (app version, environment)
 * and automatic sanitization of sensitive parameters.
 */
class LaravelContextualLogger implements ContextualLoggerInterface
{
    /** @var array<string, mixed> */
    private array $globalContext = [];

    /** @var array<int, string> */
    private array $sensitiveKeys = ['password', 'token', 'secret', 'key', 'authorization', 'api_key', 'apikey'];

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

    public function logError(Throwable $exception, array $context = []): void
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
     *
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function sanitizeParameters(array $parameters): array
    {
        return collect($parameters)->map(function ($value, $key) {
            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                return '[REDACTED]';
            }
            if (is_object($value)) {
                return get_class($value).':'.(property_exists($value, 'id') ? $value->id : 'new');
            }
            if (is_array($value)) {
                return $this->sanitizeParameters($value);
            }

            return $value;
        })->toArray();
    }

    /**
     * Extract data from domain event for logging.
     *
     * @return array<string, mixed>
     */
    private function extractEventData(object $event): array
    {
        $reflection = new ReflectionClass($event);
        $data = [];

        foreach ($reflection->getProperties() as $property) {
            $value = $property->getValue($event);

            if ($value instanceof \DateTimeInterface) {
                $data[$property->getName()] = $value->format('c');
            } elseif (is_scalar($value) || $value === null) {
                $data[$property->getName()] = $value;
            } else {
                $data[$property->getName()] = get_debug_type($value);
            }
        }

        return $data;
    }
}
