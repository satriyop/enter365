<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use ReflectionException;
use ReflectionMethod;

/**
 * Base class for query filters.
 *
 * Provides automatic method-based filtering. Each public method
 * becomes a filter that can be triggered via request parameters.
 *
 * Usage:
 * 1. Create a filter class extending QueryFilter
 * 2. Add methods matching request parameter names (camelCase)
 * 3. Inject filter into controller and call $query->filter($filter)
 *
 * Example:
 * ```php
 * class ProductFilter extends QueryFilter
 * {
 *     public function status(string $value): void
 *     {
 *         $this->builder->where('status', $value);
 *     }
 *
 *     public function categoryId(int $value): void
 *     {
 *         $this->builder->where('category_id', $value);
 *     }
 * }
 * ```
 */
abstract class QueryFilter
{
    protected Builder $builder;

    protected Request $request;

    /**
     * Parameters to exclude from automatic filtering.
     *
     * @var array<string>
     */
    protected array $excludedParameters = [
        'page',
        'per_page',
        'sort',
        'direction',
        'order',
        'limit',
        'offset',
    ];

    /**
     * Public methods declared on this base class that are legitimate request filters.
     *
     * @var list<string>
     */
    private const BASE_REQUEST_FILTERS = ['include'];

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply all filters to the query builder.
     */
    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->getFilterableParameters() as $name => $value) {
            $method = Str::camel($name);

            if ($this->shouldApplyFilter($method, $value)) {
                $this->{$method}($value);
            }
        }

        // Apply sorting if present
        $this->applySorting();

        return $this->builder;
    }

    /**
     * Get parameters that should be used for filtering.
     *
     * @return array<string, mixed>
     */
    protected function getFilterableParameters(): array
    {
        return collect($this->request->all())
            ->except($this->excludedParameters)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    /**
     * Explicitly load relationships (e.g., ?include=items,contact)
     *
     * @param  string  $value  Comma-separated list of relationships
     */
    public function include(string $value): void
    {
        $includes = explode(',', $value);
        $allowed = $this->getAllowedIncludes();

        $validIncludes = array_filter($includes, function ($include) use ($allowed) {
            return in_array(trim($include), $allowed, true);
        });

        if (! empty($validIncludes)) {
            $this->builder->with(array_map('trim', $validIncludes));
        }
    }

    /**
     * Get allowed relationships for explicit loading.
     * Override in child classes.
     *
     * @return array<string>
     */
    protected function getAllowedIncludes(): array
    {
        return [];
    }

    /**
     * Check if a filter method should be applied.
     *
     * Only public instance methods with at most one required parameter are
     * callable from the request. Methods declared on QueryFilter itself are
     * infrastructure (apply, applySorting, getters) except include().
     */
    protected function shouldApplyFilter(string $method, mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            $reflection = new ReflectionMethod($this, $method);
        } catch (ReflectionException) {
            return false;
        }

        if (! $reflection->isPublic() || $reflection->isStatic() || str_starts_with($method, '__')) {
            return false;
        }

        if ($reflection->getNumberOfRequiredParameters() > 1) {
            return false;
        }

        $declaredOn = $reflection->getDeclaringClass()->getName();

        if ($declaredOn === self::class && ! in_array($method, self::BASE_REQUEST_FILTERS, true)) {
            return false;
        }

        return true;
    }

    /**
     * Apply sorting to the query.
     */
    protected function applySorting(): void
    {
        $sortField = $this->request->input('sort', $this->getDefaultSortField());
        $sortDirection = strtolower((string) $this->request->input('direction', $this->getDefaultSortDirection()));

        if (! in_array($sortDirection, ['asc', 'desc'], true)) {
            $sortDirection = $this->getDefaultSortDirection();
        }

        if (is_string($sortField) && $sortField !== '' && $this->isValidSortField($sortField)) {
            $this->builder->orderBy($sortField, $sortDirection);
        }
    }

    /**
     * Get the default sort field.
     */
    protected function getDefaultSortField(): ?string
    {
        return 'created_at';
    }

    /**
     * Get the default sort direction.
     */
    protected function getDefaultSortDirection(): string
    {
        return 'desc';
    }

    /**
     * Get allowed sort fields.
     *
     * @return array<string>
     */
    protected function getAllowedSortFields(): array
    {
        return ['id', 'created_at', 'updated_at'];
    }

    /**
     * Check if sort field is valid.
     */
    protected function isValidSortField(string $field): bool
    {
        return in_array($field, $this->getAllowedSortFields(), true);
    }

    /**
     * Get the current request instance.
     */
    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * Get the current builder instance.
     */
    public function getBuilder(): Builder
    {
        return $this->builder;
    }
}
