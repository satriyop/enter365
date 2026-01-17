<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Contracts\Services\Domain\DocumentNumberGeneratorInterface;

/**
 * Sequence number generator for testing.
 *
 * Generates sequential numbers without database access.
 */
class SequenceNumberGenerator implements DocumentNumberGeneratorInterface
{
    private int $counter = 1;

    /**
     * Generate a sequential number for testing.
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        return $prefix.str_pad((string) $this->counter++, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Reset the counter (useful in tests).
     */
    public function reset(): void
    {
        $this->counter = 1;
    }
}
