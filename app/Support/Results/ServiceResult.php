<?php

declare(strict_types=1);

namespace App\Support\Results;

/**
 * Base result object for service operations.
 *
 * Provides a consistent API for returning operation results:
 * - Success/failure status
 * - Data payload (on success)
 * - Error messages and validation errors
 * - Metadata for additional context
 *
 * @template T
 */
class ServiceResult
{
    private bool $success;

    private ?object $data;

    private ?string $message;

    /** @var array<string, array<string>|string> */
    private array $errors;

    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param  array<string, array<string>|string>  $errors
     * @param  array<string, mixed>  $metadata
     */
    protected function __construct(
        bool $success,
        ?object $data = null,
        ?string $message = null,
        array $errors = [],
        array $metadata = []
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->message = $message;
        $this->errors = $errors;
        $this->metadata = $metadata;
    }

    /**
     * Create success result.
     *
     * @template TData of object
     *
     * @param  TData|null  $data
     * @return self<TData>
     */
    public static function success(?object $data = null, ?string $message = null): static
    {
        return new static(true, $data, $message ?? 'Operasi berhasil.');
    }

    /**
     * Create failure result.
     *
     * @param  array<string, array<string>|string>  $errors
     * @return self<null>
     */
    public static function failure(string $message, array $errors = []): static
    {
        return new static(false, null, $message, $errors);
    }

    /**
     * Create validation failure result.
     *
     * @param  array<string, array<string>>  $errors
     * @return self<null>
     */
    public static function validationFailed(array $errors): static
    {
        return new static(false, null, 'Validasi gagal.', $errors);
    }

    /**
     * Create not found result.
     *
     * @return self<null>
     */
    public static function notFound(string $entityName, int|string $id): static
    {
        return new static(false, null, "{$entityName} dengan ID {$id} tidak ditemukan.");
    }

    /**
     * Create unauthorized result.
     *
     * @return self<null>
     */
    public static function unauthorized(?string $message = null): static
    {
        return new static(false, null, $message ?? 'Akses tidak diizinkan.');
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function isFailure(): bool
    {
        return ! $this->success;
    }

    /**
     * @return T|null
     */
    public function getData(): ?object
    {
        return $this->data;
    }

    /**
     * Get data or throw exception if failed.
     *
     * @return T
     *
     * @throws \RuntimeException
     */
    public function getDataOrFail(): object
    {
        if ($this->isFailure()) {
            throw new \RuntimeException($this->message ?? 'Operation failed');
        }

        if ($this->data === null) {
            throw new \RuntimeException('No data available');
        }

        return $this->data;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string, array<string>|string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Check if result has specific error.
     */
    public function hasError(string $key): bool
    {
        return isset($this->errors[$key]);
    }

    /**
     * Return new instance with updated message.
     */
    public function withMessage(string $message): static
    {
        $clone = clone $this;
        $clone->message = $message;

        return $clone;
    }

    /**
     * Add metadata to result.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function withMetadata(array $metadata): static
    {
        $clone = clone $this;
        $clone->metadata = array_merge($clone->metadata, $metadata);

        return $clone;
    }

    /**
     * Get all metadata or specific key.
     *
     * @return array<string, mixed>|mixed
     */
    public function getMetadata(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->metadata;
        }

        return $this->metadata[$key] ?? null;
    }

    /**
     * Convert to array for API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
        ];

        if ($this->message) {
            $result['message'] = $this->message;
        }

        if ($this->success && $this->data) {
            $result['data'] = $this->data;
        }

        if (! $this->success && ! empty($this->errors)) {
            $result['errors'] = $this->errors;
        }

        return $result;
    }
}
