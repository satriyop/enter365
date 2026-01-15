<?php

declare(strict_types=1);

namespace App\Filters\Traits;

/**
 * Trait for search filtering.
 *
 * Provides common search filtering methods:
 * - search (multi-field search)
 * - searchExact (exact match)
 *
 * Use in filter classes that need full-text or fuzzy search.
 */
trait HasSearchFilter
{
    /**
     * Get the fields to search in.
     * Override in filter class to customize.
     *
     * @return array<string>
     */
    protected function getSearchableFields(): array
    {
        return ['name'];
    }

    /**
     * Search across multiple fields (case-insensitive LIKE).
     */
    public function search(string $value): void
    {
        $searchTerm = strtolower(trim($value));
        $fields = $this->getSearchableFields();

        if (empty($fields) || empty($searchTerm)) {
            return;
        }

        $this->builder->where(function ($query) use ($searchTerm, $fields) {
            foreach ($fields as $index => $field) {
                // Handle relationship fields (e.g., 'contact.name')
                if (str_contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $method = $index === 0 ? 'whereHas' : 'orWhereHas';
                    $query->{$method}($relation, function ($q) use ($column, $searchTerm) {
                        $q->whereRaw("LOWER({$column}) LIKE ?", ["%{$searchTerm}%"]);
                    });
                } else {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}("LOWER({$field}) LIKE ?", ["%{$searchTerm}%"]);
                }
            }
        });
    }

    /**
     * Search with exact match.
     */
    public function searchExact(string $value): void
    {
        $fields = $this->getSearchableFields();

        if (empty($fields)) {
            return;
        }

        $this->builder->where(function ($query) use ($value, $fields) {
            foreach ($fields as $index => $field) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($field, $value);
            }
        });
    }

    /**
     * Search by keyword with word boundaries.
     */
    public function keyword(string $value): void
    {
        $searchTerm = strtolower(trim($value));
        $fields = $this->getSearchableFields();

        $this->builder->where(function ($query) use ($searchTerm, $fields) {
            foreach ($fields as $index => $field) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                // Match whole word or word at boundaries
                $query->{$method}(
                    "LOWER({$field}) REGEXP ?",
                    ["(^|[^a-z]){$searchTerm}([^a-z]|$)"]
                );
            }
        });
    }
}
