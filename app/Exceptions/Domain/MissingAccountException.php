<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Exception thrown when a required accounting account is not found.
 *
 * This exception provides clear context about:
 * - Which account code(s) are missing
 * - What operation required them
 * - How to resolve the issue
 */
class MissingAccountException extends DomainException
{
    protected string $errorCode = 'MISSING_ACCOUNT';

    protected int $statusCode = 500;

    /**
     * Create exception for a single missing account code.
     */
    public static function forCode(string $code, string $purpose = ''): self
    {
        $message = "Required account with code '{$code}' not found";
        if ($purpose) {
            $message .= " for {$purpose}";
        }
        $message .= '. Please ensure the Chart of Accounts is properly seeded.';

        return new self($message, [
            'missing_code' => $code,
            'purpose' => $purpose,
        ]);
    }

    /**
     * Create exception for multiple missing account codes.
     *
     * @param  array<string>  $codes
     */
    public static function forCodes(array $codes, string $operation = ''): self
    {
        $codesStr = implode(', ', array_map(fn ($c) => "'{$c}'", $codes));
        $message = "Required accounts with codes {$codesStr} not found";
        if ($operation) {
            $message .= " for {$operation}";
        }
        $message .= '. Please ensure the Chart of Accounts is properly seeded.';

        return new self($message, [
            'missing_codes' => $codes,
            'operation' => $operation,
        ]);
    }

    /**
     * Create exception when an account ID doesn't exist.
     */
    public static function forId(int $id, string $purpose = ''): self
    {
        $message = "Account with ID {$id} not found";
        if ($purpose) {
            $message .= " for {$purpose}";
        }

        return new self($message, [
            'missing_id' => $id,
            'purpose' => $purpose,
        ]);
    }
}
