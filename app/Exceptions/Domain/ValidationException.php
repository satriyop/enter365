<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Exception for business rule validation failures.
 *
 * Use when:
 * - Domain invariants are violated
 * - Business rules prevent an action
 * - Data constraints are not met
 */
class ValidationException extends DomainException
{
    protected string $errorCode = 'VALIDATION_ERROR';

    protected int $statusCode = 422;

    /**
     * Create a validation exception with a field-specific context.
     *
     * @param  array<string, mixed>  $context
     */
    public static function withField(string $field, string $message, array $context = []): self
    {
        return new self($message, array_merge(['field' => $field], $context));
    }

    /**
     * Create a validation exception for a required field.
     */
    public static function required(string $field, string $label): self
    {
        return self::withField($field, "{$label} wajib diisi.", ['rule' => 'required']);
    }

    /**
     * Create a validation exception for an invalid value.
     *
     * @param  array<string, mixed>  $context
     */
    public static function invalid(string $field, string $message, array $context = []): self
    {
        return self::withField($field, $message, array_merge(['rule' => 'invalid'], $context));
    }
}
