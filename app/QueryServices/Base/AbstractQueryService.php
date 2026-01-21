<?php

declare(strict_types=1);

namespace App\QueryServices\Base;

use App\Contracts\Logging\ContextualLoggerInterface;
use App\Contracts\Repositories\RepositoryInterface;
use App\Domain\Shared\ValueObjects\DateRange;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Base class for read-only query services.
 *
 * Query services are optimized for reads and do not modify data.
 * They can use repositories or direct DB queries for performance.
 * Following CQRS-lite: separate query path from command path.
 *
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class AbstractQueryService
{
    protected RepositoryInterface $repository;

    protected ContextualLoggerInterface $logger;

    public function __construct(
        RepositoryInterface $repository,
        ContextualLoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->logger = $logger;
    }

    /**
     * Get single entity by ID.
     *
     * @return TModel|null
     */
    public function find(int $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * Get single entity by ID or throw exception.
     *
     * @return TModel
     *
     * @throws \App\Exceptions\Domain\EntityNotFoundException
     */
    public function findOrFail(int $id): object
    {
        return $this->repository->findOrFail($id);
    }

    /**
     * Get paginated list with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<TModel>
     */
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->paginate($perPage);
    }

    /**
     * Get collection with filters.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, TModel>
     */
    public function list(array $filters = []): Collection
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->get();
    }

    /**
     * Count records matching filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function count(array $filters = []): int
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->count();
    }

    /**
     * Check if any records match filters.
     *
     * @param  array<string, mixed>  $filters
     */
    public function exists(array $filters = []): bool
    {
        $query = $this->buildFilteredQuery($filters);

        return $query->exists();
    }

    /**
     * Get all records.
     *
     * @return Collection<int, TModel>
     */
    public function all(): Collection
    {
        return $this->repository->all();
    }

    /**
     * Get statistics for date range.
     *
     * @return array<string, mixed>
     */
    abstract public function getStatistics(DateRange $range): array;

    /**
     * Build filtered query.
     *
     * Subclasses implement this to define how filters are applied.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    abstract protected function buildFilteredQuery(array $filters): Builder;

    /**
     * Apply common search filter to query.
     *
     * @param  Builder<TModel>  $query
     * @param  array<string>  $searchFields  Fields to search in
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query, string $search, array $searchFields): Builder
    {
        return $query->where(function (Builder $q) use ($search, $searchFields) {
            foreach ($searchFields as $field) {
                $q->orWhere($field, 'like', "%{$search}%");
            }
        });
    }

    /**
     * Apply date range filter to query.
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applyDateRange(Builder $query, string $column, DateRange $range): Builder
    {
        return $query->whereBetween($column, [
            $range->start->toDateString(),
            $range->end->toDateString(),
        ]);
    }
}
