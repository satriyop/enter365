<?php

declare(strict_types=1);

namespace App\Contracts\Shared;

/**
 * Strategy interface for document number generation.
 *
 * Extends the basic DocumentNumberGeneratorInterface with
 * context awareness and strategy identification.
 */
interface NumberGenerationStrategy
{
    /**
     * Generate a unique document number.
     */
    public function generate(string $prefix, string $table, string $column): string;

    /**
     * Generate document number with extended context.
     *
     * @param  array<string, mixed>  $context  Additional context (project_id, contact_id, etc.)
     */
    public function generateWithContext(string $prefix, string $table, string $column, array $context = []): string;

    /**
     * Get strategy name for configuration.
     */
    public function getName(): string;
}
