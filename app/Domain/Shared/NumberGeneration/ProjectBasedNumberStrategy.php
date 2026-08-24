<?php

declare(strict_types=1);

namespace App\Domain\Shared\NumberGeneration;

use App\Contracts\Shared\NumberGenerationStrategy;
use App\Domain\Shared\DocumentNumbers;

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

        $padLength = $context['pad_length'] ?? 3;

        return DocumentNumbers::generate("{$projectNumber}/{$prefix}/", $table, $column, $padLength);
    }

    public function getName(): string
    {
        return 'project_based';
    }
}
