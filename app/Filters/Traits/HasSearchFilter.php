<?php

declare(strict_types=1);

namespace App\Filters\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait for search filtering.
 *
 * Provides common search filtering methods:
 * - search (multi-field search)
 * - searchExact (exact match)
 * - keyword (whole-token search)
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
                        $q->whereRaw("LOWER(\"{$column}\") LIKE ?", ["%{$searchTerm}%"]);
                    });
                } else {
                    $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                    $query->{$method}("LOWER(\"{$field}\") LIKE ?", ["%{$searchTerm}%"]);
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
     *
     * Uses a POSIX regex on PostgreSQL, REGEXP on MySQL/MariaDB, and a
     * space-padded LIKE on SQLite (no REGEXP function by default). The
     * search term is always escaped so metacharacters cannot break the
     * pattern or become a ReDoS vector.
     */
    public function keyword(string $value): void
    {
        $searchTerm = mb_strtolower(trim($value));
        $fields = $this->getSearchableFields();

        if ($fields === [] || $searchTerm === '') {
            return;
        }

        $this->builder->where(function ($query) use ($searchTerm, $fields) {
            foreach ($fields as $index => $field) {
                if (str_contains($field, '.')) {
                    [$relation, $column] = explode('.', $field, 2);
                    $method = $index === 0 ? 'whereHas' : 'orWhereHas';
                    $query->{$method}($relation, function ($relationQuery) use ($column, $searchTerm) {
                        $this->constrainKeyword($relationQuery, $column, $searchTerm, asOr: false);
                    });

                    continue;
                }

                $this->constrainKeyword($query, $field, $searchTerm, asOr: $index > 0);
            }
        });
    }

    /**
     * Apply a driver-specific whole-token match on a single column.
     */
    private function constrainKeyword(Builder $query, string $field, string $searchTerm, bool $asOr): void
    {
        $column = $query->getGrammar()->wrap($field);
        $driver = $query->getConnection()->getDriverName();
        $method = $asOr ? 'orWhereRaw' : 'whereRaw';
        $escaped = preg_quote($searchTerm, '/');

        [$sql, $binding] = match ($driver) {
            'pgsql' => [
                "{$column}::text ~* ?",
                '(^|[^a-z])'.$escaped.'([^a-z]|$)',
            ],
            'mysql', 'mariadb' => [
                "LOWER({$column}) REGEXP ?",
                '(^|[^a-z])'.$escaped.'([^a-z]|$)',
            ],
            default => [
                "(' ' || LOWER({$column}) || ' ') LIKE ?",
                '% '.str_replace(['\\', '%', '_'], '', $searchTerm).' %',
            ],
        };

        $query->{$method}($sql, [$binding]);
    }
}
