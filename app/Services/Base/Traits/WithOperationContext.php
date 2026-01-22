<?php

declare(strict_types=1);

namespace App\Services\Base\Traits;

use App\Support\OperationContext;

/**
 * Operation context management for services.
 *
 * Provides withContext() for setting context explicitly
 * and getContext() for resolving from container or auth.
 */
trait WithOperationContext
{
    protected ?OperationContext $operationContext = null;

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
     * Resolution order:
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
}
