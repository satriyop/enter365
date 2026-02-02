<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Immutable context object for service operations.
 *
 * Laravel way: Bound to container via BindOperationContext middleware.
 * Services resolve automatically - no manual withContext() calls needed in controllers.
 *
 * Usage:
 * - HTTP requests: Automatic via middleware (resolves from container)
 * - Jobs/Commands: OperationContext::system() or OperationContext::forJob()
 * - Testing: Bind mock context to container or use withContext()
 *
 * Future multi-tenant: tenantId property ready for when infrastructure is built.
 *
 * @example
 * // In service - automatic resolution from container
 * $context = $this->getContext(); // Resolves from container
 *
 * // In test - bind to container
 * $this->app->scoped(OperationContext::class, fn () => OperationContext::forUser(42));
 *
 * // In job - explicit context
 * $service->withContext(OperationContext::forJob($userId))->process();
 */
final class OperationContext
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly ?int $userId,
        public readonly ?int $tenantId = null,
        public readonly ?string $ipAddress = null,
        public readonly ?DateTimeInterface $timestamp = null,
        public readonly array $metadata = [],
    ) {}

    /**
     * Create context from current authenticated user.
     *
     * Note: In HTTP context, prefer resolving from container (automatic via middleware).
     * Use this method for edge cases where container binding isn't available.
     */
    public static function fromAuth(): self
    {
        return new self(
            userId: auth()->id(),
            tenantId: null, // TODO: auth()->user()?->tenant_id when multi-tenant ready
            ipAddress: request()->ip(),
            timestamp: now(),
            metadata: ['source' => 'auth'],
        );
    }

    /**
     * Create context for system operations.
     *
     * Use this in jobs, commands, and scheduled tasks where no user is authenticated.
     */
    public static function system(): self
    {
        return new self(
            userId: null,
            timestamp: now(),
            metadata: ['source' => 'system'],
        );
    }

    /**
     * Create context for a specific user.
     *
     * Use this in tests or when impersonating a user.
     */
    public static function forUser(int $userId): self
    {
        return new self(
            userId: $userId,
            timestamp: now(),
        );
    }

    /**
     * Create context for a job or queue worker.
     *
     * Optionally includes the user who dispatched the job.
     */
    public static function forJob(?int $dispatchedBy = null, ?string $jobName = null): self
    {
        return new self(
            userId: $dispatchedBy,
            timestamp: now(),
            metadata: array_filter([
                'source' => 'job',
                'job_name' => $jobName,
            ]),
        );
    }

    /**
     * Create context for an artisan command.
     */
    public static function forCommand(string $commandName): self
    {
        return new self(
            userId: null,
            timestamp: now(),
            metadata: [
                'source' => 'command',
                'command_name' => $commandName,
            ],
        );
    }

    /**
     * Create a new context with additional metadata.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self(
            userId: $this->userId,
            tenantId: $this->tenantId,
            ipAddress: $this->ipAddress,
            timestamp: $this->timestamp,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Create a new context with a different user.
     *
     * Useful for operations that need to be performed on behalf of another user.
     */
    public function withUser(int $userId): self
    {
        return new self(
            userId: $userId,
            tenantId: $this->tenantId,
            ipAddress: $this->ipAddress,
            timestamp: $this->timestamp,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new context with a specific tenant.
     *
     * For future multi-tenant support.
     */
    public function withTenant(int $tenantId): self
    {
        return new self(
            userId: $this->userId,
            tenantId: $tenantId,
            ipAddress: $this->ipAddress,
            timestamp: $this->timestamp,
            metadata: $this->metadata,
        );
    }

    /**
     * Get tenant ID.
     *
     * @throws \RuntimeException if tenant ID is not set (for operations requiring tenant scope)
     */
    public function requireTenantId(): int
    {
        if ($this->tenantId === null) {
            throw new \RuntimeException('Operation requires tenant context but none was set.');
        }

        return $this->tenantId;
    }

    /**
     * Check if this is a system context (no authenticated user).
     */
    public function isSystem(): bool
    {
        return $this->userId === null;
    }

    /**
     * Check if this context has a specific metadata key.
     */
    public function hasMetadata(string $key): bool
    {
        return array_key_exists($key, $this->metadata);
    }

    /**
     * Get a metadata value.
     */
    public function getMetadata(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    /**
     * Get the source of this context (auth, system, job, command).
     */
    public function getSource(): string
    {
        return $this->metadata['source'] ?? 'auth';
    }
}
