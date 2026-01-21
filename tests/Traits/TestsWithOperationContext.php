<?php

declare(strict_types=1);

namespace Tests\Traits;

use App\Support\OperationContext;

/**
 * Trait for testing services with OperationContext.
 *
 * Provides helper methods to create and inject operation contexts
 * into services for unit testing without mocking Laravel's auth system.
 *
 * Usage in Pest:
 * ```php
 * uses(TestsWithOperationContext::class);
 *
 * it('creates quotation with specific user', function () {
 *     $service = $this->withUserContext(
 *         app(QuotationService::class),
 *         userId: 42
 *     );
 *     $result = $service->create([...]);
 *     // assertions...
 * });
 * ```
 */
trait TestsWithOperationContext
{
    /**
     * Create an operation context for a specific user.
     */
    protected function createUserContext(int $userId): OperationContext
    {
        return OperationContext::forUser($userId);
    }

    /**
     * Create a system operation context (no authenticated user).
     */
    protected function createSystemContext(): OperationContext
    {
        return OperationContext::system();
    }

    /**
     * Create a context for a job with optional dispatcher.
     */
    protected function createJobContext(?int $dispatchedBy = null, ?string $jobName = null): OperationContext
    {
        return OperationContext::forJob($dispatchedBy, $jobName);
    }

    /**
     * Create a context for an artisan command.
     */
    protected function createCommandContext(string $commandName): OperationContext
    {
        return OperationContext::forCommand($commandName);
    }

    /**
     * Inject user context into a service that supports withContext().
     *
     * @template T of object
     *
     * @param  T  $service
     * @return T
     */
    protected function withUserContext(object $service, int $userId): object
    {
        if (method_exists($service, 'withContext')) {
            return $service->withContext(OperationContext::forUser($userId));
        }

        return $service;
    }

    /**
     * Inject system context into a service that supports withContext().
     *
     * @template T of object
     *
     * @param  T  $service
     * @return T
     */
    protected function withSystemContext(object $service): object
    {
        if (method_exists($service, 'withContext')) {
            return $service->withContext(OperationContext::system());
        }

        return $service;
    }

    /**
     * Inject job context into a service that supports withContext().
     *
     * @template T of object
     *
     * @param  T  $service
     * @return T
     */
    protected function withJobContext(
        object $service,
        ?int $dispatchedBy = null,
        ?string $jobName = null
    ): object {
        if (method_exists($service, 'withContext')) {
            return $service->withContext(OperationContext::forJob($dispatchedBy, $jobName));
        }

        return $service;
    }

    /**
     * Inject custom context into a service that supports withContext().
     *
     * @template T of object
     *
     * @param  T  $service
     * @return T
     */
    protected function withContext(object $service, OperationContext $context): object
    {
        if (method_exists($service, 'withContext')) {
            return $service->withContext($context);
        }

        return $service;
    }

    /**
     * Assert that a service supports the withContext() method.
     */
    protected function assertSupportsOperationContext(object $service): void
    {
        expect(method_exists($service, 'withContext'))
            ->toBeTrue(get_class($service).' does not support withContext()');
    }
}
