<?php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use Illuminate\Support\Facades\DB;

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

        $lastRecord = DB::table($table)
            ->where($column, 'like', $prefix.'%')
            ->orderBy($column, 'desc')
            ->first();

        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->$column, -$padLength);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix.str_pad((string) $nextNumber, $padLength, '0', STR_PAD_LEFT);
    }

    public function getName(): string
    {
        return 'sequential';
    }
}
