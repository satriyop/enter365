<?php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use Illuminate\Support\Facades\DB;

/**
 * Project-based numbering: PRJ-001/WO/001
 *
 * Numbers are sequential within each project, formatted as:
 * {project_number}/{doc_prefix}/{sequence}
 */
class ProjectBasedNumberStrategy implements NumberGenerationStrategy
{
    private NumberGenerationStrategy $fallback;

    public function __construct(?NumberGenerationStrategy $fallback = null)
    {
        $this->fallback = $fallback ?? new SequentialNumberStrategy;
    }

    /**
     * Generate (falls back to sequential without context).
     */
    public function generate(string $prefix, string $table, string $column): string
    {
        return $this->fallback->generate($prefix, $table, $column);
    }

    /**
     * Generate project-based number if project_number is provided.
     */
    public function generateWithContext(string $prefix, string $table, string $column, array $context = []): string
    {
        $projectNumber = $context['project_number'] ?? null;

        if (! $projectNumber) {
            return $this->fallback->generateWithContext($prefix, $table, $column, $context);
        }

        $pattern = "{$projectNumber}/{$prefix}/%";
        $padLength = $context['pad_length'] ?? 3;

        $lastRecord = DB::table($table)
            ->where($column, 'like', $pattern)
            ->orderByRaw("CAST(SUBSTRING_INDEX({$column}, '/', -1) AS UNSIGNED) DESC")
            ->first();

        if ($lastRecord) {
            $lastSequence = (int) substr($lastRecord->$column, strrpos($lastRecord->$column, '/') + 1);
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return "{$projectNumber}/{$prefix}/".str_pad((string) $nextSequence, $padLength, '0', STR_PAD_LEFT);
    }

    public function getName(): string
    {
        return 'project_based';
    }
}
