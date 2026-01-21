<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Exception for business rule violations.
 *
 * Use when:
 * - Business constraints prevent an operation (e.g., insufficient stock)
 * - Credit limits are exceeded
 * - Fiscal periods are closed
 * - Any domain-specific rule is violated
 */
class BusinessRuleException extends DomainException
{
    protected string $errorCode = 'BUSINESS_RULE_VIOLATION';

    protected int $statusCode = 409; // Conflict

    /**
     * Create exception for insufficient stock.
     */
    public static function insufficientStock(string $product, float $required, float $available): self
    {
        return new self(
            "Stok tidak cukup untuk {$product}. Dibutuhkan: {$required}, Tersedia: {$available}",
            [
                'product' => $product,
                'required' => $required,
                'available' => $available,
            ]
        );
    }

    /**
     * Create exception for credit limit exceeded.
     */
    public static function creditLimitExceeded(string $contact, int $limit, int $outstanding): self
    {
        return new self(
            "Limit kredit terlampaui untuk {$contact}. Limit: {$limit}, Outstanding: {$outstanding}",
            [
                'contact' => $contact,
                'limit' => $limit,
                'outstanding' => $outstanding,
            ]
        );
    }

    /**
     * Create exception for fiscal period closed.
     */
    public static function fiscalPeriodClosed(string $period): self
    {
        return new self(
            "Periode fiskal {$period} sudah ditutup. Tidak bisa posting transaksi.",
            ['period' => $period]
        );
    }

    /**
     * Create exception for duplicate entry.
     */
    public static function duplicateEntry(string $entity, string $field, mixed $value): self
    {
        return new self(
            "{$entity} dengan {$field} '{$value}' sudah ada.",
            [
                'entity' => $entity,
                'field' => $field,
                'value' => $value,
            ]
        );
    }

    /**
     * Create exception for operation not allowed in current state.
     */
    public static function operationNotAllowed(string $operation, string $reason): self
    {
        return new self(
            "Operasi {$operation} tidak diizinkan. {$reason}",
            [
                'operation' => $operation,
                'reason' => $reason,
            ]
        );
    }
}
