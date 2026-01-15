<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use Exception;
use Throwable;

/**
 * Base exception for all domain/business rule violations.
 *
 * Provides structured error responses with:
 * - Error code for client-side handling
 * - Context data for debugging
 * - HTTP status code mapping
 */
abstract class DomainException extends Exception
{
    protected string $errorCode = 'DOMAIN_ERROR';

    /** @var array<string, mixed> */
    protected array $context = [];

    protected int $statusCode = 422;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ) {
        $this->context = $context;
        parent::__construct($message, 0, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Convert exception to array for JSON response.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'context' => $this->context,
        ];
    }
}
