<?php

declare(strict_types=1);

namespace App\Services\Shared;

use App\Contracts\Shared\NumberGenerationStrategy;
use InvalidArgumentException;

/**
 * Manages number generation strategies for different document types.
 *
 * Allows configuration-based strategy selection per document type.
 */
class NumberGenerationManager
{
    /** @var array<string, NumberGenerationStrategy> */
    private array $strategies = [];

    private string $defaultStrategy = 'sequential';

    /**
     * Register a number generation strategy.
     */
    public function register(NumberGenerationStrategy $strategy): self
    {
        $this->strategies[$strategy->getName()] = $strategy;

        return $this;
    }

    /**
     * Set the default strategy name.
     */
    public function setDefault(string $strategyName): self
    {
        $this->defaultStrategy = $strategyName;

        return $this;
    }

    /**
     * Generate document number using configured strategy.
     *
     * @param  string  $documentType  Document type for configuration lookup
     * @param  string  $prefix  Number prefix (e.g., 'INV-202501-')
     * @param  string  $table  Database table name
     * @param  string  $column  Column containing document numbers
     * @param  array<string, mixed>  $context  Additional context
     */
    public function generate(
        string $documentType,
        string $prefix,
        string $table,
        string $column,
        array $context = []
    ): string {
        $strategyName = config("documents.{$documentType}.numbering", $this->defaultStrategy);
        $strategy = $this->getStrategy($strategyName);

        return $strategy->generateWithContext($prefix, $table, $column, $context);
    }

    /**
     * Get a specific strategy by name.
     *
     * @throws InvalidArgumentException If strategy not found
     */
    public function getStrategy(string $name): NumberGenerationStrategy
    {
        if (! isset($this->strategies[$name])) {
            throw new InvalidArgumentException("Unknown numbering strategy: {$name}");
        }

        return $this->strategies[$name];
    }

    /**
     * Check if a strategy is registered.
     */
    public function hasStrategy(string $name): bool
    {
        return isset($this->strategies[$name]);
    }

    /**
     * Get all registered strategy names.
     *
     * @return array<string>
     */
    public function getAvailableStrategies(): array
    {
        return array_keys($this->strategies);
    }
}
