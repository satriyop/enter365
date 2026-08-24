<?php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use App\Domain\Shared\DocumentNumbers;

/**
 * Sequential number generation: PREFIX-YYYYMM-0001
 *
 * Standard document numbering that resets per month.
 */
class SequentialNumberStrategy implements NumberGenerationStrategy
{
    private int $padLength;

    public function __construct(int $padLength = 4)
    {
        $this->padLength = $padLength;
    }

    /**
     * Generate a unique document number by querying the database.
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        return $this->generateWithContext($prefix, $table, $column);
    }

    /**
     * Generate with extended context (context is ignored for sequential).
     */
    public function generateWithContext(string $prefix, string $table, string $column, array $context = []): string
    {
        $padLength = $context['pad_length'] ?? $this->padLength;

        return DocumentNumbers::generate($prefix, $table, $column, $padLength);
    }

    public function getName(): string
    {
        return 'sequential';
    }
}
